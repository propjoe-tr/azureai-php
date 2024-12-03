<?php
// code_file_handler.php
class CodeFileHandler {
    private $allowedTypes = ['php', 'html', 'css', 'js', 'sql', 'xml', 'json', 'txt'];
    private $maxFileSize = 1048576; // 1MB
    
    public function processFile($file) {
        try {
            $this->validateFile($file);
            
            // Dosya içeriğini oku
            $content = file_get_contents($file['tmp_name']);
            
            // Dosya içeriğini güvenli hale getir
            $content = htmlspecialchars($content);
            
            return [
                'success' => true,
                'fileName' => basename($file['name']),
                'content' => $content,
                'type' => pathinfo($file['name'], PATHINFO_EXTENSION)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Dosya yükleme hatası');
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('Dosya boyutu çok büyük. Maximum 1MB.');
        }

        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $this->allowedTypes)) {
            throw new Exception('Bu dosya türü desteklenmiyor. Desteklenen türler: ' . implode(', ', $this->allowedTypes));
        }
    }
}
?>