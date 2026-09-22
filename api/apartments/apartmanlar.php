<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/database.php'; 

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ob_clean();

try {
    require_once '../core/database.php';
    require_once '../core/auth.php';     
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
            $apartmentId = (int)$_GET['id'];
            $sql = "SELECT a.*, u.full_name as manager_name 
                   FROM apartments a 
                   LEFT JOIN users u ON a.manager_id = u.id 
                   WHERE a.id = ? AND a.status = 1";
            $apartment = $db->fetch($sql, [$apartmentId]);
            
            if (!$apartment) {
                echo json_encode(['success' => false, 'message' => 'Apartman bulunamadı']);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $apartment]);
            exit;
        }
        
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $offset = ($page - 1) * $limit;
        
        $whereCondition = "WHERE a.status = 1";
        $params = [];
        
        if (!empty($search)) {
            $whereCondition .= " AND (a.name LIKE ? OR a.address LIKE ? OR a.tax_number LIKE ? OR CAST(a.total_units AS CHAR) LIKE ? OR u.full_name LIKE ? OR a.phone LIKE ? OR a.email LIKE ?)";
            $searchParam = "%{$search}%";
            $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
        }
        
        $countSql = "SELECT COUNT(*) as total FROM apartments a LEFT JOIN users u ON a.manager_id = u.id {$whereCondition}";
        $totalResult = $db->fetch($countSql, $params);
        $total = $totalResult['total'];
        
        $sql = "SELECT a.*, u.full_name as manager_name 
               FROM apartments a 
               LEFT JOIN users u ON a.manager_id = u.id 
               {$whereCondition}
               ORDER BY a.created_at DESC 
               LIMIT {$limit} OFFSET {$offset}";
        
        $apartments = $db->fetchAll($sql, $params);
        
        $totalPages = ceil($total / $limit);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'apartments' => $apartments,
                'pagination' => [
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ]
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Apartmanlar getirilemedi: ' . $e->getMessage()]);
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
        
        if (empty($data['name'])) {
            echo json_encode(['success' => false, 'message' => 'Apartman adı gereklidir']);
            exit;
        }
        
        if (empty($data['address'])) {
            echo json_encode(['success' => false, 'message' => 'Adres gereklidir']);
            exit;
        }
        
        if (empty($data['total_units']) || $data['total_units'] < 1) {
            echo json_encode(['success' => false, 'message' => 'Geçerli bir daire sayısı giriniz']);
            exit;
        }
        
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Geçerli bir e-posta adresi giriniz']);
            exit;
        }
        
        if (!empty($data['manager_id'])) {
            $manager = $db->fetch(
                "SELECT id FROM users WHERE id = ? AND status = 1", 
                [$data['manager_id']]
            );
            if (!$manager) {
                echo json_encode(['success' => false, 'message' => 'Geçersiz yönetici seçimi']);
                exit;
            }
        }
        
        $existingApartment = $db->fetch(
            "SELECT id FROM apartments WHERE name = ? AND status = 1",
            [$data['name']]
        );
        
        if ($existingApartment) {
            echo json_encode(['success' => false, 'message' => 'Bu apartman adı zaten kullanılıyor']);
            exit;
        }
        
        $sql = "INSERT INTO apartments (name, address, total_units, manager_id, tax_number, phone, email, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())";
        
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['name'],
            $data['address'],
            (int)$data['total_units'],
            !empty($data['manager_id']) ? (int)$data['manager_id'] : null,
            !empty($data['tax_number']) ? $data['tax_number'] : null,
            !empty($data['phone']) ? $data['phone'] : null,
            !empty($data['email']) ? $data['email'] : null
        ]);
        
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Apartman veritabanına eklenemedi']);
            exit;
        }
        
        $apartmentId = $pdo->lastInsertId();
        $currentUserId = getCurrentUserId();
        logApartmentInsert($pdo, $currentUserId, $apartmentId);
        
        $newApartment = $db->fetch(
            "SELECT a.*, u.full_name as manager_name 
             FROM apartments a 
             LEFT JOIN users u ON a.manager_id = u.id 
             WHERE a.id = ?", 
            [$apartmentId]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Apartman başarıyla eklendi',
            'data' => $newApartment
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Apartman eklenirken hata: ' . $e->getMessage()]);
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
        
        $apartmentId = (int)$data['id'];
        
        $existingApartment = $db->fetch(
            "SELECT * FROM apartments WHERE id = ? AND status = 1",
            [$apartmentId]
        );
        
        if (!$existingApartment) {
            echo json_encode(['success' => false, 'message' => 'Apartman bulunamadı']);
            exit;
        }

        $oldData = $existingApartment; 
        
        if (empty($data['name'])) {
            echo json_encode(['success' => false, 'message' => 'Apartman adı gereklidir']);
            exit;
        }
        
        if (empty($data['address'])) {
            echo json_encode(['success' => false, 'message' => 'Adres gereklidir']);
            exit;
        }
        
        if (empty($data['total_units']) || $data['total_units'] < 1) {
            echo json_encode(['success' => false, 'message' => 'Geçerli bir daire sayısı giriniz']);
            exit;
        }
        
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Geçerli bir e-posta adresi giriniz']);
            exit;
        }
        
        if (!empty($data['manager_id'])) {
            $manager = $db->fetch(
                "SELECT id FROM users WHERE id = ? AND status = 1", 
                [$data['manager_id']]
            );
            if (!$manager) {
                echo json_encode(['success' => false, 'message' => 'Geçersiz yönetici seçimi']);
                exit;
            }
        }
        
        $duplicateApartment = $db->fetch(
            "SELECT id FROM apartments WHERE name = ? AND id != ? AND status = 1",
            [$data['name'], $apartmentId]
        );
        
        if ($duplicateApartment) {
            echo json_encode(['success' => false, 'message' => 'Bu apartman adı zaten kullanılıyor']);
            exit;
        }
        
        $sql = "UPDATE apartments SET 
                name = ?, 
                address = ?, 
                total_units = ?, 
                manager_id = ?, 
                tax_number = ?, 
                phone = ?, 
                email = ?, 
                updated_at = NOW() 
                WHERE id = ?";
        
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['name'],
            $data['address'],
            (int)$data['total_units'],
            !empty($data['manager_id']) ? (int)$data['manager_id'] : null,
            !empty($data['tax_number']) ? $data['tax_number'] : null,
            !empty($data['phone']) ? $data['phone'] : null,
            !empty($data['email']) ? $data['email'] : null,
            $apartmentId
        ]);
        
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Apartman güncellenemedi']);
            exit;
        }

        require_once '../config/system_logger.php';
        $currentUserId = getCurrentUserId();
        logApartmentUpdate($pdo, $currentUserId, $apartmentId, $oldData);
        
        $updatedApartment = $db->fetch(
            "SELECT a.*, u.full_name as manager_name 
             FROM apartments a 
             LEFT JOIN users u ON a.manager_id = u.id 
             WHERE a.id = ?", 
            [$apartmentId]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Apartman başarıyla güncellendi',
            'data' => $updatedApartment
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Apartman güncellenirken hata: ' . $e->getMessage()]);
        exit;
    }
}
function handleDelete($db) {
    try {
        if (isset($_GET['id'])) {
            $apartmentId = (int)$_GET['id'];
        } else {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (!$data || !isset($data['id'])) {
                echo json_encode(['success' => false, 'message' => 'Apartman ID gereklidir']);
                exit;
            }
            
            $apartmentId = (int)$data['id'];
        }
        
        $apartment = $db->fetch(
            "SELECT id, name FROM apartments WHERE id = ? AND status = 1",
            [$apartmentId]
        );
        
        if (!$apartment) {
            echo json_encode(['success' => false, 'message' => 'Apartman bulunamadı']);
            exit;
        }
        
        
        $oldData = $apartment;

        $sql = "UPDATE apartments SET status = 0, updated_at = NOW() WHERE id = ?";
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$apartmentId]);
        
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Apartman silinemedi']);
            exit;
        }
        
        $currentUserId = getCurrentUserId();
        logApartmentDelete($pdo, $currentUserId, $apartmentId, $oldData);

        echo json_encode([
            'success' => true,
            'message' => 'Apartman başarıyla silindi'
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Apartman silinirken hata: ' . $e->getMessage()]);
        exit;
    }
}

ob_end_clean();
?>