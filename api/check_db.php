<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Veritabanı bağlantısı kontrol ediliyor...<br><br>";

require_once '../config/database.php';

echo "<strong>Tanımlı değişkenler:</strong><br>";
$variables = get_defined_vars();
foreach ($variables as $name => $value) {
    if (is_object($value)) {
        echo "$name => " . get_class($value) . " (object)<br>";
    } elseif (is_array($value)) {
        echo "$name => Array<br>";
    } else {
        echo "$name => $value<br>";
    }
}

echo "<br><strong>MySQLi bağlantısı araniyor...</strong><br>";

$possible_connections = ['$conn', '$connection', '$db', '$pdo', '$mysqli'];
foreach ($possible_connections as $var_name) {
    $var = substr($var_name, 1); 
    if (isset($$var)) {
        echo "✓ $var_name bulundu!<br>";
        $conn = $$var; 
        break;
    } else {
        echo "✗ $var_name bulunamadı<br>";
    }
}

if (isset($conn) && $conn) {
    echo "<br><strong>Bağlantı test ediliyor...</strong><br>";
    try {
        if (method_exists($conn, 'query')) {
            $result = $conn->query("SELECT 1");
            if ($result) {
                echo "✓ Veritabanı sorgusu başarılı!<br>";
                
                $tables = $conn->query("SHOW TABLES");
                echo "<br><strong>Mevcut tablolar:</strong><br>";
                while ($row = $tables->fetch_array()) {
                    echo "- " . $row[0] . "<br>";
                }
                
            } else {
                echo "✗ Sorgu başarısız: " . $conn->error . "<br>";
            }
        } else {
            echo "✗ query() metodu bulunamadı. Bu bir MySQLi bağlantısı değil.<br>";
        }
    } catch (Exception $e) {
        echo "✗ Hata: " . $e->getMessage() . "<br>";
    }
} else {
    echo "<br>❌ Hiçbir veritabanı bağlantısı bulunamadı!<br>";
}
?>