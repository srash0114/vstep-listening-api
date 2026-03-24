<?php
use Appwrite\Client;
use Appwrite\Services\Storage;
use Appwrite\InputFile;
use Appwrite\ID;
use Appwrite\Permission;
use Appwrite\Role;

define('APPWRITE_ENDPOINT',   getenv('APPWRITE_ENDPOINT')   ?: 'https://sgp.cloud.appwrite.io/v1');
define('APPWRITE_PROJECT_ID', getenv('APPWRITE_PROJECT_ID') ?: '');
define('APPWRITE_API_KEY',    getenv('APPWRITE_API_KEY')    ?: '');
define('APPWRITE_BUCKET_ID',  getenv('APPWRITE_BUCKET_ID')  ?: '');

class AppwriteAudioUpload {

    private static function client(): Client {
        $client = new Client();
        $client
            ->setEndpoint(APPWRITE_ENDPOINT)
            ->setProject(APPWRITE_PROJECT_ID)
            ->setKey(APPWRITE_API_KEY);
        return $client;
    }

    /**
     * Upload audio file to Appwrite Storage
     */
    public static function uploadAudio(array $file): array {
        if (!isset($file['tmp_name']) || !isset($file['name']) || !isset($file['type'])) {
            return ['success' => false, 'error' => 'Invalid file'];
        }

        if ($file['size'] > 200 * 1024 * 1024) {
            return ['success' => false, 'error' => 'File exceeds maximum size (200MB)'];
        }

        $allowedTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/webm'];
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid audio format. Allowed: mp3, wav, ogg, webm'];
        }

        try {
            $storage = new Storage(self::client());

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'audio_' . uniqid() . '.' . $ext;

            $result = $storage->createFile(
                APPWRITE_BUCKET_ID,
                ID::unique(),
                InputFile::withPath($file['tmp_name'], $file['type'], $filename),
                [Permission::read(Role::any())]
            );

            $fileId = $result['$id'];
            $url = APPWRITE_ENDPOINT . '/storage/buckets/' . APPWRITE_BUCKET_ID . '/files/' . $fileId . '/view?project=' . APPWRITE_PROJECT_ID;

            return [
                'success' => true,
                'file_id' => $fileId,
                'path'    => $fileId,   // store fileId as "path" for deletion
                'url'     => $url,
                'size'    => $file['size']
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()];
        }
    }

    /**
     * Delete audio file from Appwrite Storage
     * $path is the fileId stored during upload
     */
    public static function deleteAudio(string $path): bool {
        if (empty($path)) return false;

        try {
            $storage = new Storage(self::client());
            $storage->deleteFile(APPWRITE_BUCKET_ID, $path);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
