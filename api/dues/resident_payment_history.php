<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $configPath = dirname(__DIR__, 2) . '/config/database.php';
    if (!file_exists($configPath)) {
        throw new Exception('Database config file not found');
    }
    require_once $configPath;
    
    $residentName = $_GET['resident_name'] ?? '';
    $unitId = $_GET['unit_id'] ?? '';
    
    if (empty($residentName)) {
        throw new Exception('Sakin adı gerekli');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    $residentQuery = "
        SELECT DISTINCT 
            u.resident_phone,
            u.resident_email,
            a.name as apartment_name,
            u.unit_number,
            u.floor
        FROM units u
        LEFT JOIN apartments a ON u.apartment_id = a.id
        WHERE u.resident_name = :resident_name
    ";
    
    $queryParams = [':resident_name' => $residentName];
    
    if (!empty($unitId)) {
        $residentQuery .= " AND u.id = :unit_id";
        $queryParams[':unit_id'] = $unitId;
    }
    
    $residentStmt = $db->prepare($residentQuery);
    $residentStmt->execute($queryParams);
    $residentInfo = $residentStmt->fetch(PDO::FETCH_ASSOC);
    
    $summaryQuery = "
        SELECT 
            COALESCE(SUM(d.amount), 0) as total_dues,
            COALESCE(SUM(d.paid_amount), 0) as total_paid
        FROM dues d
        LEFT JOIN units u ON d.unit_id = u.id
        WHERE u.resident_name = :resident_name
    ";
    
    $summaryParams = [':resident_name' => $residentName];
    
    if (!empty($unitId)) {
        $summaryQuery .= " AND u.id = :unit_id";
        $summaryParams[':unit_id'] = $unitId;
    }
    
    $summaryStmt = $db->prepare($summaryQuery);
    $summaryStmt->execute($summaryParams);
    $paymentSummary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
    $paymentsQuery = "
        SELECT 
            d.id as due_id,
            d.due_type,
            d.amount as due_amount,
            d.paid_amount,
            d.period_year,
            d.period_month,
            d.due_date,
            d.created_at as due_created,
            pr.payment_date,
            pr.payment_method,
            pr.receipt_number,
            pr.notes as payment_notes,
            usr.full_name as created_by_name,
            CASE 
                WHEN d.paid_amount >= d.amount THEN 'Ödendi'
                WHEN d.paid_amount > 0 THEN 'Kısmi Ödendi'
                WHEN d.due_date < CURDATE() THEN 'Gecikmiş'
                ELSE 'Ödenmedi'
            END as payment_status,
            a.name as apartment_name,
            u.unit_number
        FROM dues d
        LEFT JOIN units u ON d.unit_id = u.id
        LEFT JOIN apartments a ON u.apartment_id = a.id
        LEFT JOIN payment_records pr ON d.id = pr.due_id
        LEFT JOIN users usr ON pr.created_by = usr.id
        WHERE u.resident_name = :resident_name
    ";
    
    $paymentParams = [':resident_name' => $residentName];
    
    if (!empty($unitId)) {
        $paymentsQuery .= " AND u.id = :unit_id";
        $paymentParams[':unit_id'] = $unitId;
    }
    
    $paymentsQuery .= " ORDER BY d.period_year DESC, d.period_month DESC, d.created_at DESC";
    
    $paymentsStmt = $db->prepare($paymentsQuery);
    $paymentsStmt->execute($paymentParams);
    $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $methodTranslations = [
        'cash' => 'Nakit',
        'bank_transfer' => 'Havale/EFT',
        'credit_card' => 'Kredi Kartı',
        'check' => 'Çek'
    ];
    
    foreach ($payments as &$payment) {
        if (isset($payment['payment_method'])) {
            $payment['payment_method_tr'] = $methodTranslations[$payment['payment_method']] ?? $payment['payment_method'];
        }
        
        if ($payment['payment_date']) {
            $payment['payment_date'] = date('d.m.Y', strtotime($payment['payment_date']));
        }
        if ($payment['due_date']) {
            $payment['due_date'] = date('d.m.Y', strtotime($payment['due_date']));
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'resident_info' => $residentInfo ?: [
                'resident_phone' => '-',
                'resident_email' => '-',
                'apartment_name' => 'Bilinmiyor',
                'unit_number' => 'Bilinmiyor',
                'floor' => 'Bilinmiyor'
            ],
            'payment_summary' => [
                'total_dues' => floatval($paymentSummary['total_dues'] ?? 0),
                'total_paid' => floatval($paymentSummary['total_paid'] ?? 0)
            ],
            'payments' => $payments ?: []
        ],
        'message' => 'Sakin ödeme geçmişi başarıyla yüklendi'
    ]);
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu',
        'error' => 'Database connection or query failed'
    ]);
    
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>