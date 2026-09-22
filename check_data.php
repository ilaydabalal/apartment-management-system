<?php
require_once 'config/database.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Veritabanı Verileri</h2>";
    
    // Apartmanlar
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM apartments");
    $apartments = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Apartmanlar:</strong> {$apartments['count']} adet</p>";
    
    // Kullanıcılar
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 1");
    $users = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Aktif Kullanıcılar:</strong> {$users['count']} adet</p>";
    
    // Daireler
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM apartment_units");
    $units = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Daireler:</strong> {$units['count']} adet</p>";
    
    // Aidatlar
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM dues");
    $dues = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Aidatlar:</strong> {$dues['count']} adet</p>";
    
    // Gelirler
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM incomes");
    $incomes = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Gelirler:</strong> {$incomes['count']} adet</p>";
    
    // Giderler
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM expenses");
    $expenses = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Giderler:</strong> {$expenses['count']} adet</p>";
    
} catch (PDOException $e) {
    echo "<h2>Hata!</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?> 