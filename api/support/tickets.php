<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../middleware/auth.php';

try {
    $user = authenticateUser();
    
    $personalViewEnabled = isset($_GET['personal_view']) && $_GET['personal_view'] === 'true';
    $userName = $_GET['user_name'] ?? null;

    error_log("🎫 Personal view enabled: " . ($personalViewEnabled ? 'YES' : 'NO'));
    error_log("🎫 User name from JS: " . ($userName ?? 'NULL'));

    $whereConditions = [];
    $whereParams = [];

    if ($personalViewEnabled && !empty($userName)) {
        $whereConditions[] = "st.requester_name = ?";
        $whereParams[] = $userName;
        error_log('🔒 KİŞİSEL DESTEK FİLTRESİ AKTİF: ' . $userName);
    } else {
        error_log('📋 TÜM DESTEK TALEPLERİ GÖSTERİLECEK');
        if ($personalViewEnabled && empty($userName)) {
            error_log('⚠️ Personal view true ama user_name boş');
        }
    }

    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    error_log('🎫 Final WHERE clause: ' . $whereClause);
    error_log('🎫 WHERE params: ' . print_r($whereParams, true));
    
    $stmt = $pdo->prepare("
        SELECT 
            st.*,
            sc.category_name,
            tech.name as assigned_name
        FROM support_tickets st
        LEFT JOIN support_categories sc ON st.category = sc.category_key
        LEFT JOIN support_technicians tech ON st.assigned_technician_id = tech.id
        $whereClause
        ORDER BY st.created_at DESC
    ");
    
    $stmt->execute($whereParams);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log('🎫 Toplam bulunan ticket sayısı: ' . count($tickets));
    
    if ($personalViewEnabled && !empty($tickets)) {
        $uniqueRequesters = array_unique(array_column($tickets, 'requester_name'));
        error_log('🎫 Bulunan talep eden isimler: ' . implode(', ', $uniqueRequesters));
    }
    
    foreach ($tickets as &$ticket) {
        $photoStmt = $pdo->prepare("
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
        ");
        $photoStmt->execute([$ticket['id']]);
        $photosData = $photoStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $photos = [];
        foreach ($photosData as $photo) {
            $photos[] = [
                'id' => $photo['id'],
                'url' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/apartment-management/' . $photo['photo_path'],
                'path' => $photo['photo_path'],
                'original_name' => $photo['original_name'],
                'file_size' => $photo['file_size'],
                'mime_type' => $photo['mime_type'],
                'uploaded_at' => $photo['uploaded_at']
            ];
        }
        
        $ticket['photos'] = $photos;
        $ticket['photo_count'] = count($photos);
    }

    $responseData = [
        'success' => true, 
        'data' => [
            'tickets' => $tickets,
            'total_count' => count($tickets)
        ]
    ];
    
    if ($personalViewEnabled) {
        $responseData['debug'] = [
            'personal_filter_active' => true,
            'filtered_for_user' => $userName,
            'total_tickets' => count($tickets)
        ];
    }
    
    echo json_encode($responseData);
    
} catch (Exception $e) {
    error_log('❌ Tickets API hatası: ' . $e->getMessage());
    error_log('❌ Stack trace: ' . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>