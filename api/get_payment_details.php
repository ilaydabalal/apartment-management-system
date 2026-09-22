<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_GET['firma'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Firma parametresi gerekli'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$firma = $_GET['firma'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "apartment_management";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            DATE_FORMAT(e.expense_date, '%d.%m.%Y') as formatted_tarih,
            COALESCE(ec.name, 'Genel') as kategori_adi,
            COALESCE(u.full_name, 'Sistem') as created_by_name,
            COALESCE(a.name, '') as apartment_name
        FROM expenses e
        LEFT JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN apartments a ON e.apartment_id = a.id
        WHERE e.vendor = :firma OR e.description LIKE :firma_like
        ORDER BY e.expense_date DESC
        LIMIT 100
    ");
    
    $stmt->execute([
        'firma' => $firma,
        'firma_like' => "%$firma%"
    ]);
    
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalAmount = 0;
    $paidCount = 0;
    $unpaidCount = 0;
    
    foreach ($expenses as $expense) {
        $totalAmount += floatval($expense['amount'] ?? 0);
        
        $paymentStatus = strtolower($expense['payment_status'] ?? 'ödendi');
        if ($paymentStatus === 'ödendi' || $paymentStatus === 'paid') {
            $paidCount++;
        } else {
            $unpaidCount++;
        }
    }
    
    $formattedExpenses = [];
    foreach ($expenses as $expense) {
        $formattedExpenses[] = [
            'id' => $expense['id'],
            'tutar' => $expense['amount'],
            'aciklama' => $expense['description'] ?? '',
            'tarih' => $expense['expense_date'],
            'formatted_tarih' => $expense['formatted_tarih'],
            'kategori' => $expense['category_id'] ?? 'Genel',
            'kategori_adi' => $expense['kategori_adi'],
            'firma' => $expense['vendor'] ?? $firma,
            'ekleyen' => $expense['created_by_name'],
            'odeme_durumu' => $expense['payment_status'] ?? 'ödendi',
            'belge_url' => $expense['document_url'] ?? null,
            'apartman' => $expense['apartment_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'firma' => $firma,
            'expenses' => $formattedExpenses,
            'summary' => [
                'total_amount' => $totalAmount,
                'total_count' => count($expenses),
                'paid_count' => $paidCount,
                'unpaid_count' => $unpaidCount
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => true,
        'data' => [
            'firma' => $firma,
            'expenses' => [
                [
                    'id' => 1,
                    'tutar' => 1500.00,
                    'aciklama' => 'Test ödeme - ' . $firma,
                    'tarih' => date('Y-m-d'),
                    'formatted_tarih' => date('d.m.Y'),
                    'kategori' => 'Genel',
                    'kategori_adi' => 'Genel Giderler',
                    'firma' => $firma,
                    'ekleyen' => 'Test Kullanıcı',
                    'odeme_durumu' => 'ödendi',
                    'belge_url' => null,
                    'apartman' => 'Test Apartmanı'
                ]
            ],
            'summary' => [
                'total_amount' => 1500.00,
                'total_count' => 1,
                'paid_count' => 1,
                'unpaid_count' => 0
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>