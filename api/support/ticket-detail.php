<?php
require_once 'config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Sadece GET metodu desteklenir');
    }
    
    $ticketId = $_GET['id'] ?? null;
    if (!$ticketId) {
        throw new Exception('Talep ID gerekli');
    }
    
    $headers = getallheaders();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    }
    
    if (!$token) {
        throw new Exception('Token gerekli');
    }
    
    $userData = validateJWT($token);
    if (!$userData) {
        throw new Exception('Geçersiz token');
    }
    
    $pdo = new PDO($dsn, $username, $password, $options);
    $userRole = $userData['role_name'];
    $userId = $userData['id'];
    
    $ticketQuery = "
        SELECT 
            st.*,
            u.full_name as user_name,
            u.email as user_email,
            u.phone as user_phone,
            a.full_name as assigned_name,
            a.email as assigned_email,
            a.phone as assigned_phone
        FROM support_tickets st
        LEFT JOIN users u ON st.user_id = u.id
        LEFT JOIN users a ON st.assigned_to = a.id
        WHERE st.id = ?
    ";
    
    $params = [$ticketId];
    
    if ($userRole === 'Daire Sakini') {
        $ticketQuery .= " AND st.user_id = ?";
        $params[] = $userId;
    }
    
    $stmt = $pdo->prepare($ticketQuery);
    $stmt->execute($params);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        throw new Exception('Talep bulunamadı veya erişim yetkiniz yok');
    }
    
    $ticket['category_name'] = getCategoryName($ticket['category']);
    $ticket['status_name'] = getStatusName($ticket['status']);
    $ticket['priority_name'] = getPriorityName($ticket['priority']);
    
$photoQuery = "
SELECT 
    id,
    photo_path,
    original_name,
    file_size,
    mime_type,
    uploaded_at
FROM support_ticket_photos 
WHERE ticket_id = ? 
ORDER BY uploaded_at ASC
";

$photoStmt = $pdo->prepare($photoQuery);
$photoStmt->execute([$ticketId]);
$photos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($photos as &$photo) {
$photo['url'] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/apartment-management/' . $photo['photo_path'];
$photo['file_size_mb'] = round($photo['file_size'] / (1024 * 1024), 2);
}

$ticket['photos'] = $photos;
    
    $historyQuery = "
        SELECT 
            sth.*,
            u.full_name as user_name,
            u.email as user_email,
            r.name as user_role
        FROM support_ticket_history sth
        LEFT JOIN users u ON sth.user_id = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE sth.ticket_id = ?
        ORDER BY sth.created_at DESC
    ";
    
    $historyStmt = $pdo->prepare($historyQuery);
    $historyStmt->execute([$ticketId]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($history as &$item) {
        $item['action_text'] = getActionText($item['action_type'], $item['old_value'], $item['new_value']);
        $item['formatted_date'] = date('d.m.Y H:i', strtotime($item['created_at']));
        
        if ($item['action_type'] === 'status_changed') {
            $oldStatus = $item['old_value'] ? getStatusName($item['old_value']) : '';
            $newStatus = $item['new_value'] ? getStatusName($item['new_value']) : '';
            $item['action_text'] = "Durum değiştirildi: {$oldStatus} → {$newStatus}";
        }
    }
    
    $technicians = [];
    if ($userRole !== 'Daire Sakini') {
        $techQuery = "
            SELECT 
                u.id,
                u.full_name,
                u.email,
                st.specialization,
                COUNT(at.id) as active_tickets,
                st.max_concurrent_tickets
            FROM support_technicians st
            JOIN users u ON st.user_id = u.id
            LEFT JOIN support_tickets at ON u.id = at.assigned_to 
                AND at.status IN ('acik', 'inceleniyor', 'islemde')
            WHERE st.is_active = TRUE
            GROUP BY u.id
            ORDER BY u.full_name ASC
        ";
        
        $techStmt = $pdo->prepare($techQuery);
        $techStmt->execute();
        $technicians = $techStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($technicians as &$tech) {
            if ($tech['specialization']) {
                $specializations = explode(',', $tech['specialization']);
                $tech['specialization_names'] = array_map('getCategoryName', $specializations);
            } else {
                $tech['specialization_names'] = ['Genel'];
            }
            $tech['is_available'] = $tech['active_tickets'] < $tech['max_concurrent_tickets'];
        }
    }
    
    $resolutionTime = null;
    if ($ticket['status'] === 'cozuldu' && $ticket['resolved_at']) {
        $created = new DateTime($ticket['created_at']);
        $resolved = new DateTime($ticket['resolved_at']);
        $interval = $created->diff($resolved);
        
        $resolutionTime = [
            'days' => $interval->days,
            'hours' => $interval->h,
            'minutes' => $interval->i,
            'total_hours' => round($interval->days * 24 + $interval->h + $interval->i / 60, 1),
            'formatted' => formatResolutionTime($interval)
        ];
    }
    
    $similarQuery = "
        SELECT 
            id,
            ticket_number,
            title,
            status,
            priority,
            created_at
        FROM support_tickets 
        WHERE category = ? 
            AND id != ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY created_at DESC 
        LIMIT 5
    ";
    
    $similarStmt = $pdo->prepare($similarQuery);
    $similarStmt->execute([$ticket['category'], $ticketId]);
    $similarTickets = $similarStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($similarTickets as &$similar) {
        $similar['status_name'] = getStatusName($similar['status']);
        $similar['priority_name'] = getPriorityName($similar['priority']);
    }
    
    $response = [
        'success' => true,
        'data' => [
            'ticket' => $ticket,
            'photos' => $photos,
            'history' => $history,
            'technicians' => $technicians,
            'similar_tickets' => $similarTickets,
            'resolution_time' => $resolutionTime,
            'user_permissions' => [
                'can_edit' => ($userRole !== 'Daire Sakini' || $ticket['user_id'] == $userId),
                'can_change_status' => ($userRole !== 'Daire Sakini'),
                'can_assign' => ($userRole !== 'Daire Sakini'),
                'can_delete' => ($userRole === 'Süper Admin'),
                'can_add_photos' => true,
                'can_comment' => true
            ],
            'stats' => [
                'photo_count' => count($photos),
                'history_count' => count($history),
                'days_open' => floor((time() - strtotime($ticket['created_at'])) / (24 * 60 * 60))
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getCategoryName($category) {
    $names = [
        'elektrik' => 'Elektrik',
        'su_tesisati' => 'Su Tesisatı',
        'isi_sogutucu' => 'Isı/Soğutucu',
        'asansor' => 'Asansör',
        'guvenlik' => 'Güvenlik',
        'temizlik' => 'Temizlik',
        'ortak_alan' => 'Ortak Alan',
        'diger' => 'Diğer'
    ];
    return $names[$category] ?? $category;
}

function getStatusName($status) {
    $names = [
        'acik' => 'Açık',
        'inceleniyor' => 'İnceleniyor',
        'islemde' => 'İşlemde',
        'beklemede' => 'Beklemede',
        'cozuldu' => 'Çözüldü',
        'iptal' => 'İptal'
    ];
    return $names[$status] ?? $status;
}

function getPriorityName($priority) {
    $names = [
        'dusuk' => 'Düşük',
        'orta' => 'Orta',
        'yuksek' => 'Yüksek',
        'acil' => 'Acil'
    ];
    return $names[$priority] ?? $priority;
}

function getActionText($actionType, $oldValue = null, $newValue = null) {
    switch ($actionType) {
        case 'created':
            return 'Talep oluşturuldu';
        case 'status_changed':
            return 'Durum değiştirildi';
        case 'assigned':
            return 'Talep atandı';
        case 'comment_added':
            return 'Yorum eklendi';
        case 'photo_added':
            return 'Fotoğraf eklendi';
        case 'resolved':
            return 'Talep çözüldü';
        case 'reopened':
            return 'Talep yeniden açıldı';
        default:
            return ucfirst($actionType);
    }
}

function formatResolutionTime($interval) {
    if ($interval->days > 0) {
        return $interval->days . ' gün ' . $interval->h . ' saat';
    } elseif ($interval->h > 0) {
        return $interval->h . ' saat ' . $interval->i . ' dakika';
    } else {
        return $interval->i . ' dakika';
    }
}
?>