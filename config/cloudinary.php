<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Api\Admin\AdminApi;

class CloudinaryAudioUpload {

    private static function config(): void {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => getenv('CLOUDINARY_API_KEY'),
                'api_secret' => getenv('CLOUDINARY_API_SECRET'),
            ],
            'url' => ['secure' => true]
        ]);
    }

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
            self::config();
            $publicId = 'audio_' . uniqid();

            $result = (new UploadApi())->upload($file['tmp_name'], [
                'resource_type' => 'video',
                'public_id'     => $publicId,
                'folder'        => 'vstep_audio',
            ]);

            return [
                'success' => true,
                'file_id' => $result['public_id'],
                'path'    => $result['public_id'],
                'url'     => $result['secure_url'],
                'size'    => $file['size']
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()];
        }
    }

    public static function deleteAudio(string $path): bool {
        if (empty($path)) return false;

        try {
            self::config();
            (new AdminApi())->deleteAssets([$path], ['resource_type' => 'video']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
