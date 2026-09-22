<?php

require_once __DIR__ . '/config/database.php';

try {
    $db = new PDO('mysql:host=localhost;dbname=apartment_management', 'root', ''); 
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "SELECT id, username FROM users";
    $updated = 0;
    foreach ($db->query($sql) as $row) {
        $id = $row['id'];
        $username = $row['username'];
        $newPassword = $username . '123';
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $id]);
        $updated++;
    }
    echo "Tüm kullanıcıların şifresi kullanıcıadı123 olarak güncellendi. ($updated kullanıcı)";
} catch (Exception $e) {
    echo 'Hata: ' . $e->getMessage();
}

?>