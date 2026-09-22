<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    if (!file_exists('../config/database.php')) {
        throw new Exception('Database config dosyası bulunamadı');
    }
    require_once '../config/database.php';
    
    $authRequired = true;
    if (file_exists('../middleware/auth.php')) {
        require_once '../middleware/auth.php';
    } else {
        $authRequired = false;
        error_log('Auth middleware bulunamadı, auth kontrolü devre dışı');
    }
    
    if ($authRequired && function_exists('authenticateUser')) {
        try {
            $user = authenticateUser();
        } catch (Exception $e) {
            error_log('Auth hatası: ' . $e->getMessage());
        }
    }
    
    if (!isset($pdo)) {
        if (class_exists('Database')) {
            $database = new Database();
            $pdo = $database->getConnection();
        } else {
            throw new Exception('PDO bağlantısı bulunamadı');
        }
    }
    
    $tableCheck = $pdo->prepare("SHOW TABLES LIKE 'support_technicians'");
    $tableCheck->execute();
    
    if ($tableCheck->rowCount() === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Teknisyenler tablosu bulunamadı',
            'data' => [
                'technicians' => [],
                'active_count' => 0
            ]
        ]);
        exit();
    }
    
    $columnsCheck = $pdo->prepare("DESCRIBE support_technicians");
    $columnsCheck->execute();
    $columns = $columnsCheck->fetchAll(PDO::FETCH_COLUMN);
    
    $selectFields = ['id', 'name'];
    $optionalFields = ['email', 'phone', 'department', 'status', 'created_at', 'full_name'];
    
    foreach ($optionalFields as $field) {
        if (in_array($field, $columns)) {
            $selectFields[] = $field;
        }
    }
    
    $selectClause = implode(', ', $selectFields);
    
    $whereClause = '';
    if (in_array('status', $columns)) {
        $whereClause = 'WHERE status = 1';
    }
    
    $sql = "SELECT {$selectClause} FROM support_technicians {$whereClause} ORDER BY name ASC";
    
    error_log("Executing SQL: " . $sql);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedTechnicians = [];
    foreach ($technicians as $tech) {
        $formatted = [
            'id' => (int)$tech['id'],
            'name' => $tech['name'] ?? 'İsimsiz',
            'full_name' => $tech['full_name'] ?? $tech['name'] ?? 'İsimsiz',
            'email' => $tech['email'] ?? null,
            'phone' => $tech['phone'] ?? null,
            'department' => $tech['department'] ?? 'Genel',
            'status' => isset($tech['status']) ? (int)$tech['status'] : 1,
            'created_at' => $tech['created_at'] ?? date('Y-m-d H:i:s')
        ];
        
        $formattedTechnicians[] = $formatted;
    }
    
    $activeCount = count($formattedTechnicians);
    error_log("Bulunan aktif teknisyen sayısı: " . $activeCount);
    
    echo json_encode([
        'success' => true,
        'message' => "Teknisyenler başarıyla getirildi ({$activeCount} aktif)",
        'data' => [
            'technicians' => $formattedTechnicians,
            'active_count' => $activeCount,
            'sql_used' => $sql,
            'available_columns' => $columns
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("PDO Hatası: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage(),
        'error_type' => 'PDO_ERROR',
        'error_code' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Genel hata: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sunucu hatası: ' . $e->getMessage(),
        'error_type' => 'GENERAL_ERROR',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>