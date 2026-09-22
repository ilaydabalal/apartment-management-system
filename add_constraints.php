<?php
require_once 'config/database.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Constraint'ler Ekleniyor...</h2>";
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $constraints = [
        // apartments tablosu
        "ALTER TABLE `apartments` ADD CONSTRAINT `apartments_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        
        // apartment_units tablosu
        "ALTER TABLE `apartment_units` ADD CONSTRAINT `apartment_units_ibfk_1` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`) ON DELETE CASCADE",
        
        // dues tablosu
        "ALTER TABLE `dues` ADD CONSTRAINT `dues_ibfk_1` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `dues` ADD CONSTRAINT `dues_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `apartment_units` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `dues` ADD CONSTRAINT `dues_ibfk_3` FOREIGN KEY (`due_definition_id`) REFERENCES `dues_definitions` (`id`) ON DELETE CASCADE",
        
        // dues_definitions tablosu
        "ALTER TABLE `dues_definitions` ADD CONSTRAINT `dues_definitions_ibfk_1` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`) ON DELETE CASCADE",
        
        // expenses tablosu
        "ALTER TABLE `expenses` ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `expenses` ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`)",
        "ALTER TABLE `expenses` ADD CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        
        // incomes tablosu
        "ALTER TABLE `incomes` ADD CONSTRAINT `incomes_ibfk_1` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `incomes` ADD CONSTRAINT `incomes_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `income_categories` (`id`)",
        "ALTER TABLE `incomes` ADD CONSTRAINT `incomes_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        
        // payments tablosu
        "ALTER TABLE `payments` ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `payments` ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `apartment_units` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `payments` ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`due_id`) REFERENCES `dues` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `payments` ADD CONSTRAINT `payments_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        
        // system_logs tablosu
        "ALTER TABLE `system_logs` ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        
        // users tablosu
        "ALTER TABLE `users` ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL",
        
        // user_sessions tablosu
        "ALTER TABLE `user_sessions` ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE"
    ];
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($constraints as $constraint) {
        try {
            $pdo->exec($constraint);
            echo "<p style='color: green;'>✓ " . substr($constraint, 0, 80) . "...</p>";
            $successCount++;
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠ " . substr($constraint, 0, 80) . "... (Zaten mevcut olabilir)</p>";
            $errorCount++;
        }
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<h3>Sonuç:</h3>";
    echo "<p>Başarılı: $successCount</p>";
    echo "<p>Hata: $errorCount</p>";
    
    if ($successCount > 0) {
        echo "<p style='color: green; font-weight: bold;'>Constraint'ler başarıyla eklendi! Şimdi arayüzü test edebilirsiniz.</p>";
    }
    
} catch (PDOException $e) {
    echo "<h2>Hata!</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?> 