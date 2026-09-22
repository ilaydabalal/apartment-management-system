<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
require_once '../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz JSON']);
    exit;
}

$name = $input['name'] ?? '';
$address = $input['address'] ?? '';
$total_units = $input['total_units'] ?? 0;
$manager_id = $input['manager_id'] ?? null;
$tax_number = $input['tax_number'] ?? '';
$phone = $input['phone'] ?? '';
$email = $input['email'] ?? '';
$status = 1;
$now = date('Y-m-d H:i:s');

if (!$name || !$address || !$total_units) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Zorunlu alanlar eksik']);
    exit;
}

try {
    $db = Database::getInstance();
    $sql = "INSERT INTO apartments (name, address, total_units, manager_id, tax_number, phone, email, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $db->execute($sql, [$name, $address, $total_units, $manager_id, $tax_number, $phone, $email, $status, $now, $now]);
    echo json_encode(['success' => true, 'message' => 'Apartman eklendi']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası', 'error' => $e->getMessage()]);
} 