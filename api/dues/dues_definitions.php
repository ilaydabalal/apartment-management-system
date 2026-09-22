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

error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            Response::error('Desteklenmeyen HTTP metodu', 405);
    }
} catch (Exception $e) {
    error_log('Dues Definitions API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    Response::serverError('Bir hata oluştu: ' . $e->getMessage());
}

function handleGet() {
    global $db;
    
    $apartmentId = $_GET['apartment_id'] ?? '';
    $status = isset($_GET['status']) ? (int)$_GET['status'] : 1;
    
    if (isset($_GET['id'])) {
        $definitionId = (int)$_GET['id'];
        $sql = "SELECT dd.*, a.name as apartment_name
                FROM dues_definitions dd
                LEFT JOIN apartments a ON dd.apartment_id = a.id
                WHERE dd.id = ? AND dd.status >= ?";
        
        $definition = $db->fetch($sql, [$definitionId, $status]);
        
        if (!$definition) {
            Response::notFound('Aidat tanımı bulunamadı');
        }
        
        Response::success($definition);
    }
    
    $whereClauses = ["dd.status >= ?"];
    $params = [$status];
    
    if (!empty($apartmentId)) {
        $whereClauses[] = "dd.apartment_id = ?";
        $params[] = $apartmentId;
    }
    
    $whereClause = implode(' AND ', $whereClauses);
    
$sql = "SELECT 
MIN(dd.id) as id,
dd.name,
dd.amount,
dd.due_type,
dd.description,
MIN(dd.apartment_id) as apartment_id,
MIN(a.name) as apartment_name,
COUNT(d.id) as usage_count
FROM dues_definitions dd
LEFT JOIN apartments a ON dd.apartment_id = a.id
LEFT JOIN dues d ON dd.id = d.due_definition_id
WHERE {$whereClause}
GROUP BY dd.name, dd.amount, dd.due_type
ORDER BY dd.due_type ASC, dd.name ASC";

    $definitions = $db->fetchAll($sql, $params);
    
    $dueTypes = [];
    foreach ($definitions as $definition) {
        $dueTypes[] = [
            'id' => $definition['id'],
            'name' => $definition['name'],
            'amount' => $definition['amount'],
            'due_type' => $definition['due_type'],
            'description' => $definition['description'],
            'apartment_id' => $definition['apartment_id'],
            'apartment_name' => $definition['apartment_name'],
            'usage_count' => $definition['usage_count']
        ];
    }
    
    Response::success([
        'due_types' => $dueTypes,
        'data' => $definitions,
        'total' => count($definitions)
    ], 'Aidat tanımları başarıyla getirildi');
}


function handlePost() {
    global $db;
    
    try {
        $json = file_get_contents('php://input');
        error_log('Raw POST data: ' . $json);
        
        if (empty($json)) {
            Response::error('POST verisi bulunamadı');
        }
        
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            Response::error('Geçersiz JSON formatı: ' . json_last_error_msg());
        }
        
        if (!$data) {
            Response::error('Geçersiz veri formatı');
        }
        
        $errors = validateDefinitionData($data);
        if (!empty($errors)) {
            Response::validationError($errors);
        }
        
        $existingDefinition = $db->fetch(
            "SELECT id FROM dues_definitions WHERE name = ? AND apartment_id = ? AND status = 1",
            [$data['name'], $data['apartment_id']]
        );
        
        if ($existingDefinition) {
            Response::error('Bu isimde bir aidat tanımı zaten mevcut', 409);
        }
        
        $sql = "INSERT INTO dues_definitions (apartment_id, name, amount, due_type, description, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())";
        
        $insertParams = [
            $data['apartment_id'],
            $data['name'],
            $data['amount'],
            $data['due_type'] ?? 'monthly',
            $data['description'] ?? ''
        ];
        
        $definitionId = $db->insert($sql, $insertParams);
        
        if (!$definitionId) {
            Response::serverError('Aidat tanımı eklenemedi');
        }
        
        $newDefinition = $db->fetch(
            "SELECT dd.*, a.name as apartment_name
             FROM dues_definitions dd 
             LEFT JOIN apartments a ON dd.apartment_id = a.id 
             WHERE dd.id = ?",
            [$definitionId]
        );
        
        Response::success($newDefinition, 'Aidat tanımı başarıyla eklendi', 201);
        
    } catch (Exception $e) {
        error_log('handlePost exception: ' . $e->getMessage());
        error_log('Exception trace: ' . $e->getTraceAsString());
        Response::serverError('Database hatası: ' . $e->getMessage());
    }
}

function handlePut() {
    global $db;
    
    try {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['id'])) {
            Response::error('Geçersiz veri formatı');
        }
        
        $definitionId = (int)$data['id'];
        
        $existingDefinition = $db->fetch(
            "SELECT * FROM dues_definitions WHERE id = ? AND status = 1",
            [$definitionId]
        );
        
        if (!$existingDefinition) {
            Response::notFound('Aidat tanımı bulunamadı');
        }
        
        $errors = validateDefinitionData($data, $definitionId);
        if (!empty($errors)) {
            Response::validationError($errors);
        }
        
        $duplicateDefinition = $db->fetch(
            "SELECT id FROM dues_definitions WHERE name = ? AND apartment_id = ? AND id != ? AND status = 1",
            [$data['name'], $data['apartment_id'], $definitionId]
        );
        
        if ($duplicateDefinition) {
            Response::error('Bu isimde başka bir aidat tanımı zaten mevcut', 409);
        }
        
        $updateFields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $updateFields[] = "name = ?";
            $params[] = $data['name'];
        }
        
        if (isset($data['amount'])) {
            $updateFields[] = "amount = ?";
            $params[] = $data['amount'];
        }
        
        if (isset($data['due_type'])) {
            $updateFields[] = "due_type = ?";
            $params[] = $data['due_type'];
        }
        
        if (isset($data['description'])) {
            $updateFields[] = "description = ?";
            $params[] = $data['description'];
        }
        
        $updateFields[] = "updated_at = NOW()";
        $params[] = $definitionId;
        
        $sql = "UPDATE dues_definitions SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $result = $db->execute($sql, $params);
        
        if ($result === false) {
            Response::error('Aidat tanımı güncellenemedi');
        }
        
        $updatedDefinition = $db->fetch(
            "SELECT dd.*, a.name as apartment_name
             FROM dues_definitions dd 
             LEFT JOIN apartments a ON dd.apartment_id = a.id 
             WHERE dd.id = ?",
            [$definitionId]
        );
        
        Response::success($updatedDefinition, 'Aidat tanımı başarıyla güncellendi');
        
    } catch (Exception $e) {
        error_log('handlePut exception: ' . $e->getMessage());
        Response::serverError('Güncelleme hatası: ' . $e->getMessage());
    }
}

function handleDelete() {
    global $db;
    
    try {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            $id = $data['id'] ?? null;
        }
        
        if (!$id) {
            Response::error('Aidat tanımı ID gereklidir');
        }
        
        $definitionId = (int)$id;
        
        $definition = $db->fetch(
            "SELECT id, name FROM dues_definitions WHERE id = ? AND status = 1",
            [$definitionId]
        );
        
        if (!$definition) {
            Response::notFound('Aidat tanımı bulunamadı');
        }
        
        $usageCount = $db->fetch(
            "SELECT COUNT(*) as count FROM dues WHERE due_definition_id = ? AND status = 1",
            [$definitionId]
        );
        
        if ($usageCount['count'] > 0) {
            Response::error('Bu aidat tanımı kullanımda olduğu için silinemez. Önce bu tanımı kullanan aidatları silmeniz gerekir.');
        }
        
        $result = $db->execute(
            "UPDATE dues_definitions SET status = 0, updated_at = NOW() WHERE id = ?",
            [$definitionId]
        );
        
        if ($result === false) {
            Response::error('Aidat tanımı silinemedi');
        }
        
        Response::success(null, 'Aidat tanımı başarıyla silindi');
        
    } catch (Exception $e) {
        error_log('handleDelete exception: ' . $e->getMessage());
        Response::serverError('Silme hatası: ' . $e->getMessage());
    }
}

function validateDefinitionData($data, $definitionId = null) {
    $errors = [];
    
    if (empty($data['apartment_id'])) {
        $errors['apartment_id'] = 'Apartman seçimi gereklidir';
    } else {
        global $db;
        $apartment = $db->fetch("SELECT id FROM apartments WHERE id = ? AND status = 1", [$data['apartment_id']]);
        if (!$apartment) {
            $errors['apartment_id'] = 'Geçersiz apartman seçimi';
        }
    }
    
    if (empty($data['name'])) {
        $errors['name'] = 'Aidat tanım adı gereklidir';
    } elseif (strlen(trim($data['name'])) < 2) {
        $errors['name'] = 'Aidat tanım adı en az 2 karakter olmalıdır';
    }
    
    if (empty($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
        $errors['amount'] = 'Geçerli bir miktar giriniz';
    }
    
    if (isset($data['due_type'])) {
        $validTypes = ['monthly', 'quarterly', 'yearly', 'one_time'];
        if (!in_array($data['due_type'], $validTypes)) {
            $errors['due_type'] = 'Geçersiz aidat türü';
        }
    }
    
    return $errors;
}
?>