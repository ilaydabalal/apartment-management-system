<?php
require_once 'api/core/database.php';

$db = Database::getInstance();

try {
    // Kullanıcıları ve rollerini getir
    $users = $db->fetchAll("
        SELECT u.id, u.username, u.email, u.full_name, u.status, 
               r.id as role_id, r.name as role_name
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        ORDER BY u.id
    ");
    
    echo "=== KULLANICILAR ===\n";
    foreach ($users as $user) {
        echo "ID: {$user['id']} | Username: {$user['username']} | Role: {$user['role_name']} | Status: {$user['status']}\n";
    }
    
    echo "\n=== ROLLER ===\n";
    $roles = $db->fetchAll("SELECT id, name, description FROM roles ORDER BY id");
    foreach ($roles as $role) {
        echo "ID: {$role['id']} | Name: {$role['name']} | Description: {$role['description']}\n";
    }
    
    echo "\n=== TEST GİRİŞLERİ ===\n";
    $testUsers = [
        ['username' => 'admin', 'password' => 'admin123'],
        ['username' => 'muhasebeci', 'password' => 'admin123'],
        ['username' => 'yonetici', 'password' => 'admin123'],
        ['username' => 'sakini1', 'password' => 'admin123'],
        ['username' => 'sakini2', 'password' => 'admin123']
    ];
    
    require_once 'api/core/auth.php';
    $auth = new Auth();
    
    foreach ($testUsers as $testUser) {
        $user = $auth->authenticate($testUser['username'], $testUser['password']);
        if ($user) {
            echo "✅ {$testUser['username']} - Giriş başarılı - Rol: {$user['role_name']}\n";
        } else {
            echo "❌ {$testUser['username']} - Giriş başarısız\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?> 