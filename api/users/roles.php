<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../core/database.php';
require_once '../core/auth.php';     
require_once '../config/system_logger.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ob_clean();

try {
    require_once '../core/database.php';
    
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
   

    $db = Database::getInstance();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Desteklenmeyen HTTP metodu']);
            exit;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Sunucu hatası: ' . $e->getMessage()
    ]);
    exit;
}

function handleGet($db) {
    try {
        if (isset($_GET['id'])) {
            $roleId = (int)$_GET['id'];
            $sql = "SELECT * FROM roles WHERE id = ? AND status = 1";
            $role = $db->fetch($sql, [$roleId]);
            
            if (!$role) {
                echo json_encode(['success' => false, 'message' => 'Rol bulunamadı']);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $role]);
            exit;
        }
        
        $sql = "SELECT * FROM roles WHERE status = 1 ORDER BY created_at DESC";
        $roles = $db->fetchAll($sql);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'roles' => $roles,
                'pagination' => [
                    'total' => count($roles),
                    'current_page' => 1,
                    'per_page' => count($roles)
                ]
            ]
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Roller getirilemedi: ' . $e->getMessage()]);
        exit;
    }
}

function handlePost($db) {
    try {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz JSON formatı']);
            exit;
        }
        
        error_log("Gelen veri: " . print_r($data, true));
        
        if (empty($data['name'])) {
            echo json_encode(['success' => false, 'message' => 'Rol adı gereklidir']);
            exit;
        }
        
        $existingRole = $db->fetch(
            "SELECT id FROM roles WHERE name = ? AND status = 1",
            [$data['name']]
        );
        
        if ($existingRole) {
            echo json_encode(['success' => false, 'message' => 'Bu rol adı zaten kullanılıyor']);
            exit;
        }
        
        $permissions = $data['permissions'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        $permissionsJson = json_encode($permissions, JSON_UNESCAPED_UNICODE);
        
        $sql = "INSERT INTO roles (name, description, permissions, status, created_at) VALUES (?, ?, ?, 1, NOW())";
        
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $permissionsJson
        ]);
        
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Rol veritabanına eklenemedi']);
            exit;
        }
        
        $roleId = $pdo->lastInsertId();
        $currentUserId = getCurrentUserId();
        logRoleInsert($pdo, $currentUserId, $roleId);
        
        $newRole = $db->fetch("SELECT * FROM roles WHERE id = ?", [$roleId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Rol başarıyla eklendi',
            'data' => $newRole
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log("Rol ekleme hatası: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Rol eklenirken hata: ' . $e->getMessage()]);
        exit;
    }
}

function handlePut($db) {
    try {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz veri formatı']);
            exit;
        }
        
        $roleId = (int)$data['id'];
        
        $existingRole = $db->fetch(
            "SELECT * FROM roles WHERE id = ? AND status = 1",
            [$roleId]
        );
        
        if (!$existingRole) {
            echo json_encode(['success' => false, 'message' => 'Rol bulunamadı']);
            exit;
        }
        
        if (empty($data['name'])) {
            echo json_encode(['success' => false, 'message' => 'Rol adı gereklidir']);
            exit;
        }

        $oldData = $existingRole;
        
        $duplicateRole = $db->fetch(
            "SELECT id FROM roles WHERE name = ? AND id != ? AND status = 1",
            [$data['name'], $roleId]
        );
        
        if ($duplicateRole) {
            echo json_encode(['success' => false, 'message' => 'Bu rol adı zaten kullanılıyor']);
            exit;
        }
        
        $permissions = $data['permissions'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        $permissionsJson = json_encode($permissions, JSON_UNESCAPED_UNICODE);
        
        $sql = "UPDATE roles SET name = ?, description = ?, permissions = ?, updated_at = NOW() WHERE id = ?";
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $permissionsJson,
            $roleId
        ]);
        
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Rol güncellenemedi']);
            exit;
        }
        
        $currentUserId = getCurrentUserId();
        logRoleUpdate($pdo, $currentUserId, $roleId, $oldData);
        
        $updatedRole = $db->fetch("SELECT * FROM roles WHERE id = ?", [$roleId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Rol başarıyla güncellendi',
            'data' => $updatedRole
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Rol güncellenirken hata: ' . $e->getMessage()]);
        exit;
    }
}

function handleDelete($db) {
    try {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'Rol ID gereklidir']);
            exit;
        }
        
        $roleId = (int)$data['id'];
        
        $role = $db->fetch(
            "SELECT id, name FROM roles WHERE id = ? AND status = 1",
            [$roleId]
        );
        
        $oldData = $role; 

        if (!$role) {
            echo json_encode(['success' => false, 'message' => 'Rol bulunamadı']);
            exit;
        }
        
        if ($role['name'] === 'Süper Admin') {
            echo json_encode(['success' => false, 'message' => 'Süper Admin rolü silinemez']);
            exit;
        }
        
        $sql = "UPDATE roles SET status = 0, updated_at = NOW() WHERE id = ?";
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$roleId]);

        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Rol silinemedi']);
            exit;
        }

        $currentUserId = getCurrentUserId();
        logRoleDelete($pdo, $currentUserId, $roleId, $oldData);
        
        echo json_encode([
            'success' => true,
            'message' => 'Rol başarıyla silindi'
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Rol silinirken hata: ' . $e->getMessage()]);
        exit;
    }
}

ob_end_clean();
?>