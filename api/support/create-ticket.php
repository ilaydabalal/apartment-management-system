<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Authorization, Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__.'/../config.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../config/system_logger.php';

$response = array('success' => false, 'message' => '');

try {
    $auth = new Auth();
    $token = $auth->getTokenFromRequest();
    if(!$token || !$auth->validateToken($token)) {
        $response['message'] = 'Yetkilendirme hatası';
        echo json_encode($response);
        exit;
    }

    $payload = $auth->validateToken($token);
    $user_id = $payload['user_id'] ?? null;

    if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
        $data = $_POST;
    } else {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
    }
    
    $requiredFields = ['title', 'description', 'category', 'priority', 'location'];
    foreach($requiredFields as $field) {
        if(empty($data[$field])) {
            $response['message'] = "$field alanı boş bırakılamaz";
            echo json_encode($response);
            exit;
        }
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $username = $userInfo['username'] ?? 'Bilinmiyor';
    $userFullName = $userInfo['full_name'] ?? $username;

    $year = date('Y');
    $stmt = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE YEAR(created_at) = $year");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $ticketNumber = 'TKT' . $year . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("INSERT INTO support_tickets 
        (ticket_number, title, description, category, priority, status, location, user_id, requester_name, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    
    $status = 'acik';
    $requester_name = $data['requester_name'] ?? '';
    
    $stmt->execute([
        $ticketNumber,
        $data['title'],
        $data['description'],
        $data['category'],
        $data['priority'],
        $status,
        $data['location'],
        $user_id,
        $requester_name
    ]);
    
    $ticketId = $conn->lastInsertId();

    $ticketData = [
        'title' => $data['title'],
        'description' => $data['description'],
        'category' => $data['category'],
        'priority' => $data['priority'],
        'status' => $status,
        'location' => $data['location'],
        'requester_name' => $requester_name
    ];
    
    $logResult = logSupportTicketCreated(
        $conn, 
        $user_id, 
        $username, 
        $userFullName, 
        $ticketId, 
        $ticketNumber, 
        $ticketData
    );

    $uploaded_photos = [];
    if (isset($_FILES['photos'])) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/apartment-management/uploads/tickets/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if (is_array($_FILES['photos']['name'])) {
            $file_count = count($_FILES['photos']['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['photos']['error'][$i] == UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['photos']['tmp_name'][$i];
                    $original_name = $_FILES['photos']['name'][$i];
                    $file_size = $_FILES['photos']['size'][$i];
                    
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $file_type = mime_content_type($tmp_name);
                    
                    if (!in_array($file_type, $allowed_types)) continue;
                    if ($file_size > 5 * 1024 * 1024) continue;
                    
                    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
                    $new_filename = $ticketId . '_' . uniqid() . '.' . $extension;
                    $full_path = $upload_dir . $new_filename;
                    $db_path = 'uploads/tickets/' . $new_filename;
                    
                    if (move_uploaded_file($tmp_name, $full_path)) {
                        $photoStmt = $conn->prepare("INSERT INTO support_ticket_photos (ticket_id, photo_path, original_name, file_size, mime_type, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $photoStmt->execute([$ticketId, $db_path, $original_name, $file_size, $file_type]);
                        
                        $uploaded_photos[] = [
                            'url' => '/apartment-management/' . $db_path,
                            'path' => $db_path,
                            'original_name' => $original_name
                        ];
                    }
                }
            }
        }
    }

    $response['success'] = true;
    $response['message'] = 'Talep başarıyla oluşturuldu';
    $response['data'] = [
        'ticket_id' => $ticketId,
        'ticket_number' => $ticketNumber,
        'photos_uploaded' => count($uploaded_photos),
        'log_result' => $logResult
    ];

    error_log("✅ Destek talebi oluşturuldu ve loglandı: $ticketNumber by $username");

} catch(Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Hata: ' . $e->getMessage();
    error_log("❌ Destek talebi oluşturma hatası: " . $e->getMessage());
}

echo json_encode($response);
?>