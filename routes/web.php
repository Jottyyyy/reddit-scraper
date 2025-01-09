<?php

use Illuminate\Support\Facades\Route;
use App\Services\GoogleDriveService;
use App\Http\Controllers\ScraperController;


// ✅ Scraper View Route
Route::get('/', function () {
    return view('scraper');
});

// ✅ Test Google Drive Upload Route
Route::get('/test-google-drive', function (GoogleDriveService $driveService) {
    try {
        $filePath = storage_path('app/test-file.txt');
        file_put_contents($filePath, 'Test Google Drive Upload');

        $fileInfo = $driveService->uploadFileFromLocalPath($filePath, 'test-file.txt', env('GOOGLE_DRIVE_FOLDER_ID'));
        unlink($filePath); // Clean up local file after upload

        return response()->json([
            'message' => 'File uploaded successfully!',
            'file_info' => $fileInfo
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
});

// ✅ Scraper Routes
Route::post('/scrape-and-upload', [ScraperController::class, 'scrapeAndUpload'])->name('scrape-and-upload');



// ✅ Pause, Resume, and Stop Routes
Route::post('/scraper/pause', function () {
    session()->put('isPaused', true);
    return response()->json(['message' => 'Scraping paused.']);
});

Route::post('/scraper/resume', function () {
    session()->put('isPaused', false);
    return response()->json(['message' => 'Scraping resumed.']);
});

Route::post('/scraper/stop', function () {
    session()->put('isStopped', true);
    return response()->json(['message' => 'Scraping stopped.']);
});
