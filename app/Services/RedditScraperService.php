<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RedditScraperService
{
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Scrape Reddit posts from a given subreddit URL with optional filters.
     *
     * @param string $url
     * @param array $filters
     * @return array
     */
    public function scrape($url, $filters = [])
    {
        try {
            // Parse and format the URL properly
            $parsedUrl = $this->parseRedditUrl($url);
            if (!$parsedUrl) {
                Log::error('Invalid Reddit URL format');
                return [];
            }

            Log::info('Fetching Reddit URL: ' . $parsedUrl);

            // Make the request with proper headers
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
                'Accept' => 'application/json'
            ])->get($parsedUrl);

            if (!$response->successful()) {
                Log::error('Reddit API Request Failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $data = $response->json();

            // Handle the listing data structure
            $posts = $data['data']['children'] ?? [];

            if (empty($posts)) {
                Log::warning('No posts found in Reddit response');
                return [];
            }

            $results = [];

            foreach ($posts as $post) {
                $postData = $post['data'] ?? [];

                // Skip if essential fields are missing
                if (empty($postData['title'])) {
                    continue;
                }

                $upvotes = $postData['ups'] ?? 0;

                // Apply minimum upvotes filter
                if (isset($filters['minimum_upvotes']) && $upvotes < intval($filters['minimum_upvotes'])) {
                    continue;
                }

                // Apply keyword filters to the title
                if (!empty($filters['keywords'])) {
                    $matched = false;
                    foreach ($filters['keywords'] as $keyword) {
                        $keyword = trim($keyword);
                        if (!empty($keyword) && stripos($postData['title'], $keyword) !== false) {
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched) {
                        continue;
                    }
                }

                // Extract media URLs
                $mediaUrls = $this->extractMediaUrls($postData, $filters);

                if (!empty($mediaUrls)) {
                    $results[] = [
                        'title' => $postData['title'],
                        'author' => $postData['author'] ?? 'unknown',
                        'upvotes' => $upvotes,
                        'url' => $postData['url'] ?? '',
                        'permalink' => 'https://reddit.com' . ($postData['permalink'] ?? ''),
                        'media_urls' => $mediaUrls
                    ];
                }
            }

            Log::info('Successfully scraped posts', ['count' => count($results)]);
            return $results;

        } catch (\Exception $e) {
            Log::error('RedditScraperService Error: ' . $e->getMessage(), [
                'url' => $url,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Parse and format Reddit URL properly
     *
     * @param string $url
     * @return string|null
     */
    private function parseRedditUrl($url)
    {
        try {
            // Remove .json if present
            $url = str_replace('.json', '', $url);

            // Parse URL
            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? '';

            // Extract subreddit name
            preg_match('/\/r\/([^\/]+)/', $path, $matches);
            if (empty($matches[1])) {
                return null;
            }

            $subreddit = $matches[1];

            // Check if it's a "top" request
            $isTop = strpos($path, '/top') !== false;

            // Build the proper URL
            $baseUrl = "https://www.reddit.com/r/{$subreddit}";

            if ($isTop) {
                // Add sort and time parameters
                $baseUrl .= '/top.json?t=day&limit=100';
            } else {
                $baseUrl .= '.json?limit=100';
            }

            return $baseUrl;

        } catch (\Exception $e) {
            Log::error('URL parsing error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract media URLs from post data
     *
     * @param array $postData
     * @param array $filters
     * @return array
     */
    private function extractMediaUrls($postData, $filters)
    {
        $mediaUrls = [];

        try {
            // Handle gallery posts
            if (!empty($postData['is_gallery']) && !empty($postData['media_metadata'])) {
                foreach ($postData['media_metadata'] as $key => $media) {
                    if (!empty($media['s']['u'])) {
                        $url = htmlspecialchars_decode($media['s']['u']);
                        // Convert preview URLs to full-resolution URLs
                        $url = str_replace('preview.redd.it', 'i.redd.it', $url);
                        $url = preg_replace('/\?.*$/', '', $url);
                        if ($this->shouldIncludeMedia($url, $filters)) {
                            $mediaUrls[] = $url;
                        }
                    }
                }
            }
            // Handle single image posts
            elseif ($this->isImageUrl($postData['url'] ?? '')) {
                if ($this->shouldIncludeMedia($postData['url'], $filters)) {
                    $mediaUrls[] = $postData['url'];
                }
            }
            // Handle videos
            elseif (!empty($postData['is_video']) && !empty($postData['media']['reddit_video']['fallback_url'])) {
                if ($this->shouldIncludeMedia($postData['media']['reddit_video']['fallback_url'], $filters)) {
                    $mediaUrls[] = $postData['media']['reddit_video']['fallback_url'];
                }
            }
            // Handle external images
            elseif (!empty($postData['preview']['images'][0]['source']['url'])) {
                $url = htmlspecialchars_decode($postData['preview']['images'][0]['source']['url']);
                $url = preg_replace('/\?.*$/', '', $url);
                if ($this->shouldIncludeMedia($url, $filters)) {
                    $mediaUrls[] = $url;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error extracting media URLs: ' . $e->getMessage(), [
                'post_title' => $postData['title'] ?? 'unknown'
            ]);
        }

        return array_values(array_unique($mediaUrls));
    }

    /**
     * Check if a URL is an image URL
     *
     * @param string $url
     * @return bool
     */
    private function isImageUrl($url)
    {
        if (empty($url)) {
            return false;
        }

        // Check common image extensions
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
            return true;
        }

        // Check if it's a Reddit image URL
        if (strpos($url, 'i.redd.it') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a media URL should be included based on filters
     *
     * @param string $url
     * @param array $filters
     * @return bool
     */
    private function shouldIncludeMedia($url, $filters)
    {
        if (empty($url)) {
            return false;
        }

        $isImage = $this->isImageUrl($url);
        $isVideo = !$isImage;

        if ($isImage && !empty($filters['scrape_images'])) {
            return true;
        }

        if ($isVideo && !empty($filters['scrape_videos'])) {
            return true;
        }

        return false;
    }
}
