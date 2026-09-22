<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../config/database.php';
require_once 'core/auth.php';
require_once 'config/system_logger.php';

$id = $_POST['id'] ?? null;
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';

if (empty($name) || empty($email) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Tüm alanlar zorunludur.']);
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

    $logResult = false;
    
    if ($id) {
       
        $stmt = $pdo->prepare("SELECT name, email, phone FROM support_technicians WHERE id = ? AND status = 1");
        $stmt->execute([$id]);
        $oldTechnician = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldTechnician) {
            echo json_encode(['success' => false, 'message' => 'Güncellenecek teknisyen bulunamadı.']);
            exit;
        }
        
        $changes = [];
        if ($oldTechnician['name'] !== $name) {
            $changes['İsim'] = ['old' => $oldTechnician['name'], 'new' => $name];
        }
        if ($oldTechnician['email'] !== $email) {
            $changes['Email'] = ['old' => $oldTechnician['email'], 'new' => $email];
        }
        if ($oldTechnician['phone'] !== $phone) {
            $changes['Telefon'] = ['old' => $oldTechnician['phone'], 'new' => $phone];
        }
        
        $stmt = $pdo->prepare("UPDATE support_technicians SET name = ?, email = ?, phone = ? WHERE id = ? AND status = 1");
        $stmt->execute([$name, $email, $phone, $id]);
        
        $message = 'Teknisyen başarıyla güncellendi.';
        
        if (!empty($changes)) {
            $logResult = logTechnicianUpdated(
                $pdo,
                $user_id,
                $username,
                $userFullName,
                $id,
                $oldTechnician,
                ['name' => $name, 'email' => $email, 'phone' => $phone],
                $changes
            );
            
            error_log("✅ Teknisyen güncellendi ve loglandı: $name by $userFullName");
        }
        
    } else {
        $stmt = $pdo->prepare("INSERT INTO support_technicians (name, email, phone, status) VALUES (?, ?, ?, 1)");
        $stmt->execute([$name, $email, $phone]);
        
        $technicianId = $pdo->lastInsertId();
        $message = 'Teknisyen başarıyla eklendi.';
        
        $technicianData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone
        ];
        
        $logResult = logTechnicianCreated(
            $pdo,
            $user_id,
            $username,
            $userFullName,
            $technicianId,
            $technicianData
        );
        
        error_log("✅ Teknisyen eklendi ve loglandı: $name by $userFullName");
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'log_result' => $logResult,
        'performed_by' => $userFullName
    ]);
    
} catch (Exception $e) {
    error_log("❌ Teknisyen kaydetme hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Hata oluştu: ' . $e->getMessage()]);
}
?>