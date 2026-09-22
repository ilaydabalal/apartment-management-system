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

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!$data && !empty($_POST)) {
        $data = $_POST;
    }
    if (!$data) {
        Response::error('Geçersiz JSON formatı');
    }
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    if (empty($username) || empty($password)) {
        Response::error('Kullanıcı adı ve şifre gereklidir');
    }

    $auth = new Auth();
    $user = $auth->authenticate($username, $password);
    if (!$user) {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        logFailedLogin($pdo, $username, 'Kullanıcı adı veya şifre hatalı');
        
        Response::error('Kullanıcı adı veya şifre hatalı', 401);
    }
$token = $auth->generateToken($user['id'], [
    'username' => $user['username'],
    'role_name' => $user['role_name']
 ]);
 
 $db = Database::getInstance();
 $pdo = $db->getConnection();
 $userFullName = isset($user['full_name']) ? $user['full_name'] : $user['username'];
 logUserLogin($pdo, $user['id'], $user['username'], $userFullName);
 
 Response::success([
    'token' => $token,
    'user' => $user,
    'expires_in' => 86400
 ], 'Giriş başarılı');
 
 } catch (Exception $e) {
    Response::error('Sunucu hatası: ' . $e->getMessage());
 }
ob_end_clean();

?>