<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../middleware/auth.php';

try {
    $user = authenticateUser();
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('acik', 'inceleniyor', 'islemde', 'beklemede') THEN 1 ELSE 0 END) as open,
            SUM(CASE WHEN status IN ('inceleniyor', 'islemde') THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'cozuldu' THEN 1 ELSE 0 END) as resolved
        FROM support_tickets
    ");
    
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $stats]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>