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
                $currentFolderId = $folderId;
                if ($createFolders) {
                    $folderName = Str::slug($content['title']);
                    $currentFolderId = $this->googleDriveService->ensureFolderExists($folderName, $folderId);
                }

                foreach ($content['media_urls'] as $mediaUrl) {
                    try {
                        // Validate media URL
                        if (!filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
                            Log::warning('Invalid media URL skipped', ['url' => $mediaUrl]);
                            continue;
                        }

                        // Log the media URL
                        Log::info('Processing media URL: ' . $mediaUrl);

                        // Download media with retry logic
                        $response = Http::withOptions(['timeout' => 60])->retry(3, 100)->get($mediaUrl);
                        if (!$response->successful()) {
                            Log::error('Failed to download media', ['url' => $mediaUrl, 'status' => $response->status()]);
                            continue;
                        }

                        $fileContent = $response->body();
                        $fileSize = strlen($fileContent);
                        $totalFileSize += $fileSize;

                        // Determine file extension dynamically
                        $extension = pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
                        if (empty($extension)) {
                            $mimeType = $response->header('Content-Type');
                            $extension = $this->getExtensionFromMimeType($mimeType);
                        }
                        $extension = $extension ?: 'jpg';

                        // Temporary file storage
                        $tempFile = tempnam(sys_get_temp_dir(), 'reddit_');
                        file_put_contents($tempFile, $fileContent);

                        // Safe filename generation
                        $safeTitle = Str::slug($content['title']);
                        $fileName = substr($safeTitle, 0, 100) . '_' . Str::random(8) . '.' . $extension;

                        Log::info('Uploading file to Google Drive', ['fileName' => $fileName, 'fileSize' => $fileSize]);

                        $uploadResult = $this->googleDriveService->uploadFileFromLocalPath($tempFile, $fileName, $currentFolderId);

                        @unlink($tempFile);

                        if (!empty($uploadResult['file_id'])) {
                            $uploadedFiles[] = [
                                'title' => $content['title'],
                                'original_url' => $mediaUrl,
                                'drive_url' => $uploadResult['url'],
                                'file_id' => $uploadResult['file_id'],
                                'upvotes' => $content['upvotes'],
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to process or upload media', [
                            'url' => $mediaUrl,
                            'error' => $e->getMessage(),
                        ]);
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

    private function getExtensionFromMimeType($mimeType)
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',

        ];
        return $mimeMap[$mimeType] ?? null;
    }
}
