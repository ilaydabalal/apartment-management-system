<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ob_clean();

try {
    require_once '../core/database.php';
    $db = Database::getInstance();
    $sql = "SELECT * FROM expense_categories WHERE status = 1 ORDER BY name ASC";
    $categories = $db->fetchAll($sql);
    echo json_encode(['success' => true, 'data' => ['categories' => $categories]]);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Sunucu hatası: ' . $e->getMessage()]);
    exit;
} 