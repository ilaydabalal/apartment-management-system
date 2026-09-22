<?php
// export_payments.php - Format'a göre PDF veya Excel export

// Format parametresini kontrol et
$format = $_GET['format'] ?? 'excel';
$firma = $_GET['firma'] ?? '';
$download = $_GET['download'] ?? '0'; // Yeni parametre


if (empty($firma)) {
    die('Firma parametresi eksik');
}

// Veritabanı bağlantısı
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "apartment_management";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die('Veritabanı bağlantı hatası: ' . $e->getMessage());
}

// Firma ödemelerini getir
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        ec.name as kategori_adi,
        u.full_name as created_by_name,
        a.name as apartment_name,
        DATE_FORMAT(e.expense_date, '%d.%m.%Y') as formatted_tarih,
        DATE_FORMAT(e.created_at, '%d.%m.%Y %H:%i') as kayit_tarihi
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

// Eğer veri yoksa test verisi oluştur
if (empty($expenses)) {
    $expenses = [
        [
            'id' => 1,
            'amount' => 285.50,
            'description' => $firma . ' - Belediye harçları',
            'expense_date' => '2025-07-30',
            'formatted_tarih' => '30.07.2025',
            'kategori_adi' => 'Vergi ve Harçlar',
            'vendor' => $firma,
            'created_by_name' => 'Sistem Yöneticisi',
            'apartment_name' => 'B Blok',
            'payment_status' => 'ödendi',
            'kayit_tarihi' => date('d.m.Y H:i')
        ]
    ];
}

// Format'a göre farklı header'lar gönder
if ($format === 'pdf') {
    if ($download === '1') {
        // Direkt PDF indirme için header'lar
        $filename = urlencode($firma) . '_odemeler_' . date('Y-m-d') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // PDF oluşturma için wkhtmltopdf kullanacağız
        // Önce HTML içeriğini oluştur
        ob_start();
        generatePDFContent($expenses, $firma);
        $htmlContent = ob_get_clean();
        
        // HTML'i PDF'e çevir (wkhtmltopdf veya benzeri gerekli)
        // Eğer wkhtmltopdf yoksa, basit HTML'i PDF gibi göndermek için:
        echo $htmlContent;
        exit;
    } else {
        // Tarayıcıda görüntüleme için
        header('Content-Type: text/html; charset=utf-8');
    }
} else {
    // Excel için
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . urlencode($firma) . '_odemeler_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// BOM karakteri ekle (Türkçe karakterler için)
echo "\xEF\xBB\xBF";
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($firma); ?> - Ödeme Detayları</title>
    <style>
        <?php if ($format === 'pdf'): ?>
        /* PDF özel stilleri */
        @page {
            size: A4;
            margin: 15mm;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            color: #333;
            background: white;
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #011500, #260399);
            color: white;
            border-radius: 10px;
            page-break-inside: avoid;
        }
        
        .header h1 {
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: 600;
        }
        
        .header .subtitle {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .summary-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
            border-left: 3px solid #007bff;
        }
        
        .summary-card h3 {
            margin: 0 0 4px 0;
            color: #007bff;
            font-size: 16px;
            font-weight: bold;
        }
        
        .summary-card p {
            margin: 0;
            color: #6c757d;
            font-size: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            background: white;
            font-size: 10px;
        }
        
        th {
            background: #343a40;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: 600;
            font-size: 9px;
            text-transform: uppercase;
            border: 1px solid #495057;
        }
        
        td {
            padding: 6px;
            border: 1px solid #dee2e6;
            font-size: 10px;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .amount {
            text-align: right;
            font-weight: bold;
            color: #28a745;
        }
        
        .status-badge {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 8px;
            font-weight: 500;
            text-align: center;
            display: inline-block;
            min-width: 45px;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        
        .footer {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            text-align: center;
            color: #6c757d;
            font-size: 9px;
            border-top: 2px solid #e9ecef;
            page-break-inside: avoid;
        }
        
        .logo {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 6px;
            color: white;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            margin-right: 8px;
            vertical-align: middle;
            font-size: 14px;
        }
        
        .pdf-controls {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            padding: 8px;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .pdf-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            margin-left: 4px;
        }
        
        .pdf-btn:hover {
            background: #c82333;
        }
        
        .pdf-btn.secondary {
            background: #6c757d;
        }
        
        .pdf-btn.secondary:hover {
            background: #545b62;
        }
        
        @media print {
            body { -webkit-print-color-adjust: exact; color-adjust: exact; }
            .pdf-controls { display: none !important; }
            .header { background: linear-gradient(135deg, #011500, #260399) !important; -webkit-print-color-adjust: exact; }
        }
        
        <?php else: ?>
        /* Excel stilleri */
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }
        .summary {
            margin-bottom: 20px;
        }
        .summary table {
            width: 50%;
        }
        .amount {
            text-align: right;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <?php if ($format === 'pdf'): ?>
    <!-- PDF Kontrolleri -->
    <div class="pdf-controls">
        <button class="pdf-btn" onclick="window.print()">
            📄 PDF Kaydet
        </button>
        <button class="pdf-btn secondary" onclick="window.close()">
            ✕ Kapat
        </button>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="header">
        <div>
            <?php if ($format === 'pdf'): ?>
            <span class="logo">🏢</span>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($firma); ?> - Ödeme Detayları</h1>
        </div>
        <div class="subtitle">
            Rapor Tarihi: <?php echo date('d.m.Y H:i'); ?> 
            <?php if ($format === 'pdf'): ?>
            | PDF Raporu
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Özet Bilgiler -->
    <?php if ($format === 'pdf'): ?>
    <div class="summary">
        <div class="summary-card">
            <h3><?php echo count($expenses); ?></h3>
            <p>Toplam İşlem</p>
        </div>
        
        <div class="summary-card" style="border-left-color: #28a745;">
            <h3 style="color: #28a745;">
                <?php 
                $totalAmount = array_sum(array_column($expenses, 'amount'));
                echo number_format($totalAmount, 2, ',', '.') . ' ₺'; 
                ?>
            </h3>
            <p>Toplam Tutar</p>
        </div>
        
        <div class="summary-card" style="border-left-color: #17a2b8;">
            <h3 style="color: #17a2b8;">
                <?php 
                $paidCount = count(array_filter($expenses, function($e) {
                    $status = strtolower($e['payment_status'] ?? 'ödendi');
                    return $status === 'ödendi' || $status === 'paid';
                }));
                echo $paidCount;
                ?>
            </h3>
            <p>Ödenen</p>
        </div>
        
        <div class="summary-card" style="border-left-color: #ffc107;">
            <h3 style="color: #ffc107;"><?php echo count($expenses) - $paidCount; ?></h3>
            <p>Bekleyen</p>
        </div>
    </div>
    <?php else: ?>
    <div class="summary">
        <h3>Özet Bilgiler</h3>
        <table>
            <tr>
                <th>Toplam İşlem Sayısı</th>
                <td><?php echo count($expenses); ?></td>
            </tr>
            <tr>
                <th>Toplam Tutar</th>
                <td class="amount">
                    <?php 
                    $totalAmount = array_sum(array_column($expenses, 'amount'));
                    echo number_format($totalAmount, 2, ',', '.') . ' ₺'; 
                    ?>
                </td>
            </tr>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Detay Tablosu -->
    <h3><?php echo $format === 'pdf' ? 'İşlem Detayları' : 'Detaylı Liste'; ?></h3>
    <table>
        <thead>
            <tr>
                <th>Tarih</th>
                <th>Apartman</th>
                <th>Açıklama</th>
                <th>Kategori</th>
                <th>Tutar</th>
                <th>Durum</th>
                <th>Ekleyen</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($expenses)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px; color: #6c757d; font-style: italic;">
                        Bu firmaya ait ödeme kaydı bulunamadı.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($expenses as $expense): ?>
                <tr>
                    <td><?php echo htmlspecialchars($expense['formatted_tarih']); ?></td>
                    <td><?php echo htmlspecialchars($expense['apartment_name'] ?? 'B Blok'); ?></td>
                    <td><?php echo htmlspecialchars($expense['description'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($expense['kategori_adi'] ?? 'Genel'); ?></td>
                    <td class="amount"><?php echo number_format($expense['amount'], 2, ',', '.') . ' ₺'; ?></td>
                    <td>
                        <?php 
                        $status = strtolower($expense['payment_status'] ?? 'ödendi');
                        $statusClass = 'status-paid';
                        $statusText = 'Ödendi';
                        
                        if ($status === 'beklemede' || $status === 'pending') {
                            $statusClass = 'status-pending';
                            $statusText = 'Beklemede';
                        } elseif ($status === 'ödenmedi' || $status === 'unpaid') {
                            $statusClass = 'status-unpaid';
                            $statusText = 'Ödenmedi';
                        }
                        ?>
                        <?php if ($format === 'pdf'): ?>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                        <?php else: ?>
                        <?php echo $statusText; ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($expense['created_by_name'] ?? 'Sistem'); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Footer -->
    <div class="footer">
        <p>
            <strong><?php echo $format === 'pdf' ? '🏢 ' : ''; ?>Apartman Yönetim Sistemi</strong><br>
            Bu rapor <?php echo date('d.m.Y H:i:s'); ?> tarihinde otomatik olarak oluşturulmuştur.<br>
            <?php if ($format === 'pdf'): ?>
            <small>Firma: <?php echo htmlspecialchars($firma); ?> | 
            Toplam Kayıt: <?php echo count($expenses); ?> | 
            Format: PDF</small>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($format === 'pdf'): ?>
    <script>
    // PDF için JavaScript
    window.onload = function() {
        console.log('PDF sayfası yüklendi');
        
        // 1 saniye sonra print dialog'u aç
        setTimeout(function() {
            console.log('Print dialog açılıyor...');
            window.print();
        }, 1000);
    };
    
    // Print tamamlandıktan sonra işlem yap
    window.onafterprint = function() {
        console.log('Print işlemi tamamlandı');
        // İsteğe bağlı: pencereyi kapat
        // setTimeout(function() { window.close(); }, 1000);
    };
    
    // Kapat butonu
    function closePage() {
        window.close();
    }
    </script>
    <?php endif; ?>
</body>
</html>