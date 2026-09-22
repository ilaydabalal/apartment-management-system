<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ob_clean();

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../config/system_logger.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Sadece POST isteği kabul edilir', 405);
    }

    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (strpos($authHeader, 'Bearer ') !== 0) {
        Response::error('Geçersiz authorization header', 401);
    }
    
    $token = substr($authHeader, 7);
    
    $auth = new Auth();
    $user = $auth->validateToken($token);
    
    if (!$user) {
        Response::error('Geçersiz veya süresi dolmuş token', 401);
    }

    $sessionDuration = null;
    try {
        $tokenParts = explode('.', $token);
        if (count($tokenParts) == 3) {
            $payload = json_decode(base64_decode($tokenParts[1]), true);
            $iat = $payload['iat'] ?? null;
            if ($iat) {
                $sessionDuration = time() - $iat;
            }
        }
    } catch (Exception $e) {
    }

$db = Database::getInstance();
$pdo = $db->getConnection();

$stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
$stmt->execute([$user['user_id']]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$username = $userInfo['username'] ?? 'Bilinmiyor';
$userFullName = $userInfo['full_name'] ?? $username;

$logResult = logUserLogout($pdo, $user['user_id'], $username, $userFullName, $sessionDuration);

    Response::success([
        'message' => 'Çıkış başarılı',
        'log_result' => $logResult,
        'session_duration' => $sessionDuration ? formatSessionDuration($sessionDuration) : 'Hesaplanamadı',
        'user' => $user['username']
    ], 'Çıkış başarılı');

} catch (Exception $e) {
    error_log("Logout hatası: " . $e->getMessage());
    Response::error('Sunucu hatası: ' . $e->getMessage());
}

ob_end_clean();
?>