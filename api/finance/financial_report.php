<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../core/database.php';
require_once '../core/response.php';

$db = Database::getInstance();

try {
    $sql = "SELECT * FROM financial_report_view ORDER BY report_year DESC, report_month DESC";
    $reports = $db->fetchAll($sql);
    Response::success($reports);
} catch (Exception $e) {
    error_log('Financial Report API Error: ' . $e->getMessage());
    Response::serverError('Mali raporlar alınamadı');
} 