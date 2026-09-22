<?php
require_once '../core/database.php';
require_once '../core/response.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$db = Database::getInstance();
$response = new Response();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $apartment = $_GET['apartment'] ?? '';
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $personalViewEnabled = isset($_GET['personal_view']) && $_GET['personal_view'] === 'true';
            $userName = $_GET['user_name'] ?? null;
            
            $whereConditions = ['au.status = 1']; 
            $params = [];
            
            if ($personalViewEnabled && !empty($userName)) {
                $whereConditions[] = "au.resident_name = ?";
                $params[] = $userName;
            }
            
            if ($search) {
                $whereConditions[] = "(au.resident_name LIKE ? OR au.owner_name LIKE ? OR au.unit_number LIKE ? OR a.name LIKE ?)";
                $searchParam = "%$search%";
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
            }
            
            if ($apartment) {
                $whereConditions[] = "a.name = ?";
                $params[] = $apartment;
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            $countSql = "SELECT COUNT(*) as total 
                        FROM apartment_units au
                        LEFT JOIN apartments a ON au.apartment_id = a.id
                        $whereClause";
            $totalResult = $db->fetch($countSql, $params);
            $total = $totalResult['total'];
            
            $sql = "SELECT 
                        au.id as unit_id,
                        au.unit_number,
                        au.floor,
                        au.resident_name,
                        au.owner_name,
                        au.resident_phone,
                        au.resident_email,
                        a.name as apartment_name,
                        COALESCE(SUM(d.amount), 0) as total_dues,
                        COALESCE(SUM(d.paid_amount), 0) as total_paid,
                        COALESCE(SUM(d.amount - d.paid_amount), 0) as total_debt,
                        CASE 
                            WHEN COALESCE(SUM(d.amount - d.paid_amount), 0) = 0 THEN 'Güncel'
                            WHEN COALESCE(SUM(d.amount - d.paid_amount), 0) > 1000 THEN 'Kritik'
                            ELSE 'Gecikmiş'
                        END as debt_status
                    FROM apartment_units au
                    LEFT JOIN apartments a ON au.apartment_id = a.id
                    LEFT JOIN dues d ON au.id = d.unit_id AND d.status = 1
                    $whereClause
                    GROUP BY au.id, au.unit_number, au.floor, au.resident_name, au.owner_name, 
                             au.resident_phone, au.resident_email, a.name
                    ORDER BY a.name, au.unit_number
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $debtStatus = $db->fetchAll($sql, $params);
            
            $statsSql = "SELECT 
                            debt_status,
                            COUNT(*) as count,
                            SUM(total_debt) as total_amount
                        FROM (
                            SELECT 
                                CASE 
                                    WHEN COALESCE(SUM(d.amount - d.paid_amount), 0) = 0 THEN 'Güncel'
                                    WHEN COALESCE(SUM(d.amount - d.paid_amount), 0) > 1000 THEN 'Kritik'
                                    ELSE 'Gecikmiş'
                                END as debt_status,
                                COALESCE(SUM(d.amount - d.paid_amount), 0) as total_debt
                            FROM apartment_units au
                            LEFT JOIN apartments a ON au.apartment_id = a.id
                            LEFT JOIN dues d ON au.id = d.unit_id AND d.status = 1
                            WHERE au.status = 1
                            GROUP BY au.id
                        ) as debt_summary
                        GROUP BY debt_status";
            $stats = $db->fetchAll($statsSql);
            
            $apartmentsSql = "SELECT DISTINCT a.name as apartment_name 
                             FROM apartments a 
                             INNER JOIN apartment_units au ON a.id = au.apartment_id 
                             WHERE au.status = 1 
                             ORDER BY a.name";
            $apartments = $db->fetchAll($apartmentsSql);
            
            echo json_encode([
                'success' => true,
                'data' => $debtStatus,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ],
                'stats' => $stats,
                'apartments' => array_column($apartments, 'apartment_name'),
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'apartment' => $apartment
                ]
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Sunucu hatası: ' . $e->getMessage()]);
}
?>