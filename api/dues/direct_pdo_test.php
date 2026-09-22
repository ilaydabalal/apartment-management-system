<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $host = 'localhost';
    $dbname = 'apartment_management';  // Muhtemelen bu olabilir
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        $dueId = (int)$data['id'];
        
        $stmt = $pdo->prepare("SELECT id, paid_amount, payment_method, payment_date FROM dues WHERE id = ?");
        $stmt->execute([$dueId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $sql = "UPDATE dues SET paid_amount = ?, payment_method = ?, payment_date = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        $result = $stmt->execute([
            floatval($data['paid_amount'] ?? 0),
            $data['payment_method'] ?? 'cash',
            $data['payment_date'] ?? '2025-08-05',
            $dueId
        ]);
        
        $stmt = $pdo->prepare("SELECT id, paid_amount, payment_method, payment_date FROM dues WHERE id = ?");
        $stmt->execute([$dueId]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Direkt PDO ile güncelleme başarılı!',
            'data' => [
                'before' => $current,
                'after' => $updated,
                'affected_rows' => $stmt->rowCount()
            ]
        ]);
        
    } else {
        $stmt = $pdo->prepare("SELECT id, paid_amount, payment_method FROM dues WHERE id = 203");
        $stmt->execute();
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Direkt PDO GET test',
            'data' => $current
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'PDO Hatası: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>