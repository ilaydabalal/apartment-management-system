<?php
ob_start();

error_reporting(0);
ini_set('display_errors', 0);

require_once '../core/database.php';
require_once __DIR__ . '/../dues/fpdf/fpdf.php';

$id = $_GET['id'] ?? '';
if (!$id) {
    ob_clean(); 
    http_response_code(400);
    echo 'Gider ID gerekli.';
    exit;
}

try {
    $db = Database::getInstance();
    $sql = "SELECT e.*, a.name as apartment_name, c.name as category_name, u.full_name as created_by_name
            FROM expenses e
            LEFT JOIN apartments a ON e.apartment_id = a.id
            LEFT JOIN expense_categories c ON e.category_id = c.id
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.id = ? AND e.status = 1 LIMIT 1";
    $expense = $db->fetch($sql, [$id]);

    if (!$expense) {
        ob_clean(); 
        http_response_code(404);
        echo 'Gider bulunamadı.';
        exit;
    }

    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment ; filename="gider_faturasi_' . $expense['id'] . '.pdf"');

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 12, 'Gider Faturasi / Makbuzu', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Ln(5);

    $invoiceNumber = 'GDR-' . date('Y') . '-' . str_pad($id, 6, '0', STR_PAD_LEFT);

    $fields = [
        'Apartman' => $expense['apartment_name'] ?: '-',
        'Kategori' => $expense['category_name'] ?: '-',
        'Tutar' => number_format($expense['amount'], 2) . ' TL',
        'Aciklama' => $expense['description'] ?: '-',
        'Tarih' => $expense['expense_date'] ?: '-',
        'Fatura No' => $invoiceNumber,
        'Firma' => $expense['vendor'] ?: '-',
        'Ekleyen' => $expense['created_by_name'] ?: '-',
    ];

    foreach ($fields as $label => $value) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(50, 8, $label . ':', 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, $value, 0, 1);
    }

    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 8, 'Bu gider faturasi bilgisayar ortaminda uretilmistir.', 0, 1, 'C');
    $pdf->Cell(0, 8, 'Tarih: ' . date('d.m.Y H:i:s'), 0, 1, 'C');

    $pdf->Output('I', 'gider_faturasi_' . $expense['id'] . '.pdf');
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>PDF Oluşturma Hatası</h1>';
    echo '<p>Hata: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<a href="javascript:history.back()">Geri Dön</a>';
}
ob_end_flush();
?>