<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$db_paths = [
    '../config/database.php',
    '../config/db.php',
    'config/database.php',
    'config/db.php'
];

$conn = null;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            if (isset($conn) && $conn) {
                break;
            }
        } catch (Exception $e) {
            continue;
        }
    }
}

if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı bağlantısı kurulamadı'
    ]);
    exit;
}

try {
    $query = "SELECT * FROM support_technicians WHERE status = 1 ORDER BY name";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Sorgu hatası: " . $conn->error);
    }
    
    $technicians = [];
    while ($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $technicians
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Teknisyenler yüklenirken hata oluştu: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>