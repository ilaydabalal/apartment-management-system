<?php
/**
 * Ödeme Geçmişi API Endpoint
 * Apartman Yönetim Sistemi
 */

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

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        default:
            Response::error('Desteklenmeyen HTTP metodu', 405);
    }
} catch (Exception $e) {
    error_log('Payment History API Error: ' . $e->getMessage());
    Response::serverError('Bir hata oluştu: ' . $e->getMessage());
}

function handleGet($db) {
    error_log("DEBUG: handleGet çalıştı - " . date('Y-m-d H:i:s'));

    try {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 10);
        $apartmentId = $_GET['apartment_id'] ?? '';
        $unitId = $_GET['unit_id'] ?? '';
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['period_month'] ?? '';
        $residentName = $_GET['resident_name'] ?? '';
        
        $status = $_GET['status'] ?? '1'; 
        $excludeZeroAmount = isset($_GET['exclude_zero_amount']) && $_GET['exclude_zero_amount'] === 'true';
        
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;
        
        error_log('🔍 Payment History Parameters: ' . print_r($_GET, true));
        error_log('🔍 Status filter: ' . $status . ', Exclude zero: ' . ($excludeZeroAmount ? 'true' : 'false'));
        
        if (isset($_GET['id'])) {
            $dueId = (int)$_GET['id'];
            
            $sql = "SELECT * FROM dues_payment_history WHERE id = ?";
            
            if ($status === '1') {
                $sql = "SELECT * FROM dues_payment_history WHERE id = ? AND status = 1";
            }
            
            $due = $db->fetch($sql, [$dueId]);
            
            if (!$due) {
                Response::notFound('Aidat kaydı bulunamadı');
            }
            
            error_log('✅ Tek kayıt created_by_name: ' . ($due['created_by_name'] ?? 'NULL'));
            Response::success($due);
            return;
        }
        
        $whereClauses = [];
        $params = [];
        
try {
    $personalViewEnabled = isset($_GET['personal_view']) && $_GET['personal_view'] === 'true';
    $userName = $_GET['user_name'] ?? null;
    
    error_log("🔍 Personal view enabled: " . ($personalViewEnabled ? 'YES' : 'NO'));
    error_log("🔍 User name from JS: " . ($userName ?? 'NULL'));
    
    if ($personalViewEnabled && !empty($userName)) {
        $whereClauses[] = "resident_name = ?";
        $params[] = $userName;
        error_log('🔒 KİŞİSEL FİLTRE AKTİF: ' . $userName);
    } else {
        error_log('📋 TÜM AİDATLAR GÖSTERİLECEK');
        if ($personalViewEnabled && empty($userName)) {
            error_log('⚠️ Personal view true ama user_name boş');
        }
    }
    
} catch (Exception $e) {
    error_log('⚠️ Kişisel filtreleme hatası: ' . $e->getMessage());
}
        
        if ($status === '1') {
            $whereClauses[] = "status = 1";
            error_log('✅ Status=1 filtresi eklendi');
        } elseif ($status === '0') {
            $whereClauses[] = "status = 0";
            error_log('✅ Status=0 filtresi eklendi (silinmiş kayıtlar)');
        }
        
        if ($excludeZeroAmount) {
            $whereClauses[] = "(due_amount > 0 OR paid_amount > 0)";
            error_log('✅ Zero amount filtresi eklendi');
        }
        
        if (!empty($apartmentId)) {
            $whereClauses[] = "apartment_id = (SELECT id FROM apartments WHERE id = ?)";
            $params[] = $apartmentId;
        }
        
        if (!empty($unitId)) {
            $whereClauses[] = "unit_id = ?";
            $params[] = $unitId;
        }
        
        if (!empty($year)) {
            $whereClauses[] = "period_year = ?";
            $params[] = $year;
        }
        
        if (!empty($month)) {
            $whereClauses[] = "period_month = ?";
            $params[] = $month;
        }
        
        if (!empty($residentName)) {
            $whereClauses[] = "resident_name LIKE ?";
            $params[] = '%' . $residentName . '%';
        }
        
        $whereClauses[] = "resident_name IS NOT NULL";
        $whereClauses[] = "resident_name != ''";
        
        $whereClause = '';
        if (!empty($whereClauses)) {
            $whereClause = 'WHERE ' . implode(' AND ', $whereClauses);
        }
        
        error_log('🔍 Final WHERE clause: ' . $whereClause);
        
        $countSql = "SELECT COUNT(*) as total FROM dues_payment_history {$whereClause}";
        $totalResult = $db->fetch($countSql, $params);
        $total = $totalResult['total'];
        
        error_log('📊 Toplam kayıt sayısı: ' . $total);
        
        $sql = "SELECT 
                    id,
                    apartment_name,
                    unit_number,
                    resident_name,
                    due_type,
                    due_amount,
                    period_year,
                    period_month,
                    due_date,
                    is_paid,
                    payment_date,
                    paid_amount,
                    payment_method,
                    receipt_number,
                    created_by_name,
                    payment_status,
                    status
                FROM dues_payment_history
                {$whereClause}
                ORDER BY due_date DESC, unit_number ASC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        error_log('🔍 SQL: ' . $sql);
        error_log('🔍 Params: ' . print_r($params, true));
        
        $dues = $db->fetchAll($sql, $params);
        
        error_log('📊 Dönen kayıt sayısı: ' . count($dues));
        
        if (!empty($dues)) {
            $statusCounts = array_count_values(array_column($dues, 'status'));
            error_log('📊 Status dağılımı: ' . print_r($statusCounts, true));
            
            error_log('✅ İlk kayıt: ' . print_r($dues[0], true));
            error_log('✅ İlk kayıt created_by_name: ' . ($dues[0]['created_by_name'] ?? 'NULL'));
            
            foreach ($dues as $index => $due) {
                if (!isset($due['created_by_name']) || empty($due['created_by_name'])) {
                    $createdByQuery = "
                        SELECT 
                            COALESCE(
                                (SELECT u.full_name FROM users u JOIN payments p ON u.id = p.created_by WHERE p.due_id = ?),
                                (SELECT u.full_name FROM users u JOIN dues d ON u.id = CAST(d.payment_receiver AS UNSIGNED) WHERE d.id = ?),
                                'Sistem'
                            ) as created_by_name
                    ";
                    
                    $createdByResult = $db->fetch($createdByQuery, [$due['id'], $due['id']]);
                    $dues[$index]['created_by_name'] = $createdByResult['created_by_name'] ?? 'Sistem';
                    
                    error_log("🔧 Kayıt {$due['id']} için created_by_name düzeltildi: " . $dues[$index]['created_by_name']);
                }
            }
        } else {
            error_log('⚠️ Hiç kayıt bulunamadı - muhtemelen tüm kayıtlar status=0');
        }
        
        $pagination = [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_records' => $total,
            'per_page' => $limit
        ];
        
        Response::success([
            'data' => $dues,
            'pagination' => $pagination
        ]);
        
    } catch (Exception $e) {
        error_log('❌ Payment History Error: ' . $e->getMessage());
        error_log('❌ Stack trace: ' . $e->getTraceAsString());
        Response::serverError('Veri yüklenirken hata oluştu: ' . $e->getMessage());
    }
}
?>