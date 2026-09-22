<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../config/system_logger.php'; 

try {
    $auth = new Auth();
    $token = $auth->getTokenFromRequest();
    if(!$token || !$auth->validateToken($token)) {
        echo json_encode(['success' => false, 'message' => 'Yetkilendirme hatası']);
        exit;
    }

    $payload = $auth->validateToken($token);
    $user_id = $payload['user_id'] ?? null;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $ticket_id = $input['ticket_id'];
    $priority = $input['priority'];
    $status = $input['status'];
    $assigned_technician_id = $input['assigned_technician_id'];
    
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $username = $userInfo['username'] ?? 'Bilinmiyor';
    $userFullName = $userInfo['full_name'] ?? $username;

    $stmt = $pdo->prepare("
        SELECT t.ticket_number, t.title, t.priority, t.status, t.assigned_technician_id,
               st.name as old_assigned_name
        FROM support_tickets t
        LEFT JOIN support_technicians st ON t.assigned_technician_id = st.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $oldTicket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldTicket) {
        echo json_encode(['success' => false, 'message' => 'Talep bulunamadı']);
        exit;
    }

    $changes = [];
    $priorityNames = ['dusuk' => 'Düşük', 'orta' => 'Orta', 'yuksek' => 'Yüksek', 'acil' => 'Acil'];
    $statusNames = ['acik' => 'Açık', 'inceleniyor' => 'İnceleniyor', 'islemde' => 'İşlemde', 'beklemede' => 'Beklemede', 'cozuldu' => 'Çözüldü', 'iptal' => 'İptal'];
    
    if ($oldTicket['priority'] !== $priority) {
        $changes['oncelik'] = [
            'old' => $priorityNames[$oldTicket['priority']] ?? $oldTicket['priority'],
            'new' => $priorityNames[$priority] ?? $priority
        ];
    }
    
    if ($oldTicket['status'] !== $status) {
        $changes['durum'] = [
            'old' => $statusNames[$oldTicket['status']] ?? $oldTicket['status'],
            'new' => $statusNames[$status] ?? $status
        ];
    }
    
    if ($oldTicket['assigned_technician_id'] != $assigned_technician_id) {
        $newAssignedName = '';
        if ($assigned_technician_id) {
            $techStmt = $pdo->prepare("SELECT name FROM support_technicians WHERE id = ?");
            $techStmt->execute([$assigned_technician_id]);
            $newAssignedName = $techStmt->fetchColumn();
        }
        
        $changes['atanan_teknisyen'] = [
            'old' => $oldTicket['old_assigned_name'] ?? 'Atanmamış',
            'new' => $newAssignedName ?: 'Atanmamış'
        ];
    }

    $stmt = $pdo->prepare("
        UPDATE support_tickets 
        SET priority = ?, status = ?, assigned_technician_id = ?, updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$priority, $status, $assigned_technician_id, $ticket_id]);
    
    $assigned_name = '';
    if ($assigned_technician_id) {
        $techStmt = $pdo->prepare("SELECT name FROM support_technicians WHERE id = ?");
        $techStmt->execute([$assigned_technician_id]);
        $assigned_name = $techStmt->fetchColumn();
    }

    $logResult = false;
    if (!empty($changes)) {
        $oldData = [
            'priority' => $oldTicket['priority'],
            'status' => $oldTicket['status'],
            'assigned_technician_id' => $oldTicket['assigned_technician_id']
        ];
        
        $newData = [
            'priority' => $priority,
            'status' => $status,
            'assigned_technician_id' => $assigned_technician_id
        ];
        
        $logResult = logSupportTicketUpdated(
            $pdo,
            $user_id,
            $username,
            $userFullName,
            $ticket_id,
            $oldTicket['ticket_number'],
            $oldData,
            $newData,
            $changes
        );
        
        error_log("✅ Destek talebi güncellendi ve loglandı: {$oldTicket['ticket_number']} by $username");
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Talep güncellendi',
        'data' => [
            'assigned_name' => $assigned_name,
            'changes_logged' => !empty($changes),
            'log_result' => $logResult
        ]
    ]);
    
} catch (Exception $e) {
    error_log("❌ Destek talebi güncelleme hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>