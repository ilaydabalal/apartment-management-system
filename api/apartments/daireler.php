<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

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
    require_once '../config/system_logger.php'; 
    
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
            $unitId = (int)$_GET['id'];
            $sql = "SELECT au.*, a.name as apartment_name 
                   FROM apartment_units au 
                   LEFT JOIN apartments a ON au.apartment_id = a.id 
                   WHERE au.id = ? AND au.status = 1";
            $unit = $db->fetch($sql, [$unitId]);
            
            if (!$unit) {
                echo json_encode(['success' => false, 'message' => 'Daire bulunamadı']);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $unit]);
            exit;
        }
        
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $apartmentId = isset($_GET['apartment_id']) ? (int)$_GET['apartment_id'] : null;
        $offset = ($page - 1) * $limit;
        
        $whereCondition = "WHERE au.status = 1";
        $params = [];
        
        if ($apartmentId) {
            $whereCondition .= " AND au.apartment_id = ?";
            $params[] = $apartmentId;
        }
        
        if (!empty($search)) {
            $whereCondition .= " AND (au.unit_number LIKE ? OR a.name LIKE ? OR au.owner_name LIKE ? OR au.owner_phone LIKE ? OR au.owner_email LIKE ? OR au.resident_name LIKE ? OR au.resident_phone LIKE ? OR au.resident_email LIKE ? OR au.floor LIKE ? OR au.type LIKE ? OR au.area LIKE ?)";
            $searchParam = "%{$search}%";
            $params = array_merge($params, [
                $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam
            ]);
        }
        
        $countSql = "SELECT COUNT(*) as total FROM apartment_units au 
                    LEFT JOIN apartments a ON au.apartment_id = a.id 
                    {$whereCondition}";
        $totalResult = $db->fetch($countSql, $params);
        $total = $totalResult['total'];
        
        $sql = "SELECT au.*, a.name as apartment_name 
               FROM apartment_units au 
               LEFT JOIN apartments a ON au.apartment_id = a.id 
               {$whereCondition}
               ORDER BY a.name ASC, au.unit_number ASC 
               LIMIT {$limit} OFFSET {$offset}";
        
        $units = $db->fetchAll($sql, $params);
        
        $totalPages = ceil($total / $limit);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'units' => $units,
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
        echo json_encode(['success' => false, 'message' => 'Daireler getirilemedi: ' . $e->getMessage()]);
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
        
        if (empty($data['apartment_id'])) {
            echo json_encode(['success' => false, 'message' => 'Apartman seçimi gereklidir']);
            exit;
        }
        
        if (empty($data['unit_number'])) {
            echo json_encode(['success' => false, 'message' => 'Daire numarası gereklidir']);
            exit;
        }
        
        if (empty($data['floor']) && $data['floor'] !== '0') {
            echo json_encode(['success' => false, 'message' => 'Kat bilgisi gereklidir']);
            exit;
        }
        
        $apartment = $db->fetch(
            "SELECT id FROM apartments WHERE id = ? AND status = 1", 
            [$data['apartment_id']]
        );
        if (!$apartment) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz apartman seçimi']);
            exit;
        }
        
        if (!empty($data['owner_email']) && !filter_var($data['owner_email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Geçerli bir mal sahibi e-posta adresi giriniz']);
            exit;
        }
        
        if (!empty($data['resident_email']) && !filter_var($data['resident_email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Geçerli bir oturan e-posta adresi giriniz']);
            exit;
        }
        
        $existingUnit = $db->fetch(
            "SELECT id FROM apartment_units WHERE apartment_id = ? AND unit_number = ? AND status = 1",
            [$data['apartment_id'], $data['unit_number']]
        );
        
        if ($existingUnit) {
            echo json_encode(['success' => false, 'message' => 'Bu daire numarası bu apartmanda zaten kullanılıyor']);
            exit;
        }
        
        $sql = "INSERT INTO apartment_units (apartment_id, unit_number, floor, owner_name, owner_phone, owner_email, resident_name, resident_phone, resident_email, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
        
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            (int)$data['apartment_id'],
            $data['unit_number'],
            (int)$data['floor'],
            !empty($data['owner_name']) ? $data['owner_name'] : null,
            !empty($data['owner_phone']) ? $data['owner_phone'] : null,
            !empty($data['owner_email']) ? $data['owner_email'] : null,
            !empty($data['resident_name']) ? $data['resident_name'] : null,
            !empty($data['resident_phone']) ? $data['resident_phone'] : null,
            !empty($data['resident_email']) ? $data['resident_email'] : null
        ]);
        
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Daire veritabanına eklenemedi']);
            exit;
        }
        
        $unitId = $pdo->lastInsertId();
        
        $currentUserId = getCurrentUserId();
        logUnitInsert($pdo, $currentUserId, $unitId);
        
        $newUnit = $db->fetch(
            "SELECT au.*, a.name as apartment_name 
             FROM apartment_units au 
             LEFT JOIN apartments a ON au.apartment_id = a.id 
             WHERE au.id = ?", 
            [$unitId]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Daire başarıyla eklendi',
            'data' => $newUnit
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Daire eklenirken hata: ' . $e->getMessage()]);
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
        
        $unitId = (int)$data['id'];
        
        $existingUnit = $db->fetch(
            "SELECT * FROM apartment_units WHERE id = ? AND status = 1",
            [$unitId]
        );
        
        if (!$existingUnit) {
            echo json_encode(['success' => false, 'message' => 'Daire bulunamadı']);
            exit;
        }
        
        $oldData = $existingUnit; 
        
        if (empty($data['apartment_id'])) {
            echo json_encode(['success' => false, 'message' => 'Apartman seçimi gereklidir']);
            exit;
        }
        
        if (empty($data['unit_number'])) {
            echo json_encode(['success' => false, 'message' => 'Daire numarası gereklidir']);
            exit;
        }
        
        if (empty($data['floor']) && $data['floor'] !== '0') {
            echo json_encode(['success' => false, 'message' => 'Kat bilgisi gereklidir']);
            exit;
        }
        
        if (!empty($data['owner_email']) && !filter_var($data['owner_email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Geçerli bir mal sahibi e-posta adresi giriniz']);
            exit;
        }
        
        if (!empty($data['resident_email']) && !filter_var($data['resident_email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Geçerli bir oturan e-posta adresi giriniz']);
            exit;
        }
        
        $duplicateUnit = $db->fetch(
            "SELECT id FROM apartment_units WHERE apartment_id = ? AND unit_number = ? AND id != ? AND status = 1",
            [$data['apartment_id'], $data['unit_number'], $unitId]
        );
        
        if ($duplicateUnit) {
            echo json_encode(['success' => false, 'message' => 'Bu daire numarası bu apartmanda zaten kullanılıyor']);
            exit;
        }
        
        $sql = "UPDATE apartment_units SET 
                apartment_id = ?, 
                unit_number = ?, 
                floor = ?, 
                owner_name = ?, 
                owner_phone = ?, 
                owner_email = ?, 
                resident_name = ?, 
                resident_phone = ?, 
                resident_email = ?, 
                updated_at = NOW() 
                WHERE id = ?";
        
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            (int)$data['apartment_id'],
            $data['unit_number'],
            (int)$data['floor'],
            !empty($data['owner_name']) ? $data['owner_name'] : null,
            !empty($data['owner_phone']) ? $data['owner_phone'] : null,
            !empty($data['owner_email']) ? $data['owner_email'] : null,
            !empty($data['resident_name']) ? $data['resident_name'] : null,
            !empty($data['resident_phone']) ? $data['resident_phone'] : null,
            !empty($data['resident_email']) ? $data['resident_email'] : null,
            $unitId
        ]);
        
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Daire güncellenemedi']);
            exit;
        }
        
        $currentUserId = getCurrentUserId();
        logUnitUpdate($pdo, $currentUserId, $unitId, $oldData);
        
        $updatedUnit = $db->fetch(
            "SELECT au.*, a.name as apartment_name 
             FROM apartment_units au 
             LEFT JOIN apartments a ON au.apartment_id = a.id 
             WHERE au.id = ?", 
            [$unitId]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Daire başarıyla güncellendi',
            'data' => $updatedUnit
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Daire güncellenirken hata: ' . $e->getMessage()]);
        exit;
    }
}

function handleDelete($db) {
    try {
        if (isset($_GET['id'])) {
            $unitId = (int)$_GET['id'];
        } else {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (!$data || !isset($data['id'])) {
                echo json_encode(['success' => false, 'message' => 'Daire ID gereklidir']);
                exit;
            }
            
            $unitId = (int)$data['id'];
        }
        
        $unit = $db->fetch(
            "SELECT au.*, a.name as apartment_name 
             FROM apartment_units au 
             LEFT JOIN apartments a ON au.apartment_id = a.id 
             WHERE au.id = ? AND au.status = 1",
            [$unitId]
        );
        
        if (!$unit) {
            echo json_encode(['success' => false, 'message' => 'Daire bulunamadı']);
            exit;
        }
        
        $oldData = $unit; 
        
        $pdo = $db->getConnection();
        
        $pdo->beginTransaction();
        
        try {
            error_log("🗑️ Daire ID $unitId için aidatlar siliniyor...");
            
            $dueCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM dues WHERE unit_id = ? AND status = 1");
            $dueCountStmt->execute([$unitId]);
            $dueCount = $dueCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($dueCount > 0) {
                $deleteDuesStmt = $pdo->prepare("UPDATE dues SET status = 0, updated_at = NOW() WHERE unit_id = ? AND status = 1");
                $duesResult = $deleteDuesStmt->execute([$unitId]);
                
                if (!$duesResult) {
                    throw new Exception('Aidatlar silinemedi');
                }
                
                error_log("✅ $dueCount aidat soft delete edildi");
                
                $deletePaymentsStmt = $pdo->prepare("DELETE FROM payments WHERE unit_id = ?");
                $paymentsResult = $deletePaymentsStmt->execute([$unitId]);
                
                if ($paymentsResult) {
                    error_log("✅ İlgili ödeme kayıtları silindi");
                }
            } else {
                error_log("ℹ️ Silinecek aidat bulunamadı");
            }
            
            $deleteUnitStmt = $pdo->prepare("UPDATE apartment_units SET status = 0, updated_at = NOW() WHERE id = ?");
            $unitResult = $deleteUnitStmt->execute([$unitId]);
            
            if (!$unitResult) {
                throw new Exception('Daire silinemedi');
            }
            
            $pdo->commit();
            
            $currentUserId = getCurrentUserId();
            logUnitDelete($pdo, $currentUserId, $unitId, $oldData);
            
            $message = 'Daire başarıyla silindi';
            if ($dueCount > 0) {
                $message .= " ($dueCount aidat kaydı ile birlikte)";
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'deleted_dues_count' => $dueCount
            ]);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("❌ Transaction rollback: " . $e->getMessage());
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Daire silinirken hata: ' . $e->getMessage()]);
        exit;
    }
}

ob_end_clean();

?>