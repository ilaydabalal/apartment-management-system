<?php
try {
    $host = 'localhost';
    $dbname = 'apartment_management';
    $username = 'root'; 
    $password = ''; 
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Veritabanı bağlantı hatası: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/config/system_logger.php';

?>