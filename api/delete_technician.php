<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../config/database.php';
require_once 'core/auth.php';
require_once 'config/system_logger.php';

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Teknisyen ID gereklidir.']);
    exit;
}

try {
    $user_id = null;
    $username = 'Bilinmiyor';
    $userFullName = 'Bilinmiyor';

    $token = null;
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
        
        try {
            $tokenParts = explode('.', $token);
            if (count($tokenParts) == 3) {
                $payload = json_decode(base64_decode($tokenParts[1]), true);
                $user_id = $payload['user_id'] ?? null;
            }
        } catch (Exception $e) {
            error_log("Token parse hatası: " . $e->getMessage());
        }
    }

    $db = Database::getInstance();
    $pdo = $db->getConnection();

    if ($user_id) {
        $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userInfo) {
            $username = $userInfo['username'];
            $userFullName = $userInfo['full_name'] ?? $username;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı. Lütfen tekrar giriş yapın.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT name, email, phone FROM support_technicians WHERE id = ? AND status = 1");
    $stmt->execute([$id]);
    $technician = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$technician) {
        echo json_encode(['success' => false, 'message' => 'Kaldırılacak teknisyen bulunamadı.']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE support_technicians SET status = 0 WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        $logResult = logTechnicianDeleted(
            $pdo,
            $user_id,
            $username,
            $userFullName,
            $id,
            $technician
        );
        
        error_log("✅ Teknisyen kaldırıldı ve loglandı: {$technician['name']} by $userFullName");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Teknisyen başarıyla kaldırıldı.',
            'log_result' => $logResult,
            'deleted_by' => $userFullName,
            'deleted_technician' => $technician['name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Teknisyen kaldırılamadı.']);
    }
    
} catch (Exception $e) {
    error_log("❌ Teknisyen silme hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Hata oluştu: ' . $e->getMessage()]);
}
?>