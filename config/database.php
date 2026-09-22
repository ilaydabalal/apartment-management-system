<?php

// Veritabanı bağlantı bilgileri
define('DB_HOST', 'localhost');
define('DB_NAME', 'apartment_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// JWT ayarları
define('JWT_SECRET', 'apartman-yonetim-2024-super-gizli-anahtar-123');
define('JWT_EXPIRE_TIME', 86400); // 24 saat

// API ayarları
define('API_VERSION', 'v1');
define('API_BASE_URL', '/api/');

// Sistem ayarları
define('SYSTEM_NAME', 'Apartman Yönetim Sistemi');
define('SYSTEM_VERSION', '1.0.0');
define('DEFAULT_TIMEZONE', 'Europe/Istanbul');

// Güvenlik ayarları
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_BLOCK_TIME', 900); // 15 dakika
define('SESSION_LIFETIME', 3600); // 1 saat

// Dosya yükleme ayarları
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Log ayarları
define('LOG_LEVEL', 'INFO');
define('LOG_FILE_PATH', __DIR__ . '/../logs/');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@apartmanyonetim.com');
define('FROM_NAME', 'Apartman Yönetim Sistemi');

date_default_timezone_set(DEFAULT_TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('post_max_size', '10M');
ini_set('upload_max_filesize', '5M');
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');


$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$conn->set_charset(DB_CHARSET);

if ($conn->connect_error) {
    if (defined('PRODUCTION') && PRODUCTION) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Veritabanı bağlantısı kurulamadı. Lütfen sistem yöneticisi ile iletişime geçin.");
    } else {
        die("Veritabanı bağlantısı başarısız: " . $conn->connect_error);
    }
}

if (!defined('PRODUCTION') || !PRODUCTION) {
}
?>