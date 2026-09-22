<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
    if ($method === 'POST') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $year = $data['year'] ?? date('Y');
        $apartmentId = $data['apartment_id'] ?? null;
        $paymentReceiver = $data['payment_receiver'] ?? 'Apartman Yöneticisi';
        
        $unitsSql = "SELECT au.id as unit_id, au.apartment_id, au.unit_number, au.resident_name, 
                            a.name as apartment_name
                     FROM apartment_units au 
                     LEFT JOIN apartments a ON au.apartment_id = a.id 
                     WHERE au.status = 1";
        $params = [];
        
        if ($apartmentId) {
            $unitsSql .= " AND au.apartment_id = ?";
            $params[] = $apartmentId;
        }
        
        $units = $db->fetchAll($unitsSql, $params);
        
        if (empty($units)) {
            Response::error('Aktif daire bulunamadı');
        }
        
        $dueTypes = [
            ['name' => 'Güvenlik Aidatı', 'amount' => 50.00],
            ['name' => 'Apartman Aidatı', 'amount' => 500.00]
        ];
        
        $createdCount = 0;
        $currentMonth = date('n');
        $currentYear = date('Y');
        
        foreach ($units as $unit) {
            foreach ($dueTypes as $dueType) {
                // Her ay için aidat oluştur
                for ($month = 1; $month <= 12; $month++) {
                    // Geçmiş aylar ödenmiş, gelecek aylar ödenmemiş
                    $isPaid = ($year < $currentYear) || ($year == $currentYear && $month < $currentMonth);
                    $paymentDate = $isPaid ? date('Y-m-d', strtotime("$year-$month-05")) : null;
                    $paidAmount = $isPaid ? $dueType['amount'] : 0;
                    $paymentStatus = $isPaid ? 'Ödendi' : 'Gecikmiş';
                    
                    $dueDefSql = "SELECT id FROM dues_definitions 
                                 WHERE apartment_id = ? AND name = ? AND status = 1";
                    $dueDef = $db->fetch($dueDefSql, [$unit['apartment_id'], $dueType['name']]);
                    
                    if (!$dueDef) {
                        $insertDefSql = "INSERT INTO dues_definitions (apartment_id, name, amount, due_type, status) 
                                       VALUES (?, ?, ?, 'monthly', 1)";
                        $db->execute($insertDefSql, [$unit['apartment_id'], $dueType['name'], $dueType['amount']]);
                        $dueDefId = $db->getConnection()->lastInsertId();
                    } else {
                        $dueDefId = $dueDef['id'];
                    }
                    
                    $existingDueSql = "SELECT id FROM dues 
                                     WHERE unit_id = ? AND due_definition_id = ? AND period_year = ? AND period_month = ? AND status = 1";
                    $existingDue = $db->fetch($existingDueSql, [$unit['unit_id'], $dueDefId, $year, $month]);
                    
                    if (!$existingDue) {
                        $dueDate = date('Y-m-d', strtotime("$year-$month-05"));
                        
                        $insertDueSql = "INSERT INTO dues (apartment_id, unit_id, due_definition_id, period_year, period_month, 
                                                          amount, paid_amount, due_date, is_paid, payment_date, payment_receiver, status) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                        $db->execute($insertDueSql, [
                            $unit['apartment_id'],
                            $unit['unit_id'],
                            $dueDefId,
                            $year,
                            $month,
                            $dueType['amount'],
                            $paidAmount,
                            $dueDate,
                            $isPaid ? 1 : 0,
                            $paymentDate,
                            $paymentReceiver
                        ]);
                        
                        $createdCount++;
                        
                        // Eğer ödenmişse ödeme kaydı da oluştur
                        if ($isPaid) {
                            $dueId = $db->getConnection()->lastInsertId();
                            $receiptNumber = 'RCP' . date('Ymd') . str_pad($dueId, 6, '0', STR_PAD_LEFT);
                            
                            $insertPaymentSql = "INSERT INTO payments (apartment_id, unit_id, due_id, amount, payment_method, 
                                                                       payment_date, receipt_number, status) 
                                               VALUES (?, ?, ?, ?, 'bank_transfer', ?, ?, 1)";
                            $db->execute($insertPaymentSql, [
                                $unit['apartment_id'],
                                $unit['unit_id'],
                                $dueId,
                                $dueType['amount'],
                                $paymentDate,
                                $receiptNumber
                            ]);
                        }
                    }
                }
            }
        }
        
        Response::success("$createdCount adet aidat kaydı oluşturuldu", [
            'created_count' => $createdCount,
            'year' => $year,
            'units_processed' => count($units)
        ]);
        
    } else {
        Response::error('Geçersiz istek', 405);
    }
} catch (Exception $e) {
    error_log('Generate Dues API Error: ' . $e->getMessage());
    Response::serverError('Aidat oluşturulurken hata: ' . $e->getMessage());
} 