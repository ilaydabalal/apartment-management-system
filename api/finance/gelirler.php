<?php 
require_once __DIR__ . '/../core/database.php'; 
require_once __DIR__ . '/../core/response.php';  
require_once __DIR__ . '/../core/auth.php';      
require_once __DIR__ . '/../config/system_logger.php'; 

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function getCurrentUserId() {
    try {
        error_log("🔍 getCurrentUserId() çalışıyor...");
        
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        error_log("🔑 Auth Header: " . $authHeader);
        
        if (empty($authHeader)) {
            error_log("❌ Authorization header bulunamadı, default 1 döndürülüyor");
            return 1;
        }

        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            error_log("🎫 Token alındı: " . substr($token, 0, 20) . "...");
            
            try {
                $tokenParts = explode('.', $token);
                if (count($tokenParts) == 3) {
                    $payload = json_decode(base64_decode($tokenParts[1]), true);
                    error_log("📦 Token payload: " . json_encode($payload));
                    
                    if (isset($payload['user_id'])) {
                        error_log("✅ User ID bulundu: " . $payload['user_id']);
                        return (int)$payload['user_id'];
                    } else {
                        error_log("❌ Token'da user_id bulunamadı");
                    }
                } else {
                    error_log("❌ Token formatı hatalı, part sayısı: " . count($tokenParts));
                }
            } catch (Exception $e) {
                error_log("❌ Token parse hatası: " . $e->getMessage());
            }
        } else {
            error_log("❌ Bearer token formatı değil");
        }
        
        try {
            $auth = new Auth();
            $user = $auth->getCurrentUser();
            
            if ($user && isset($user['id'])) {
                error_log("✅ Auth sınıfından user ID alındı: " . $user['id']);
                return (int)$user['id'];
            }
        } catch (Exception $e) {
            error_log("❌ Auth sınıfı hatası: " . $e->getMessage());
        }
        
        error_log("⚠️ Hiçbir yöntem çalışmadı, default 1 döndürülüyor");
        return 1;
        
    } catch (Exception $e) {
        error_log("💥 getCurrentUserId genel hatası: " . $e->getMessage());
        return 1;
    }
}

try {     
    $db = Database::getInstance();
    $pdo = $db->getConnection();   
    $method = $_SERVER['REQUEST_METHOD'];      

    if ($method === 'GET') {         
        if (isset($_GET['id'])) {
            $incomeId = (int)$_GET['id'];
            $sql = "SELECT i.*, c.name as category, u.full_name as created_by, a.name as apartment_name
                    FROM incomes i
                    LEFT JOIN income_categories c ON i.category_id = c.id
                    LEFT JOIN users u ON i.created_by = u.id
                    LEFT JOIN apartments a ON i.apartment_id = a.id
                    WHERE i.id = ? AND i.status = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$incomeId]);
            $income = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$income) {
                Response::error('Gelir bulunamadı', 404);
            }
            
            Response::success($income);
            return;
        }
        
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';         
        $sql = "SELECT i.*, c.name as category, u.full_name as created_by, a.name as apartment_name                 
                FROM incomes i                 
                LEFT JOIN income_categories c ON i.category_id = c.id                 
                LEFT JOIN users u ON i.created_by = u.id                 
                LEFT JOIN apartments a ON i.apartment_id = a.id
                WHERE i.status = 1";         
        $params = [];         
        
        if ($search !== '') {             
            $sql .= " AND (                 
                a.name LIKE ? OR                 
                i.description LIKE ? OR                 
                i.amount LIKE ? OR                 
                i.income_date LIKE ? OR                 
                c.name LIKE ? OR                 
                u.full_name LIKE ?             
            )";             
            $searchParam = "%$search%";             
            $params = array_fill(0, 6, $searchParam);         
        }         
        
        $sql .= " ORDER BY i.income_date DESC, i.id DESC";         
        $stmt = $pdo->prepare($sql);         
        $stmt->execute($params);         
        $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);         
        
        Response::success(['incomes' => $incomes]);     
        
    } elseif ($method === 'POST') {         
        $data = json_decode(file_get_contents('php://input'), true);         
        
        if (!isset($data['description'], $data['amount'], $data['income_date'], $data['category_id'], $data['created_by'])) {             
            Response::error('Eksik alanlar', 422);         
        }         
        
        $sql = "INSERT INTO incomes (apartment_id, description, amount, income_date, category_id, created_by, status, created_at, updated_at)                 
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";         
        $stmt = $pdo->prepare($sql);         
        $result = $stmt->execute([             
            $data['apartment_id'] ?? null,
            $data['description'],             
            $data['amount'],             
            $data['income_date'],             
            $data['category_id'],             
            $data['created_by']         
        ]);         
        
        if ($result) {
            $incomeId = $pdo->lastInsertId();
            
    $currentUserId = getCurrentUserId();
    logIncomeInsert($pdo, $currentUserId, $incomeId);
    

            Response::success(['id' => $incomeId], 'Gelir eklendi');
        } else {
            Response::error('Gelir eklenemedi', 500);
        }     
        
    } elseif ($method === 'PUT') {         
        $data = json_decode(file_get_contents('php://input'), true);         
    
    if (!isset($data['id'], $data['description'], $data['amount'], $data['income_date'], $data['category_id'])) {             
        Response::error('Eksik alanlar', 422);         
    }           
        
    $oldDataSql = "SELECT i.*, c.name as category_name, a.name as apartment_name, u.full_name as creator_name
    FROM incomes i 
    LEFT JOIN income_categories c ON i.category_id = c.id 
    LEFT JOIN apartments a ON i.apartment_id = a.id 
    LEFT JOIN users u ON i.created_by = u.id
    WHERE i.id = ? AND i.status = 1";
$oldDataStmt = $pdo->prepare($oldDataSql);
$oldDataStmt->execute([$data['id']]);
$oldData = $oldDataStmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) {
            Response::error('Gelir bulunamadı', 404);
        }
        
        $sql = "UPDATE incomes SET apartment_id=?, description=?, amount=?, income_date=?, category_id=?, created_by=?, updated_at=NOW() 
            WHERE id=? AND status=1";         
    $stmt = $pdo->prepare($sql);         
    $result = $stmt->execute([             
        $data['apartment_id'] ?? null,
        $data['description'],             
        $data['amount'],             
        $data['income_date'],             
        $data['category_id'],
        $data['created_by'] ?? null,             
        $data['id']         
    ]);                  
        
    if ($stmt->rowCount() === 0) {
        Response::error('Gelir bulunamadı veya güncellenemedi', 404);
    }
    $currentUserId = getCurrentUserId();
    logIncomeUpdate($pdo, $currentUserId, $data['id'], $oldData);

    
        Response::success(null, 'Gelir güncellendi');     
        
    } elseif ($method === 'DELETE') {         
        $data = json_decode(file_get_contents('php://input'), true);         
    
    if (!isset($data['id'])) {             
        Response::error('Eksik id', 422);         
    }         
        
        $checkSql = "SELECT i.*, c.name as category_name, a.name as apartment_name, u.full_name as creator_name
                 FROM incomes i 
                 LEFT JOIN income_categories c ON i.category_id = c.id 
                 LEFT JOIN apartments a ON i.apartment_id = a.id 
                 LEFT JOIN users u ON i.created_by = u.id
                 WHERE i.id = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$data['id']]);
    $income = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$income) {
        Response::error('Gelir bulunamadı', 404);
    }
    
    if ($income['status'] == 0) {
        Response::error('Gelir zaten silinmiş', 400);
    }
    
    $oldData = $income;
    
    $sql = "UPDATE incomes SET status = 0, updated_at = NOW() WHERE id = ?";         
    $stmt = $pdo->prepare($sql);         
    $result = $stmt->execute([$data['id']]);         
    
    if ($stmt->rowCount() === 0) {
        Response::error('Gelir silinemedi', 500);
    }
    $currentUserId = getCurrentUserId();
    logIncomeDelete($pdo, $currentUserId, $data['id'], $oldData);

        
        Response::success(null, 'Gelir silindi');     
        
    } else {         
        Response::error('Geçersiz istek', 405);     
    } 
    
} catch (Exception $e) {     
    Response::error('Gelirler işlemi hatası: ' . $e->getMessage()); 
}


?>