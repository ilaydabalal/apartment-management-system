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
require_once '../core/auth.php';
require_once '../core/response.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/apartment-management/debug.log');

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];


error_log('🔄 Method detected: ' . $method);

try {
    switch ($method) {
        case 'GET':
            error_log('✅ GET method - calling handleGet');
            handleGet($db);
            break;
        case 'POST':
            error_log('✅ POST method - calling handlePost');
            handlePost($db);
            break;
        case 'PUT':
            error_log('✅ PUT method - calling handlePut');
            handlePut($db);
            break;
        case 'DELETE':
            error_log('✅ DELETE method - calling handleDelete');
            handleDelete($db);
            break;
        default:
            error_log('❌ Unsupported method: ' . $method);
            Response::error('Desteklenmeyen HTTP metodu: ' . $method, 405);
    }
} catch (Exception $e) {
    error_log('❌ Aidatlar API Exception: ' . $e->getMessage());
    error_log('❌ Stack trace: ' . $e->getTraceAsString());
    Response::serverError('Bir hata oluştu: ' . $e->getMessage());
}

function handleDelete($db) {
    error_log('🗑️ handleDelete fonksiyonu çağrıldı!');
    
    try {
    
        error_log('🔍 DELETE Request Debug:');
        error_log('📥 $_GET: ' . print_r($_GET, true));
        error_log('📥 $_POST: ' . print_r($_POST, true));
        error_log('📥 Request Method: ' . $_SERVER['REQUEST_METHOD']);
        error_log('📥 Request URI: ' . $_SERVER['REQUEST_URI']);
        
    
        $rawInput = file_get_contents('php://input');
        error_log('📥 Raw Input: ' . $rawInput);
        
    
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            $id = $data['id'] ?? null;
            error_log('📥 JSON decoded data: ' . print_r($data, true));
        }
        
        error_log('🔍 Extracted ID: ' . ($id ?? 'NULL'));
        
        if (!$id) {
            error_log('❌ ID bulunamadı!');
            Response::error('Aidat ID gereklidir');
            return;
        }
        
        $dueId = (int)$id;
        error_log('🗑️ Aidat soft delete başlatılıyor, ID: ' . $dueId);
        
    
        $due = $db->fetch(
            "SELECT id, is_paid, amount, paid_amount, unit_id, period_year, period_month, status FROM dues WHERE id = ?",
            [$dueId]
        );
        
        error_log('🔍 Veritabanından gelen kayıt: ' . print_r($due, true));
        
        if (!$due) {
            error_log('❌ Aidat kaydı bulunamadı: ' . $dueId);
            Response::notFound('Aidat kaydı bulunamadı');
            return;
        }
        
    
        if ($due['status'] == 0) {
            error_log('⚠️ Zaten silinmiş aidat: ' . $dueId);
            Response::error('Bu aidat zaten silinmiş');
            return;
        }
        
    
        error_log('🔄 Soft delete işlemi başlatılıyor...');
        $result = $db->execute(
            "UPDATE dues SET status = 0, updated_at = NOW() WHERE id = ?",
            [$dueId]
        );
        
        error_log('🔍 Update result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        
        if ($result === false) {
            error_log('❌ Aidat soft delete başarısız: ' . $dueId);
            Response::error('Aidat silinemedi');
            return;
        }
        
        $updatedDue = $db->fetch("SELECT status FROM dues WHERE id = ?", [$dueId]);
        error_log('🔍 Updated due status: ' . ($updatedDue['status'] ?? 'NULL'));
        
        error_log('✅ Aidat soft delete başarılı: ' . $dueId);
        
        Response::success([
            'deleted_due_id' => $dueId,
            'action' => 'soft_delete',
            'status' => 'deactivated'
        ], 'Aidat kaydı başarıyla kaldırıldı');
        
    } catch (Exception $e) {
        error_log('❌ handleDelete exception: ' . $e->getMessage());
        error_log('❌ Stack trace: ' . $e->getTraceAsString());
        Response::serverError('Silme hatası: ' . $e->getMessage());
    }
}


function handleGet($db) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $apartmentId = $_GET['apartment_id'] ?? '';
    $unitId = $_GET['unit_id'] ?? '';
    $year = $_GET['year'] ?? date('Y');
    $month = $_GET['month'] ?? '';
    $isPaid = $_GET['is_paid'] ?? '';
    $status = $_GET['status'] ?? '1';
    
    $page = max(1, $page);
    $limit = min(100, max(1, $limit));
    $offset = ($page - 1) * $limit;
    

    try {
        require_once '../core/auth.php';
        $auth = new Auth();
        $currentUser = $auth->getCurrentUser();
        
        $hasPersonalPermission = false;
        
        if ($currentUser && isset($currentUser['permissions'])) {
            $userPermissions = json_decode($currentUser['permissions'], true);
            if (isset($userPermissions['dues']) && is_array($userPermissions['dues'])) {
                $hasPersonalPermission = in_array('view_personal', $userPermissions['dues']);
            }
        }
    } catch (Exception $e) {
        $hasPersonalPermission = false;
        $currentUser = null;
    }
    
    if (isset($_GET['id'])) {
        $dueId = (int)$_GET['id'];
        
        $sql = "SELECT * FROM dues_payment_history WHERE id = ?";
        $params = [$dueId];
        
        if ($hasPersonalPermission && $currentUser && isset($currentUser['full_name'])) {
            $sql .= " AND resident_name = ?";
            $params[] = $currentUser['full_name'];
        }
        
        $due = $db->fetch($sql, $params);
        
        if (!$due) {
            Response::notFound('Aidat kaydı bulunamadı');
            return;
        }
        
        Response::success($due);
        return;
    }
    
    $whereClauses = ["status >= ?"];
    $params = [$status];
    
    if ($hasPersonalPermission && $currentUser && isset($currentUser['full_name'])) {
        $whereClauses[] = "resident_name = ?";
        $params[] = $currentUser['full_name'];
    }
    
    if (!empty($apartmentId)) {
        $whereClauses[] = "apartment_id = ?";
        $params[] = $apartmentId;
    }
    
    if (!empty($unitId)) {
        $whereClauses[] = "unit_id = ?";
        $params[] = $unitId;
    }
    
    if (!empty($year)) {
        $whereClauses[] = "period_year = ?";
        $params[] = $year;
    }
    
    if (!empty($month)) {
        $whereClauses[] = "period_month = ?";
        $params[] = $month;
    }
    
    if ($isPaid !== '') {
        $whereClauses[] = "is_paid = ?";
        $params[] = $isPaid;
    }
    
    $whereClause = implode(' AND ', $whereClauses);
    
    $countSql = "SELECT COUNT(*) as total FROM dues_payment_history WHERE {$whereClause}";
    $totalResult = $db->fetch($countSql, $params);
    $total = $totalResult['total'];
    
    $sql = "SELECT 
                id,
                apartment_name,
                unit_number,
                resident_name,
                due_type,
                due_amount,
                period_year,
                period_month,
                due_date,
                is_paid,
                payment_date,
                paid_amount,
                payment_method,
                receipt_number,
                created_by_name,
                payment_status
            FROM dues_payment_history
            WHERE {$whereClause}
            ORDER BY due_date DESC, unit_number ASC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $dues = $db->fetchAll($sql, $params);
    
    $summaryData = calculateSummary($db, $apartmentId, $year, $month);
    
    Response::paginated([
        'dues' => $dues,
        'summary' => $summaryData
    ], $total, $page, $limit);
}

function debugViewStructure($db) {
    try {
        $sql = "SHOW COLUMNS FROM dues_payment_history";
        $columns = $db->fetchAll($sql);
        error_log('🔍 VIEW Kolonları:');
        foreach ($columns as $column) {
            error_log('  - ' . $column['Field'] . ' (' . $column['Type'] . ')');
        }
    } catch (Exception $e) {
        error_log('❌ VIEW yapısı okunamadı: ' . $e->getMessage());
    }
}

function handlePost($db) {
    try {
        $json = file_get_contents('php://input');
        error_log('🔍 Raw POST data: ' . $json);
        
        if (empty($json)) {
            error_log('❌ POST verisi boş');
            Response::error('POST verisi bulunamadı');
        }
        
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('❌ JSON decode error: ' . json_last_error_msg());
            Response::error('Geçersiz JSON formatı: ' . json_last_error_msg());
        }
        
        error_log('📋 Parsed data: ' . print_r($data, true));
        
        if (!$data) {
            error_log('❌ Data boş');
            Response::error('Geçersiz veri formatı');
        }
        
        $errors = validateDueData($data, $db);
        if (!empty($errors)) {
            error_log('❌ Validation errors: ' . print_r($errors, true));
            Response::validationError($errors);
        }
        
        error_log('✅ Validation başarılı');
        
        $db->beginTransaction();
        error_log('📊 Transaction başladı');
        
        if (isset($data['due_definition_id']) && !empty($data['due_definition_id'])) {
            $dueDefinitionId = $data['due_definition_id'];
            error_log('📋 Mevcut due_definition_id kullanılıyor: ' . $dueDefinitionId);
            
            $definitionExists = $db->fetch(
                "SELECT id, amount FROM dues_definitions WHERE id = ? AND status = 1",
                [$dueDefinitionId]
            );
            
            if (!$definitionExists) {
                error_log('❌ Due definition bulunamadı: ' . $dueDefinitionId);
                $db->rollback();
                Response::error('Seçilen aidat tanımı bulunamadı');
            }
            
            error_log('✅ Due definition bulundu: ' . print_r($definitionExists, true));
            
            if (empty($data['amount'])) {
                $data['amount'] = $definitionExists['amount'];
                error_log('💰 Amount tanımdan alındı: ' . $data['amount']);
            }
        }
        
        $unitIds = $data['unit_ids'] ?? [];
        error_log('🏠 Unit IDs: ' . print_r($unitIds, true));
        
        if (empty($unitIds)) {
            $units = $db->fetchAll(
                "SELECT id FROM apartment_units WHERE apartment_id = ? AND status = 1",
                [$data['apartment_id']]
            );
            $unitIds = array_column($units, 'id');
            error_log('🏠 Tüm daireler seçildi: ' . print_r($unitIds, true));
        }
        
        if (empty($unitIds)) {
            error_log('❌ Hiç daire bulunamadı');
            $db->rollback();
            Response::error('Aidat uygulanacak daire bulunamadı');
        }
        
        $createdDues = [];
        $skippedDues = [];
        
        foreach ($unitIds as $unitId) {
            error_log("🔄 Daire işleniyor: $unitId");
            
            $existingDue = $db->fetch(
                "SELECT id FROM dues WHERE unit_id = ? AND due_definition_id = ? AND period_year = ? AND period_month = ? AND status = 1",
                [$unitId, $dueDefinitionId, $data['period_year'], $data['period_month']]
            );
            
            if (!$existingDue) {
                error_log("➕ Yeni aidat oluşturuluyor daire: $unitId");
                
                $sql = "INSERT INTO dues (apartment_id, unit_id, due_definition_id, period_year, period_month, amount, due_date, payment_receiver, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                
                $insertParams = [
                    $data['apartment_id'],
                    $unitId,
                    $dueDefinitionId,
                    $data['period_year'],
                    $data['period_month'],
                    $data['amount'],
                    $data['due_date'],
                    $data['payment_receiver'] ?? null
                ];
                
                error_log('💾 Insert SQL: ' . $sql);
                error_log('💾 Insert Params: ' . print_r($insertParams, true));
                
                $dueId = $db->insert($sql, $insertParams);
                
                if ($dueId) {
                    $createdDues[] = $dueId;
                    error_log("✅ Aidat oluşturuldu ID: $dueId");
                } else {
                    error_log("❌ Aidat oluşturulamadı daire: $unitId");
                }
            } else {
                error_log("⚠️ Aidat zaten mevcut daire: $unitId");
                $skippedDues[] = $unitId;
            }
        }
        
        $db->commit();
        error_log('✅ Transaction committed');
        
        $message = 'Aidatlar başarıyla oluşturuldu';
        if (!empty($skippedDues)) {
            $message .= '. ' . count($skippedDues) . ' daire için aynı dönemde aidat zaten mevcut olduğu için atlandı.';
        }
        
        $result = [
            'due_definition_id' => $dueDefinitionId,
            'created_dues' => $createdDues,
            'skipped_units' => $skippedDues,
            'total_created' => count($createdDues),
            'total_skipped' => count($skippedDues)
        ];
        
        error_log('📤 Final result: ' . print_r($result, true));
        
        Response::success($result, $message, 201);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log('❌ Exception: ' . $e->getMessage());
        error_log('❌ Stack trace: ' . $e->getTraceAsString());
        throw $e;
    }
}

function handlePut($db) {
    try {
        error_log('🔄 handlePut başladı');
        
        $json = file_get_contents('php://input');
        error_log('📥 PUT Raw data: ' . $json);
        
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['id'])) {
            error_log('❌ Geçersiz veri formatı veya ID eksik');
            Response::error('Geçersiz veri formatı');
        }
        
        $dueId = (int)$data['id'];
        error_log('🔍 İşlenecek aidat ID: ' . $dueId);
        
        $pdo = $db->getConnection();
        
        $pdo->beginTransaction();
        error_log('📊 Transaction başladı');
        
        $duesSQL = "UPDATE dues SET 
                    amount = ?, 
                    paid_amount = ?, 
                    payment_date = ?, 
                    is_paid = ?,
                    payment_receiver = ?,
                    late_fee = ?,
                    notes = ?,
                    updated_at = NOW() 
                    WHERE id = ?";
        
        $stmt = $pdo->prepare($duesSQL);
        $updateResult = $stmt->execute([
            floatval($data['amount'] ?? 0),
            floatval($data['paid_amount'] ?? 0),
            $data['payment_date'] ?? null,
            intval($data['is_paid'] ?? 0),
            $data['payment_receiver'] ?? null,
            floatval($data['late_fee'] ?? 0),
            $data['notes'] ?? null,
            $dueId
        ]);
        
        if (!$updateResult) {
            error_log('❌ Dues tablosu güncellenemedi');
            throw new Exception('Dues tablosu güncellenemedi');
        }
        
        error_log('✅ Dues tablosu güncellendi');
        
        $paidAmount = floatval($data['paid_amount'] ?? 0);
        
        if ($paidAmount > 0) {
            error_log('💰 Ödeme mevcut, payments işlemi yapılıyor: ' . $paidAmount);
            
            $existingPaymentSQL = "SELECT id, receipt_number FROM payments WHERE due_id = ?";
            $existingPayment = $pdo->prepare($existingPaymentSQL);
            $existingPayment->execute([$dueId]);
            $paymentRecord = $existingPayment->fetch(PDO::FETCH_ASSOC);
            
            if ($paymentRecord) {
                error_log('🔄 Mevcut payment güncelleniyor: ' . $paymentRecord['id']);
                
                $paymentSQL = "UPDATE payments SET 
                               amount = ?, 
                               payment_method = ?, 
                               payment_date = ?, 
                               created_by = ?,
                               updated_at = NOW()
                               WHERE due_id = ?";
                
                $stmt = $pdo->prepare($paymentSQL);
                $paymentUpdateResult = $stmt->execute([
                    $paidAmount,
                    $data['payment_method'] ?? 'cash',
                    $data['payment_date'] ?? date('Y-m-d'),
                    $data['payment_receiver'] ?? null,
                    $dueId
                ]);
                
                if (!$paymentUpdateResult) {
                    error_log('❌ Payment güncelleme başarısız');
                    throw new Exception('Payment güncelleme başarısız');
                }
                
                error_log('✅ Payment güncellendi - created_by: ' . ($data['payment_receiver'] ?? 'null'));
                
                if (empty($paymentRecord['receipt_number'])) {
                    $receiptNumber = generateReceiptNumber($pdo);
                    $pdo->prepare("UPDATE payments SET receipt_number = ? WHERE due_id = ?")
                        ->execute([$receiptNumber, $dueId]);
                    error_log('📄 Yeni makbuz numarası oluşturuldu: ' . $receiptNumber);
                }
                
            } else {
                error_log('➕ Yeni payment kaydı oluşturuluyor');
                
                $dueInfoSQL = "SELECT apartment_id, unit_id FROM dues WHERE id = ?";
                $dueInfo = $pdo->prepare($dueInfoSQL);
                $dueInfo->execute([$dueId]);
                $dueData = $dueInfo->fetch(PDO::FETCH_ASSOC);
                
                if (!$dueData) {
                    error_log('❌ Aidat bilgisi bulunamadı');  
                    throw new Exception('Aidat bilgisi bulunamadı');
                }
                
                $receiptNumber = generateReceiptNumber($pdo);
                error_log('📄 Makbuz numarası oluşturuldu: ' . $receiptNumber);
                
                $paymentSQL = "INSERT INTO payments 
                               (apartment_id, unit_id, due_id, amount, payment_method, payment_date, receipt_number, created_by, created_at, updated_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                
                $stmt = $pdo->prepare($paymentSQL);
                $insertResult = $stmt->execute([
                    $dueData['apartment_id'],
                    $dueData['unit_id'],
                    $dueId,
                    $paidAmount,
                    $data['payment_method'] ?? 'cash',
                    $data['payment_date'] ?? date('Y-m-d'),
                    $receiptNumber,
                    $data['payment_receiver'] ?? null
                ]);
                
                if (!$insertResult) {
                    error_log('❌ Payment oluşturma başarısız');
                    throw new Exception('Payment oluşturma başarısız');
                }
                
                error_log('✅ Yeni payment kaydı oluşturuldu - created_by: ' . ($data['payment_receiver'] ?? 'null'));
            }
        } else {
            error_log('🗑️ Ödeme yok, mevcut payments siliniyor');
            
            $deleteResult = $pdo->prepare("DELETE FROM payments WHERE due_id = ?")->execute([$dueId]);
            
            if ($deleteResult) {
                error_log('✅ Mevcut payment kaydı silindi');
            }
        }
        
        $pdo->commit();
        error_log('✅ Transaction committed');
        
        $stmt = $pdo->prepare("SELECT * FROM dues_payment_history WHERE id = ?");
        $stmt->execute([$dueId]);
        $updatedDue = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$updatedDue) {
            error_log('⚠️ VIEW\'dan veri alınamadı, düz dues tablosundan alınıyor');
            
            $stmt = $pdo->prepare("SELECT d.*, 
                                          au.unit_number, au.resident_name, 
                                          a.name as apartment_name,
                                          COALESCE(
                                              (SELECT u.full_name FROM users u WHERE u.id = d.payment_receiver),
                                              d.payment_receiver,
                                              'Sistem'
                                          ) as created_by_name
                                   FROM dues d 
                                   LEFT JOIN apartment_units au ON d.unit_id = au.id 
                                   LEFT JOIN apartments a ON d.apartment_id = a.id 
                                   WHERE d.id = ?");
            $stmt->execute([$dueId]);
            $updatedDue = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        error_log('📤 Başarılı sonuç - created_by_name: ' . ($updatedDue['created_by_name'] ?? 'null'));
        
        Response::success($updatedDue, 'Aidat ve ödeme başarıyla güncellendi');
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollback();
        }
        error_log('❌ handlePut exception: ' . $e->getMessage());
        error_log('❌ Stack trace: ' . $e->getTraceAsString());
        Response::serverError('Güncelleme hatası: ' . $e->getMessage());
    }
}

function generateReceiptNumber($pdo) {
    $year = date('Y');
    
    try {
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(receipt_number, 10) AS UNSIGNED)) as last_number 
                              FROM payments 
                              WHERE receipt_number LIKE CONCAT('MKB-', ?, '-%')");
        $stmt->execute([$year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $nextNumber = ($result['last_number'] ?? 0) + 1;
        
        $receiptNumber = sprintf('MKB-%d-%03d', $year, $nextNumber);
        error_log('📄 Yeni makbuz numarası: ' . $receiptNumber);
        
        return $receiptNumber;
    } catch (Exception $e) {
        error_log('❌ Makbuz numarası oluşturma hatası: ' . $e->getMessage());
        return 'MKB-' . $year . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    }
}


function getDeletedDues($db, $apartmentId = null, $limit = 50) {
    try {
        $whereClauses = ["d.status = 0"];
        $params = [];
        
        if ($apartmentId) {
            $whereClauses[] = "d.apartment_id = ?";
            $params[] = $apartmentId;
        }
        
        $whereClause = implode(' AND ', $whereClauses);
        
        $sql = "SELECT 
                    d.id,
                    d.apartment_id,
                    d.unit_id,
                    d.amount,
                    d.paid_amount,
                    d.period_year,
                    d.period_month,
                    d.updated_at as deleted_at,
                    au.unit_number,
                    au.resident_name,
                    a.name as apartment_name,
                    dd.name as due_type
                FROM dues d
                LEFT JOIN apartment_units au ON d.unit_id = au.id
                LEFT JOIN apartments a ON d.apartment_id = a.id
                LEFT JOIN dues_definitions dd ON d.due_definition_id = dd.id
                WHERE {$whereClause}
                ORDER BY d.updated_at DESC
                LIMIT ?";
        
        $params[] = $limit;
        
        return $db->fetchAll($sql, $params);
        
    } catch (Exception $e) {
        error_log('❌ getDeletedDues hatası: ' . $e->getMessage());
        return [];
    }
}

function restoreDue($db, $dueId) {
    try {
        $result = $db->execute(
            "UPDATE dues SET status = 1, updated_at = NOW() WHERE id = ? AND status = 0",
            [$dueId]
        );
        
        if ($result) {
            error_log('♻️ Aidat geri yüklendi: ' . $dueId);
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log('❌ restoreDue hatası: ' . $e->getMessage());
        return false;
    }
}

function permanentDeleteDue($db, $dueId) {
    try {
        // Önce payments kayıtlarını sil
        $db->execute("DELETE FROM payments WHERE due_id = ?", [$dueId]);
        
        // Sonra due kaydını sil
        $result = $db->execute("DELETE FROM dues WHERE id = ? AND status = 0", [$dueId]);
        
        if ($result) {
            error_log('🔥 Aidat kalıcı olarak silindi: ' . $dueId);
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log('❌ permanentDeleteDue hatası: ' . $e->getMessage());
        return false;
    }
}

function validateDueData($data, $db) {
    $errors = [];
    
    if (empty($data['apartment_id'])) {
        $errors['apartment_id'] = 'Apartman seçimi gereklidir';
    } else {
        $apartment = $db->fetch("SELECT id FROM apartments WHERE id = ? AND status = 1", [$data['apartment_id']]);
        if (!$apartment) {
            $errors['apartment_id'] = 'Geçersiz apartman seçimi';
        }
    }
    
    if (empty($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
        $errors['amount'] = 'Geçerli bir miktar giriniz';
    }
    
    if (empty($data['period_year']) || !is_numeric($data['period_year'])) {
        $errors['period_year'] = 'Geçerli bir yıl giriniz';
    }
    
    if (empty($data['period_month']) || !is_numeric($data['period_month']) || 
        $data['period_month'] < 1 || $data['period_month'] > 12) {
        $errors['period_month'] = 'Geçerli bir ay giriniz (1-12)';
    }
    
    if (empty($data['due_date'])) {
        $errors['due_date'] = 'Vade tarihi gereklidir';
    } elseif (!strtotime($data['due_date'])) {
        $errors['due_date'] = 'Geçerli bir tarih formatı giriniz (Y-m-d)';
    }
    
    return $errors;
}

function calculateSummary($db, $apartmentId = null, $year = null, $month = null) {
    $whereClauses = ["d.status = 1"];
    $params = [];
    
    if ($apartmentId) {
        $whereClauses[] = "d.apartment_id = ?";
        $params[] = $apartmentId;
    }
    
    if ($year) {
        $whereClauses[] = "d.period_year = ?";
        $params[] = $year;
    }
    
    if ($month) {
        $whereClauses[] = "d.period_month = ?";
        $params[] = $month;
    }
    
    $whereClause = implode(' AND ', $whereClauses);
    
    $sql = "SELECT 
                COUNT(*) as total_dues,
                COUNT(CASE WHEN d.is_paid = 1 THEN 1 END) as paid_dues,
                COUNT(CASE WHEN d.is_paid = 0 THEN 1 END) as unpaid_dues,
                SUM(d.amount) as total_amount,
                SUM(d.paid_amount) as paid_amount,
                SUM(CASE WHEN d.is_paid = 0 THEN (d.amount - d.paid_amount + d.late_fee) END) as debt_amount,
                SUM(d.late_fee) as total_late_fee
            FROM dues d
            WHERE {$whereClause}";
    
    $summary = $db->fetch($sql, $params);
    
    foreach ($summary as $key => $value) {
        if ($summary[$key] === null) {
            $summary[$key] = 0;
        }
    }
    
    $summary['payment_rate'] = $summary['total_dues'] > 0 
        ? round(($summary['paid_dues'] / $summary['total_dues']) * 100, 2) 
        : 0;
    
    return $summary;
}

?>