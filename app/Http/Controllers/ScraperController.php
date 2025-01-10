<?php

namespace App\Http\Controllers;

use App\Services\RedditScraperService;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ScraperController extends Controller
{
    protected $redditScraper;
    protected $googleDriveService;

    public function __construct(RedditScraperService $redditScraper, GoogleDriveService $googleDriveService)
    {
        $this->redditScraper = $redditScraper;
        $this->googleDriveService = $googleDriveService;
    }

    public function scrapeAndUpload(Request $request)
    {
        // Increase script execution time
        set_time_limit(300); // 5 minutes

        try {
            $request->validate([
                'reddit_url' => 'required|url',
                'minimum_upvotes' => 'nullable|integer|min:1',
                'keywords' => 'nullable|string',
                'drive_folder_link' => 'required|url',
                'scrape_images' => 'required|boolean',
                'scrape_videos' => 'required|boolean',
                'create_folders' => 'required|boolean',
            ]);

            $redditUrl = $request->input('reddit_url');
            $minimumUpvotes = $request->input('minimum_upvotes', 50);
            $keywords = $request->input('keywords') ? array_map('trim', explode(',', $request->input('keywords'))) : [];
            $driveFolderLink = $request->input('drive_folder_link');
            $scrapeImages = $request->boolean('scrape_images');
            $scrapeVideos = $request->boolean('scrape_videos');
            $createFolders = $request->boolean('create_folders');

            $folderId = $this->googleDriveService->extractFolderIdFromLink($driveFolderLink);

            $scrapedContent = $this->redditScraper->scrape($redditUrl, [
                'minimum_upvotes' => $minimumUpvotes,
                'keywords' => $keywords,
                'scrape_images' => $scrapeImages,
                'scrape_videos' => $scrapeVideos,
            ]);

            if (empty($scrapedContent)) {
                return response()->json(['message' => 'No content found matching the criteria.'], 404);
            }

            $uploadedFiles = [];
            $totalFileSize = 0;

            foreach ($scrapedContent as $content) {
                // Check if the content meets the minimum upvote requirement
                if ($content['upvotes'] < $minimumUpvotes) {
                    continue; // Skip to the next content if it doesn't meet the upvote requirement
                }

                $currentFolderId = $folderId;
                if ($createFolders) {
                    $folderName = Str::slug($content['title']);
                    $currentFolderId = $this->googleDriveService->ensureFolderExists($folderName, $folderId);
                }

                $mediaUrls = $content['media_urls'];
                $mediaChunks = array_chunk($mediaUrls, 10); // Process in chunks of 10

                foreach ($mediaChunks as $chunk) {
                    $responses = Http::pool(fn ($pool) => array_map(
                        fn ($url) => $pool->as($url)->withOptions(['timeout' => 60])->retry(3, 200)->get($url),
                        $chunk
                    ));

                    foreach ($responses as $url => $response) {
                        if (!$response->successful()) {
                            Log::error('Failed to download media', ['url' => $url]);
                            continue;
                        }

                        $fileContent = $response->body();
                        $fileSize = strlen($fileContent);
                        $totalFileSize += $fileSize;

                        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                        $tempFile = tempnam(sys_get_temp_dir(), 'reddit_');
                        file_put_contents($tempFile, $fileContent);

                        $safeTitle = Str::slug($content['title']);
                        $fileName = substr($safeTitle, 0, 100) . '_' . Str::random(8) . '.' . $extension;

                        $uploadResult = $this->googleDriveService->uploadFileFromLocalPath($tempFile, $fileName, $currentFolderId);

                        @unlink($tempFile);

                        if (!empty($uploadResult['file_id'])) {
                            $uploadedFiles[] = [
                                'title' => $content['title'],
                                'original_url' => $url,
                                'drive_url' => $uploadResult['url'],
                                'file_id' => $uploadResult['file_id'],
                                'upvotes' => $content['upvotes'],
                            ];
                        }
                    }
                }
            }

            return response()->json([
                'message' => 'Scraping and uploading completed successfully!',
                'uploaded_files' => $uploadedFiles,
                'total_file_size' => $totalFileSize,
            ]);
        } catch (\Exception $e) {
            Log::error('Scraping and Upload Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
