<?php
namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    protected $client;
    protected $driveService;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(env('GOOGLE_DRIVE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_DRIVE_CLIENT_SECRET'));
        $this->client->setRedirectUri(env('GOOGLE_DRIVE_REDIRECT_URI'));
        $this->client->setAccessType('offline');
        $this->client->setScopes(['https://www.googleapis.com/auth/drive.file']);

        $this->client->refreshToken(env('GOOGLE_DRIVE_REFRESH_TOKEN'));
        $this->driveService = new Drive($this->client);
    }

    /**
     * Ensure a folder exists on Google Drive; create it if it doesn't.
     */
    public function ensureFolderExists($folderName, $parentFolderId = null)
    {
        // Search for existing folder
        $query = "mimeType='application/vnd.google-apps.folder' and name='{$folderName}'";
        if ($parentFolderId) {
            $query .= " and '{$parentFolderId}' in parents";
        }

        $folders = $this->driveService->files->listFiles(['q' => $query, 'fields' => 'files(id, name)']);
        if (!empty($folders->getFiles())) {
            return $folders->getFiles()[0]->getId();
        }

        // Create a new folder if it doesn't exist
        $fileMetadata = new DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => $parentFolderId ? [$parentFolderId] : [],
        ]);

        $folder = $this->driveService->files->create($fileMetadata, ['fields' => 'id']);
        return $folder->id;
    }

    /**
     * Upload file to a specific Google Drive folder.
     */
    public function uploadFileFromLocalPath($filePath, $fileName, $folderId)
    {
        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
        ]);

        $fileContent = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        $file = $this->driveService->files->create($fileMetadata, [
            'data' => $fileContent,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id, name',
        ]);

        return [
            'file_id' => $file->id,
            'file_name' => $file->name,
            'url' => "https://drive.google.com/file/d/{$file->id}/view",
        ];
    }

    public function extractFolderIdFromLink($link)
    {
        preg_match('/\/folders\/([a-zA-Z0-9-_]+)/', $link, $matches);
        if (isset($matches[1])) {
            return $matches[1];
        }

        throw new \Exception('Invalid Google Drive folder link.');
    }
}
