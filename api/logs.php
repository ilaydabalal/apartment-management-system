<?php


header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

error_reporting(0);
ini_set('display_errors', 0);

try {
    $host = 'localhost';
    $dbname = 'apartment_management';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetLogs($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Desteklenmeyen HTTP metodu'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database bağlantı hatası: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Sunucu hatası: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function handleGetLogs($pdo) {
    try {
        $limit = (int)($_GET['limit'] ?? 100);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_logs'");
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            echo json_encode([
                'success' => false,
                'message' => 'system_logs tablosu bulunamadı'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM system_logs");
        $countResult = $stmt->fetch();
        $total = $countResult['total'];
        
        if ($total == 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Henüz log kaydı yok',
                'data' => [],
                'total_count' => 0
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $query = "
            SELECT 
                sl.id, 
                sl.user_id, 
                sl.action, 
                sl.module, 
                sl.record_id,
                sl.old_data, 
                sl.new_data, 
                sl.ip_address, 
                sl.description,
                sl.created_at,
                COALESCE(u.username, 'Sistem') as user_name,
                COALESCE(u.full_name, 'Sistem Kullanıcısı') as user_full_name
            FROM system_logs sl 
            LEFT JOIN users u ON sl.user_id = u.id
            ORDER BY sl.created_at DESC 
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($logs as &$log) {
            foreach ($log as $key => $value) {
                if ($value === null) {
                    $log[$key] = '';
                }
            }
            
            if ($log['old_data'] && $log['old_data'] !== '') {
                $decoded = json_decode($log['old_data'], true);
                if ($decoded !== null) {
                    $log['old_data_parsed'] = $decoded;
                }
            }
            
            if ($log['new_data'] && $log['new_data'] !== '') {
                $decoded = json_decode($log['new_data'], true);
                if ($decoded !== null) {
                    $log['new_data_parsed'] = $decoded;
                }
            }
            
            if ($log['created_at']) {
                $log['created_at_formatted'] = date('d.m.Y H:i:s', strtotime($log['created_at']));
            }
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $logs,
            'total_count' => $total,
            'filtered_count' => count($logs)
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Log hatası: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
?>