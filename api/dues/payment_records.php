<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../core/database.php';
require_once '../core/response.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $apartmentId = $_GET['apartment_id'] ?? '';
        $unitNumber = $_GET['unit_number'] ?? '';
        $residentName = $_GET['resident_name'] ?? '';
        $paymentMethod = $_GET['payment_method'] ?? '';
        $month = $_GET['month'] ?? '';
        $receiptNumber = $_GET['receipt_number'] ?? '';

        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where = ['p.status = 1'];
        $params = [];
        
        if ($apartmentId !== '') {
            $where[] = 'p.apartment_id = ?';
            $params[] = $apartmentId;
        }
        if ($unitNumber !== '') {
            $where[] = 'au.unit_number LIKE ?';
            $params[] = "%$unitNumber%";
        }
        if ($residentName !== '') {
            $where[] = 'au.resident_name LIKE ?';
            $params[] = "%$residentName%";
        }
        if ($paymentMethod !== '') {
            $where[] = 'p.payment_method = ?';
            $params[] = $paymentMethod;
        }
        if ($month !== '') {
            $where[] = 'MONTH(p.payment_date) = ?';
            $params[] = $month;
        }
        if ($receiptNumber !== '') {
            $where[] = 'p.receipt_number LIKE ?';
            $params[] = "%$receiptNumber%";
        }
        
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) as total 
                     FROM payments p
                     LEFT JOIN apartment_units au ON p.unit_id = au.id
                     LEFT JOIN apartments a ON p.apartment_id = a.id
                     LEFT JOIN dues d ON p.due_id = d.id
                     LEFT JOIN dues_definitions dd ON d.due_definition_id = dd.id
                     LEFT JOIN users u ON p.created_by = u.id
                     $whereSql";
        $totalResult = $db->fetch($countSql, $params);
        $total = $totalResult['total'] ?? 0;

        $sql = "SELECT p.id, p.amount, p.payment_method, p.payment_date, p.receipt_number, p.notes,
                       a.name as apartment_name, au.unit_number, au.resident_name,
                       dd.name as due_type, d.period_year, d.period_month, d.due_date,
                       u.full_name as created_by_name
                FROM payments p
                LEFT JOIN apartment_units au ON p.unit_id = au.id
                LEFT JOIN apartments a ON p.apartment_id = a.id
                LEFT JOIN dues d ON p.due_id = d.id
                LEFT JOIN dues_definitions dd ON d.due_definition_id = dd.id
                LEFT JOIN users u ON p.created_by = u.id
                $whereSql
                ORDER BY p.payment_date DESC, p.created_at DESC
                LIMIT ? OFFSET ?";
        $paramsWithLimit = array_merge($params, [$limit, $offset]);
        $rows = $db->fetchAll($sql, $paramsWithLimit);

        $methodMap = [
            'cash' => 'Nakit',
            'bank_transfer' => 'Havale/EFT',
            'credit_card' => 'Kredi Kartı',
            'check' => 'Çek'
        ];

        foreach ($rows as &$row) {
            $row['payment_method_tr'] = $methodMap[$row['payment_method']] ?? $row['payment_method'];
        }

        Response::paginated($rows, $total, $page, $limit);
        
    } elseif ($method === 'POST') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data) {
            Response::error('Geçersiz JSON formatı');
        }
        
        // Zorunlu alanlar
        $required = ['apartment_id', 'unit_id', 'due_id', 'amount', 'payment_date'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                Response::error("$field alanı gereklidir");
            }
        }
        
        $due = $db->fetch("SELECT * FROM dues WHERE id = ? AND status = 1", [$data['due_id']]);
        if (!$due) {
            Response::error('Aidat bulunamadı');
        }
        
        $unit = $db->fetch("SELECT * FROM apartment_units WHERE id = ? AND status = 1", [$data['unit_id']]);
        if (!$unit) {
            Response::error('Daire bulunamadı');
        }
        
        $receiptNumber = $data['receipt_number'] ?? 'RCP' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $insertSql = "INSERT INTO payments (apartment_id, unit_id, due_id, amount, payment_method, payment_date, receipt_number, notes, created_by, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        $db->execute($insertSql, [
            $data['apartment_id'],
            $data['unit_id'],
            $data['due_id'],
            $data['amount'],
            $data['payment_method'] ?? 'cash',
            $data['payment_date'],
            $receiptNumber,
            $data['notes'] ?? null,
            $data['created_by'] ?? null
        ]);
        
        $paymentId = $db->getConnection()->lastInsertId();
        
        $updateDueSql = "UPDATE dues SET 
                         paid_amount = paid_amount + ?, 
                         is_paid = CASE WHEN (paid_amount + ?) >= amount THEN 1 ELSE 0 END,
                         payment_date = CASE WHEN (paid_amount + ?) >= amount THEN ? ELSE payment_date END,
                         updated_at = NOW()
                         WHERE id = ?";
        $db->execute($updateDueSql, [
            $data['amount'],
            $data['amount'],
            $data['amount'],
            $data['payment_date'],
            $data['due_id']
        ]);
        
        Response::success('Ödeme kaydı başarıyla oluşturuldu', [
            'payment_id' => $paymentId,
            'receipt_number' => $receiptNumber
        ]);
        
    } elseif ($method === 'PUT') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['id'])) {
            Response::error('Ödeme ID gereklidir');
        }
        
        $paymentId = (int)$data['id'];
        
        $payment = $db->fetch("SELECT * FROM payments WHERE id = ? AND status = 1", [$paymentId]);
        if (!$payment) {
            Response::error('Ödeme kaydı bulunamadı');
        }
        
        $updatableFields = ['amount', 'payment_method', 'payment_date', 'notes'];
        $updateData = [];
        $params = [];
        
        foreach ($updatableFields as $field) {
            if (isset($data[$field])) {
                $updateData[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updateData)) {
            Response::error('Güncellenecek alan bulunamadı');
        }
        
        $updateData[] = "updated_at = NOW()";
        $params[] = $paymentId;
        
        $sql = "UPDATE payments SET " . implode(', ', $updateData) . " WHERE id = ?";
        $db->execute($sql, $params);
        
        Response::success('Ödeme kaydı başarıyla güncellendi');
        
    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            Response::error('Ödeme ID gereklidir');
        }
        
        $payment = $db->fetch("SELECT * FROM payments WHERE id = ? AND status = 1", [$id]);
        if (!$payment) {
            Response::error('Ödeme kaydı bulunamadı');
        }
        
        $sql = "UPDATE payments SET status = 0, updated_at = NOW() WHERE id = ?";
        $db->execute($sql, [$id]);
        
        Response::success('Ödeme kaydı başarıyla silindi');
        
    } else {
        Response::error('Geçersiz istek', 405);
    }
} catch (Exception $e) {
    error_log('Payment Records API Error: ' . $e->getMessage());
    Response::serverError('İşlem sırasında hata: ' . $e->getMessage());
} 