<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $stmt = $pdo->prepare("SELECT category_key, category_name FROM support_categories ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => ['categories' => $categories]]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>