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
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        error_log('PUT Input: ' . $input);
        error_log('PUT Data: ' . print_r($data, true));
        
        require_once '../core/database.php';
        $db = Database::getInstance();
        
        $dueId = (int)$data['id'];
        
        $current = $db->fetch("SELECT * FROM dues WHERE id = ?", [$dueId]);
        error_log('Current Data: ' . print_r($current, true));
        
        $sql = "UPDATE dues SET 
                paid_amount = ?, 
                payment_method = ?, 
                payment_date = ?, 
                payment_receiver = ?,
                updated_at = NOW() 
                WHERE id = ?";
        
        $params = [
            floatval($data['paid_amount'] ?? 0),
            $data['payment_method'] ?? null,
            $data['payment_date'] ?? null,
            $data['payment_receiver'] ?? null,
            $dueId
        ];
        
        error_log('Update SQL: ' . $sql);
        error_log('Update Params: ' . print_r($params, true));
        
        $result = $db->execute($sql, $params);
        error_log('Update Result: ' . $result);
        
        $updated = $db->fetch("SELECT * FROM dues WHERE id = ?", [$dueId]);
        error_log('Updated Data: ' . print_r($updated, true));
        
        echo json_encode([
            'success' => true,
            'message' => 'Güncelleme başarılı',
            'data' => [
                'affected_rows' => $result,
                'current' => $current,
                'updated' => $updated
            ]
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Sadece PUT metodu destekleniyor'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Test Update Error: ' . $e->getMessage());
    error_log('Stack: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>