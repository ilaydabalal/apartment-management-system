<?php
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../core/auth.php');
define('SUPPORT_UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/apartment-management/uploads/support/');
define('SUPPORT_UPLOAD_URL', '/apartment-management/uploads/support/');
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5 * 1024 * 1024);
}define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
define('MAX_PHOTOS_PER_TICKET', 5);

class SupportHelper {
    
    public static function validateTicketData($data, $isUpdate = false) {
        $errors = [];
        
        if (!$isUpdate) {
            if (empty($data['title'])) {
                $errors[] = 'Başlık alanı zorunludur';
            }
            
            if (empty($data['description'])) {
                $errors[] = 'Açıklama alanı zorunludur';
            }
            
            if (empty($data['category'])) {
                $errors[] = 'Kategori seçimi zorunludur';
            }
        }
        
        if (isset($data['title']) && strlen($data['title']) > 255) {
            $errors[] = 'Başlık 255 karakterden uzun olamaz';
        }
        
        if (isset($data['category'])) {
            $validCategories = ['elektrik', 'su_tesisati', 'isi_sogutucu', 'asansor', 'guvenlik', 'temizlik', 'ortak_alan', 'diger'];
            if (!in_array($data['category'], $validCategories)) {
                $errors[] = 'Geçersiz kategori seçimi';
            }
        }
        
        if (isset($data['priority'])) {
            $validPriorities = ['dusuk', 'orta', 'yuksek', 'acil'];
            if (!in_array($data['priority'], $validPriorities)) {
                $errors[] = 'Geçersiz öncelik seçimi';
            }
        }
        
        if (isset($data['status'])) {
            $validStatuses = ['acik', 'inceleniyor', 'islemde', 'beklemede', 'cozuldu', 'iptal'];
            if (!in_array($data['status'], $validStatuses)) {
                $errors[] = 'Geçersiz durum seçimi';
            }
        }
        
        return $errors;
    }
    
    public static function createUploadDirectory($ticketNumber) {
        $year = date('Y');
        $month = date('m');
        $uploadPath = SUPPORT_UPLOAD_DIR . $year . '/' . $month . '/';
        
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        return $uploadPath;
    }
    
    public static function generateFileName($ticketNumber, $originalName, $index = 1) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return $ticketNumber . '_' . $index . '.' . $extension;
    }
    
    public static function validateUploadedFile($file) {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Dosya yüklenirken hata oluştu';
            return $errors;
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'Dosya boyutu çok büyük (maksimum 5MB)';
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            $errors[] = 'Desteklenmeyen dosya formatı (sadece JPG, PNG, WebP)';
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = 'Geçersiz dosya türü';
        }
        
        return $errors;
    }
    
    public static function logTicketHistory($pdo, $ticketId, $userId, $actionType, $oldValue = null, $newValue = null, $comment = null) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO support_ticket_history 
                (ticket_id, user_id, action_type, old_value, new_value, comment) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $ticketId,
                $userId,
                $actionType,
                $oldValue,
                $newValue,
                $comment
            ]);
        } catch (Exception $e) {
            error_log("History log error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function getActionText($actionType, $oldValue = null, $newValue = null) {
        $texts = [
            'created' => 'Talep oluşturuldu',
            'status_changed' => 'Durum değiştirildi: ' . ($oldValue ?? '') . ' → ' . ($newValue ?? ''),
            'assigned' => 'Talep atandı',
            'comment_added' => 'Yorum eklendi',
            'photo_added' => 'Fotoğraf eklendi',
            'resolved' => 'Talep çözüldü',
            'reopened' => 'Talep yeniden açıldı'
        ];
        
        return $texts[$actionType] ?? $actionType;
    }
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}
?>