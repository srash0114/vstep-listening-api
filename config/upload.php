<?php
// Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../public/uploads');
define('AUDIO_UPLOAD_DIR', UPLOAD_DIR . '/audio');
define('MAX_AUDIO_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_AUDIO_TYPES', ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/webm']);
define('AUDIO_BASE_URL', '/uploads/audio');

// File Upload Handler Class
class FileUpload {
    
    /**
     * Upload audio file for a question
     */
    public static function uploadAudio($file) {
        // Validate file
        if (!isset($file['tmp_name']) || !isset($file['name']) || !isset($file['type'])) {
            return ['success' => false, 'error' => 'Invalid file'];
        }
        
        // Check file size
        if ($file['size'] > MAX_AUDIO_SIZE) {
            return ['success' => false, 'error' => 'File exceeds maximum size (10MB)'];
        }
        
        // Check file type
        if (!in_array($file['type'], ALLOWED_AUDIO_TYPES)) {
            return ['success' => false, 'error' => 'Invalid audio format. Allowed: mp3, wav, ogg, webm'];
        }
        
        // Create uploads directory if not exists
        if (!is_dir(AUDIO_UPLOAD_DIR)) {
            mkdir(AUDIO_UPLOAD_DIR, 0755, true);
        }
        
        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'audio_' . uniqid() . '.' . $ext;
        $filepath = AUDIO_UPLOAD_DIR . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $filepath,
                'url' => AUDIO_BASE_URL . '/' . $filename
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to upload file'];
    }
    
    /**
     * Delete audio file
     */
    public static function deleteAudio($filename) {
        if (empty($filename)) {
            return false;
        }
        
        $filepath = AUDIO_UPLOAD_DIR . '/' . basename($filename);
        
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        
        return false;
    }
}
?>
