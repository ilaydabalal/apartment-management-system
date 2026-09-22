<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/response.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance()->getConnection();
    $sql = "SELECT id, name FROM income_categories ORDER BY name";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    Response::success(['categories' => $categories]);
} catch (Exception $e) {
    Response::error('Gelir kategorileri alınamadı: ' . $e->getMessage());
} 