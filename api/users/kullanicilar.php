<?php

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../core/database.php';
require_once '../core/auth.php';
require_once '../core/response.php';
require_once '../config/database.php'; 

function getCurrentUserId() {
    try {
        $auth = new Auth();
        $user = $auth->getCurrentUser();
        
        if ($user && isset($user['id'])) {
            return $user['id'];
        }
        
        return 1;
        
    } catch (Exception $e) {
        error_log("getCurrentUserId hatası: " . $e->getMessage());
        return 1;
    }
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            Response::error('Desteklenmeyen HTTP metodu', 405);
    }
} catch (Exception $e) {
    error_log('Users API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    Response::serverError('Bir hata oluştu: ' . $e->getMessage());
}

function handleGet() {
    global $db;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $search = $_GET['search'] ?? '';
    $roleId = $_GET['role_id'] ?? '';
    $status = isset($_GET['status']) ? (int)$_GET['status'] : 1;
    
    $page = max(1, $page);
    $limit = min(100, max(1, $limit));
    $offset = ($page - 1) * $limit;
    
    if (isset($_GET['id'])) {
        $userId = (int)$_GET['id'];
        $sql = "SELECT u.id, u.username, u.email, u.full_name, u.phone, 
                       u.role_id, u.last_login, u.status, u.created_at, u.updated_at,
                       r.name as role_name
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ? AND u.status >= ?";
        
        $user = $db->fetch($sql, [$userId, $status]);
        
        if (!$user) {
            Response::notFound('Kullanıcı bulunamadı');
        }
        
        Response::success($user);
    }
    
    $whereClauses = ["u.status >= ?"];
    $params = [$status];
    
    if (!empty($search)) {
        $whereClauses[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR r.name LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($roleId)) {
        $whereClauses[] = "u.role_id = ?";
        $params[] = $roleId;
    }
    
    $whereClause = implode(' AND ', $whereClauses);
    
    $countSql = "SELECT COUNT(*) as total FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE {$whereClause}";
    $totalResult = $db->fetch($countSql, $params);
    $total = $totalResult['total'];
    
    $sql = "SELECT u.id, u.username, u.email, u.full_name, u.phone, 
                   u.role_id, u.last_login, u.status, u.created_at, u.updated_at,
                   r.name as role_name
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE {$whereClause}
            ORDER BY 
                CASE r.name
                    WHEN 'Süper Admin' THEN 1
                    WHEN 'Muhasebeci' THEN 2
                    WHEN 'Apartman Yöneticisi' THEN 3
                    WHEN 'Apartman Görevlisi' THEN 4
                    WHEN 'Daire Sakini' THEN 5
                    ELSE 99
                END,
                u.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    
    try {
        $users = $db->fetchAll($sql, $params);
    } catch (Exception $e) {
        error_log('SQL ERROR: ' . $e->getMessage());
        error_log('SQL: ' . $sql);
        error_log('PARAMS: ' . print_r($params, true));
        Response::serverError('SQL Hatası: ' . $e->getMessage());
    }
    
    Response::paginated($users, $total, $page, $limit);
}

function handlePost() {
    global $db, $pdo;
    
    try {
        $json = file_get_contents('php://input');
        error_log('Raw POST data: ' . $json);
        
        if (empty($json)) {
            Response::error('POST verisi bulunamadı');
        }
        
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            Response::error('Geçersiz JSON formatı: ' . json_last_error_msg());
        }
        
        error_log('POST Data received: ' . print_r($data, true));
        
        if (!$data) {
            Response::error('Geçersiz veri formatı');
        }
        
        if (empty($data['username'])) {
            if (!empty($data['email'])) {
                $data['username'] = explode('@', $data['email'])[0];
                $data['username'] = strtolower(str_replace(
                    ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'I', 'İ', 'Ö', 'Ş', 'Ü'],
                    ['c', 'g', 'i', 'o', 's', 'u', 'c', 'g', 'i', 'i', 'o', 's', 'u'],
                    $data['username']
                ));
                $data['username'] = preg_replace('/[^a-z0-9_]/', '', $data['username']);
            } else {
                Response::error('Username veya email gereklidir');
            }
        }
        
        $errors = validateUserData($data);
        if (!empty($errors)) {
            error_log('Validation errors: ' . print_r($errors, true));
            Response::validationError($errors);
        }
        
        $existingUser = $db->fetch(
            "SELECT id, username, email FROM users WHERE (email = ? OR username = ?) AND status = 1",
            [$data['email'], $data['username']]
        );
        
        if ($existingUser) {
            if ($existingUser['email'] === $data['email']) {
                Response::error('Bu email adresi zaten kullanılıyor', 409);
            }
            if ($existingUser['username'] === $data['username']) {
                $baseUsername = $data['username'];
                $counter = 1;
                do {
                    $data['username'] = $baseUsername . $counter;
                    $usernameCheck = $db->fetch(
                        "SELECT id FROM users WHERE username = ? AND status = 1",
                        [$data['username']]
                    );
                    $counter++;
                } while ($usernameCheck && $counter < 100);
            }
        }
        
        if (empty($data['password'])) {
            Response::error('Şifre gereklidir');
        }
        
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $roleId = null;
        if (!empty($data['role_id']) && $data['role_id'] !== '') {
            $roleId = (int)$data['role_id'];
            $roleExists = $db->fetch("SELECT id FROM roles WHERE id = ? AND status = 1", [$roleId]);
            if (!$roleExists) {
                Response::error('Geçersiz rol seçimi');
            }
        }
        
        $sql = "INSERT INTO users (username, email, password, full_name, phone, role_id, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
        
        $insertParams = [
            $data['username'],
            $data['email'],
            $hashedPassword,
            $data['full_name'],
            $data['phone'] ?? null,
            $roleId
        ];
        
        error_log('Insert SQL: ' . $sql);
        error_log('Insert Params: ' . print_r($insertParams, true));
        
        $userId = $db->insert($sql, $insertParams);

        $currentUserId = isset($data['current_user_id']) ? (int)$data['current_user_id'] : 1;
        logUserInsert($pdo, $currentUserId, $userId);

        if (!$userId) {
            error_log('Insert failed: userId is empty');
            Response::serverError('Kullanıcı eklenemedi - Insert işlemi başarısız');
        }
        
        $newUser = $db->fetch(
            "SELECT u.id, u.username, u.email, u.full_name, u.phone, 
                    u.role_id, u.status, u.created_at, u.updated_at,
                    r.name as role_name
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ?",
            [$userId]
        );
        
        if (!$newUser) {
            error_log('Failed to fetch newly created user');
            Response::serverError('Kullanıcı eklendi ancak bilgiler alınamadı');
        }
        
        Response::success($newUser, 'Kullanıcı başarıyla eklendi', 201);
        
    } catch (Exception $e) {
        error_log('handlePost exception: ' . $e->getMessage());
        error_log('Exception trace: ' . $e->getTraceAsString());
        Response::serverError('Database hatası: ' . $e->getMessage());
    }
}

function handlePut() {
    global $db, $pdo;
    
    try {
        if (!isset($pdo) || $pdo === null) {
            try {
                $host = 'localhost';
                $dbname = 'apartment_management';
                $username = 'root';
                $password = '';
                
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                error_log("PDO bağlantısı oluşturuldu");
            } catch (PDOException $e) {
                error_log("PDO bağlantı hatası: " . $e->getMessage());
                Response::error('Database bağlantı hatası');
            }
        }
        
        require_once '../config/system_logger.php';
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['id'])) {
            Response::error('Geçersiz veri formatı');
        }
        
        $userId = (int)$data['id'];
        
        $existingUser = $db->fetch(
            "SELECT * FROM users WHERE id = ? AND status = 1",
            [$userId]
        );
        
        if (!$existingUser) {
            Response::notFound('Kullanıcı bulunamadı');
        }
        
        $oldData = $existingUser; 

        $errors = validateUserData($data, $userId);
        if (!empty($errors)) {
            Response::validationError($errors);
        }
        
        $duplicateUser = $db->fetch(
            "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? AND status = 1",
            [$data['username'], $data['email'], $userId]
        );
        
        if ($duplicateUser) {
            Response::error('Bu kullanıcı adı veya email zaten kullanılıyor', 409);
        }
        
        $updateFields = [];
        $params = [];
        $passwordChanged = false; 
        
        if (isset($data['username'])) {
            $updateFields[] = "username = ?";
            $params[] = $data['username'];
        }
        
        if (isset($data['email'])) {
            $updateFields[] = "email = ?";
            $params[] = $data['email'];
        }
        
        if (isset($data['full_name'])) {
            $updateFields[] = "full_name = ?";
            $params[] = $data['full_name'];
        }
        
        if (isset($data['phone'])) {
            $updateFields[] = "phone = ?";
            $params[] = $data['phone'];
        }
        
        if (isset($data['role_id'])) {
            $updateFields[] = "role_id = ?";
            $params[] = empty($data['role_id']) ? null : (int)$data['role_id'];
        }
        
        if (!empty($data['password'])) {
            $updateFields[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            $passwordChanged = true; 
        }
        
        $updateFields[] = "updated_at = NOW()";
        $params[] = $userId;
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $result = $db->execute($sql, $params);
        
        if ($result === false) {
            Response::error('Kullanıcı güncellenemedi');
        }
        
        if ($passwordChanged) {
            $updatedUser = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
            
            $oldDataForLog = $oldData;
            $oldDataForLog['password'] = $oldData['password']; // Eski hash
            
            $newDataForLog = $updatedUser;
            
            $currentUserId = isset($data['current_user_id']) ? (int)$data['current_user_id'] : getCurrentUserId();
            logUserUpdate($pdo, $currentUserId, $userId, $oldDataForLog);
        } else {
            $currentUserId = isset($data['current_user_id']) ? (int)$data['current_user_id'] : getCurrentUserId();
            logUserUpdate($pdo, $currentUserId, $userId, $oldData);
        }

        $updatedUser = $db->fetch(
            "SELECT u.id, u.username, u.email, u.full_name, u.phone, 
                    u.role_id, u.status, u.updated_at, r.name as role_name
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ?",
            [$userId]
        );
        
        Response::success($updatedUser, 'Kullanıcı başarıyla güncellendi');
        
    } catch (Exception $e) {
        error_log('handlePut exception: ' . $e->getMessage());
        Response::serverError('Güncelleme hatası: ' . $e->getMessage());
    }
}

function handleDelete() {
    global $db, $pdo;
    
    try {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            $id = $data['id'] ?? null;
        }
        if (!$id) {
            Response::error('Kullanıcı ID gereklidir');
        }
        $userId = (int)$id;
        
        $oldData = $db->fetch(  
            "SELECT * FROM users WHERE id = ? AND status = 1",
            [$userId]
        );
        
        if (!$oldData) { 
            Response::notFound('Kullanıcı bulunamadı');
        }
        
        $result = $db->execute(
            "UPDATE users SET status = 0, updated_at = NOW() WHERE id = ?",
            [$userId]
        );
        
        if ($result === false) {
            Response::error('Kullanıcı silinemedi');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $currentUserId = isset($input['current_user_id']) ? (int)$input['current_user_id'] : 1;
        logUserDelete($pdo, $currentUserId, $userId, $oldData);
        
        Response::success(null, 'Kullanıcı başarıyla silindi');
        
    } catch (Exception $e) {
        error_log('handleDelete exception: ' . $e->getMessage());
        Response::serverError('Silme hatası: ' . $e->getMessage());
    }
}

function validateUserData($data, $userId = null) {
    $errors = [];
    
    if (empty($data['username'])) {
        $errors['username'] = 'Kullanıcı adı gereklidir';
    } elseif (strlen($data['username']) < 3) {
        $errors['username'] = 'Kullanıcı adı en az 3 karakter olmalıdır';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
        $errors['username'] = 'Kullanıcı adı sadece harf, rakam ve alt çizgi içerebilir';
    }
    
    if (empty($data['email'])) {
        $errors['email'] = 'Email adresi gereklidir';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Geçerli bir email adresi giriniz';
    }
    
    if (empty($data['full_name'])) {
        $errors['full_name'] = 'Ad soyad gereklidir';
    } elseif (strlen(trim($data['full_name'])) < 2) {
        $errors['full_name'] = 'Ad soyad en az 2 karakter olmalıdır';
    }
    
    if ($userId === null) { 
        if (empty($data['password'])) {
            $errors['password'] = 'Şifre gereklidir';
        } elseif (strlen($data['password']) < 6) {
            $errors['password'] = 'Şifre en az 6 karakter olmalıdır';
        }
    } else { 
        if (!empty($data['password']) && strlen($data['password']) < 6) {
            $errors['password'] = 'Şifre en az 6 karakter olmalıdır';
        }
    }
    
    if (!empty($data['phone']) && !preg_match('/^[0-9+\-\(\)\s]+$/', $data['phone'])) {
        $errors['phone'] = 'Geçerli bir telefon numarası giriniz';
    }
    
    if (!empty($data['role_id']) && $data['role_id'] !== '') {
        global $db;
        try {
            $role = $db->fetch("SELECT id FROM roles WHERE id = ? AND status = 1", [(int)$data['role_id']]);
            if (!$role) {
                $errors['role_id'] = 'Geçersiz rol seçimi';
            }
        } catch (Exception $e) {
            error_log('Role validation error: ' . $e->getMessage());
            $errors['role_id'] = 'Rol kontrolü yapılamadı';
        }
    }
    
    return $errors;
}


?>