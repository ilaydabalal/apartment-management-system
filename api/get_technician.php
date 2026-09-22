<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$id = $_GET['id'] ?? 0;

try {
    $stmt = $conn->prepare("SELECT * FROM support_technicians WHERE id = ? AND status = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => true,
            'data' => $result->fetch_assoc()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Teknisyen bulunamadı.'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata oluştu: ' . $e->getMessage()
    ]);
}

$conn->close();
?>