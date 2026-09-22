<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Sadece POST metodu desteklenir'
    ]);
    exit();
}

try {
    $host = 'localhost';
    $dbname = 'apartment_management';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı bağlantısı başarısız: ' . $e->getMessage()
    ]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$required_fields = ['apartment_id', 'unit_id', 'due_definition_id', 'period_year', 'period_month', 'amount', 'due_date'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Eksik alanlar: ' . implode(', ', $missing_fields),
        'required_fields' => $required_fields
    ]);
    exit();
}

try {
    $check_sql = "SELECT id FROM dues WHERE apartment_id = ? AND unit_id = ? AND due_definition_id = ? AND period_year = ? AND period_month = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([
        $input['apartment_id'],
        $input['unit_id'], 
        $input['due_definition_id'],
        $input['period_year'],
        $input['period_month']
    ]);
    
    if ($check_stmt->rowCount() > 0) {
        $existing_due = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Bu aidat zaten mevcut - mevcut kayıt döndürüldü',
            'data' => [
                'id' => $existing_due['id'],
                'existing' => true
            ]
        ]);
        exit();
    }
    
    $sql = "INSERT INTO dues (
        apartment_id, unit_id, due_definition_id, period_year, period_month, 
        amount, paid_amount, due_date, is_paid, payment_date, late_fee, 
        payment_receiver, notes, status, created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
    )";
    
    $stmt = $pdo->prepare($sql);
    
    $result = $stmt->execute([
        $input['apartment_id'],
        $input['unit_id'],
        $input['due_definition_id'],
        $input['period_year'],
        $input['period_month'],
        $input['amount'],
        $input['paid_amount'] ?? 0,
        $input['due_date'],
        $input['is_paid'] ?? 0,
        $input['payment_date'] ?? null,
        $input['late_fee'] ?? 0,
        $input['payment_receiver'] ?? null,
        $input['notes'] ?? 'Otomatik oluşturuldu',
        $input['status'] ?? 1
    ]);
    
    if ($result) {
        $due_id = $pdo->lastInsertId();
        
        $get_sql = "SELECT d.*, dd.name as due_type_name, au.unit_number, au.resident_name, a.name as apartment_name
                   FROM dues d
                   LEFT JOIN dues_definitions dd ON d.due_definition_id = dd.id
                   LEFT JOIN apartment_units au ON d.unit_id = au.id
                   LEFT JOIN apartments a ON d.apartment_id = a.id
                   WHERE d.id = ?";
        
        $get_stmt = $pdo->prepare($get_sql);
        $get_stmt->execute([$due_id]);
        $due_data = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Aidat başarıyla oluşturuldu',
            'data' => [
                'id' => $due_id,
                'due_info' => $due_data
            ]
        ]);
    } else {
        throw new Exception('Aidat kaydı oluşturulamadı');
    }
    
} catch (PDOException $e) {
    error_log("Aidat oluşturma PDO hatası: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası',
        'error_code' => $e->getCode(),
        'debug_info' => [
            'input_data' => $input,
            'sql_error' => $e->getMessage()
        ]
    ]);
} catch (Exception $e) {
    error_log("Aidat oluşturma genel hatası: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage(),
        'debug_info' => [
            'input_data' => $input
        ]
    ]);
}
?>