<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once '../../config/database.php';
require_once '../core/database.php';

try {
    $db = Database::getInstance();
    $sql = "SELECT * FROM apartments WHERE status = 1 ORDER BY created_at DESC";
    $apartments = $db->fetchAll($sql);
    echo json_encode([
        'success' => true,
        'data' => $apartments
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası',
        'error' => $e->getMessage()
    ]);
} 