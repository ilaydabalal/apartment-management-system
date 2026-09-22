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
    $pdo = $db->getConnection(); 
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                $sql = "SELECT e.*, c.name as category, u.full_name as created_by, a.name as apartment_name
                        FROM expenses e
                        LEFT JOIN expense_categories c ON e.category_id = c.id
                        LEFT JOIN users u ON e.created_by = u.id
                        LEFT JOIN apartments a ON e.apartment_id = a.id
                        WHERE e.id = ? AND e.status = 1";
                $stmt = $db->getConnection()->prepare($sql);
                $stmt->execute([$id]);
                $expense = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $expense]);
                exit;
            }
            
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $sql = "SELECT e.*, c.name as category, u.full_name as created_by, a.name as apartment_name
                    FROM expenses e
                    LEFT JOIN expense_categories c ON e.category_id = c.id
                    LEFT JOIN users u ON e.created_by = u.id
                    LEFT JOIN apartments a ON e.apartment_id = a.id
                    WHERE e.status = 1";
            $params = [];
            
            if ($search !== '') {
                $sql .= " AND (
                    a.name LIKE ? OR
                    e.description LIKE ? OR
                    e.amount LIKE ? OR
                    e.expense_date LIKE ? OR
                    e.vendor LIKE ? OR
                    c.name LIKE ? OR
                    u.full_name LIKE ?
                )";
                $searchParam = "%$search%";
                $params = array_fill(0, 7, $searchParam);
            }
            
            $sql .= " ORDER BY e.expense_date DESC, e.id DESC";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->execute($params);
            $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => ['expenses' => $expenses]]);
            exit;
            
        case 'POST':
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (empty($data['expense_date']) || empty($data['amount']) || empty($data['category_id']) || empty($data['apartment_id'])) {
                echo json_encode(['success' => false, 'message' => 'Tüm zorunlu alanlar doldurulmalıdır.']);
                exit;
            }
            
            $sql = "INSERT INTO expenses (apartment_id, description, amount, expense_date, category_id, vendor, created_by, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
            $stmt = $db->getConnection()->prepare($sql);
            $result = $stmt->execute([
                $data['apartment_id'],
                $data['description'] ?? null,
                $data['amount'],
                $data['expense_date'],
                $data['category_id'],
                $data['vendor'] ?? null,
                $data['created_by'] ?? null
            ]);
            
            if ($result) {
                $expenseId = $db->getConnection()->lastInsertId();
                
                // LOG KAYDET - EKLEME
                $currentUserId = getCurrentUserId();
                logExpenseInsert($pdo, $currentUserId, $expenseId);
                
                echo json_encode(['success' => true, 'message' => 'Gider başarıyla eklendi', 'id' => $expenseId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gider eklenemedi']);
            }
            exit;
            
        case 'PUT':
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (empty($data['id']) || empty($data['expense_date']) || empty($data['amount']) || empty($data['category_id']) || empty($data['apartment_id'])) {
                echo json_encode(['success' => false, 'message' => 'Tüm zorunlu alanlar doldurulmalıdır.']);
                exit;
            }
            
            $id = $data['id'];
            
            $oldDataSql = "SELECT e.*, c.name as category_name, a.name as apartment_name, u.full_name as creator_name
                           FROM expenses e 
                           LEFT JOIN expense_categories c ON e.category_id = c.id 
                           LEFT JOIN apartments a ON e.apartment_id = a.id 
                           LEFT JOIN users u ON e.created_by = u.id
                           WHERE e.id = ? AND e.status = 1";
            $oldDataStmt = $pdo->prepare($oldDataSql);
            $oldDataStmt->execute([$id]);
            $oldData = $oldDataStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldData) {
                echo json_encode(['success' => false, 'message' => 'Gider bulunamadı']);
                exit;
            }
            
            $sql = "UPDATE expenses SET 
                    apartment_id = ?, 
                    description = ?, 
                    amount = ?, 
                    expense_date = ?, 
                    category_id = ?, 
                    vendor = ?, 
                    created_by = ?, 
                    updated_at = NOW() 
                    WHERE id = ? AND status = 1";
            $stmt = $db->getConnection()->prepare($sql);
            $result = $stmt->execute([
                $data['apartment_id'],
                $data['description'] ?? null,
                $data['amount'],
                $data['expense_date'],
                $data['category_id'],
                $data['vendor'] ?? null,
                $data['created_by'] ?? null,
                $id
            ]);
            
            if ($result && $stmt->rowCount() > 0) {
                $currentUserId = getCurrentUserId();
                logExpenseUpdate($pdo, $currentUserId, $id, $oldData);
                
                echo json_encode(['success' => true, 'message' => 'Gider başarıyla güncellendi']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gider güncellenemedi veya bulunamadı']);
            }
            exit;
            
        case 'DELETE':
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            $id = isset($_GET['id']) ? (int)$_GET['id'] : ($data['id'] ?? 0);
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Gider ID gerekli']);
                exit;
            }
            
            $checkSql = "SELECT e.*, c.name as category_name, a.name as apartment_name, u.full_name as creator_name
                         FROM expenses e 
                         LEFT JOIN expense_categories c ON e.category_id = c.id 
                         LEFT JOIN apartments a ON e.apartment_id = a.id 
                         LEFT JOIN users u ON e.created_by = u.id
                         WHERE e.id = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            $expense = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$expense) {
                echo json_encode(['success' => false, 'message' => 'Gider bulunamadı']);
                exit;
            }
            
            if ($expense['status'] == 0) {
                echo json_encode(['success' => false, 'message' => 'Gider zaten silinmiş']);
                exit;
            }
            
            $oldData = $expense; 
            
            $sql = "UPDATE expenses SET status = 0, updated_at = NOW() WHERE id = ?";
            $stmt = $db->getConnection()->prepare($sql);
            $result = $stmt->execute([$id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $currentUserId = getCurrentUserId();
                logExpenseDelete($pdo, $currentUserId, $id, $oldData);
                
                echo json_encode(['success' => true, 'message' => 'Gider başarıyla silindi']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gider silinemedi']);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Desteklenmeyen HTTP metodu']);
            exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Sunucu hatası: ' . $e->getMessage()]);
    exit;
}
?>