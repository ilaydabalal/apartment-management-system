<?php
require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/response.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $userCount = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 1")->fetch(PDO::FETCH_ASSOC)['count'];
    
    $apartmentCount = $db->query("SELECT COUNT(*) as count FROM apartments WHERE status = 1")->fetch(PDO::FETCH_ASSOC)['count'];
    
    Response::success([
        'total_users' => (int)$userCount,
        'total_apartments' => (int)$apartmentCount,
        
    ]);
    
} catch (Exception $e) {
    Response::error('Dashboard istatistikleri alınamadı: ' . $e->getMessage());
} 