<?php
require_once '../core/database.php';
require_once __DIR__ . '/fpdf/fpdf.php';

header('Content-Type: application/pdf');

$receiptNumber = $_GET['receipt_number'] ?? '';
if (!$receiptNumber) {
    http_response_code(400);
    echo 'Makbuz numarası gerekli.';
    exit;
}

$db = Database::getInstance();

$sql = "SELECT 
            dph.*,
            COALESCE(
                (SELECT u.full_name FROM users u JOIN payments p ON u.id = p.created_by WHERE p.receipt_number = dph.receipt_number),
                (SELECT u.full_name FROM users u JOIN dues d ON u.id = CAST(d.payment_receiver AS UNSIGNED) WHERE d.id = (SELECT due_id FROM payments WHERE receipt_number = dph.receipt_number)),
                'Belirtilmemiş'
            ) as payment_receiver_name
        FROM dues_payment_history dph 
        WHERE dph.receipt_number = ? 
        LIMIT 1";

$payment = $db->fetch($sql, [$receiptNumber]);

if (!$payment) {
    http_response_code(404);
    echo 'Makbuz bulunamadı.';
    exit;
}

error_log('📄 Makbuz ödeme alan kişi: ' . ($payment['payment_receiver_name'] ?? 'NULL'));

$methodMap = [
    'cash' => 'Nakit',
    'bank_transfer' => 'Havale/EFT',
    'credit_card' => 'Kredi Kartı',
    'check' => 'Çek',
    null => '',
    '' => ''
];
$payment['payment_method_tr'] = $methodMap[$payment['payment_method']] ?? $payment['payment_method'];

$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 12, 'Aidat Odeme Makbuzu', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Ln(5);

$fields = [
    'Blok' => $payment['apartment_name'],
    'Daire No' => $payment['unit_number'],
    'Sakin' => $payment['resident_name'],
    'Aidat Turu' => $payment['due_type'],
    'Tutar' => $payment['due_amount'] . ' TL',
    'Yil' => $payment['period_year'],
    'Ay' => $payment['period_month'],
    'Vade' => $payment['due_date'],
    'Odendi' => $payment['is_paid'] ? 'Evet' : 'Hayir',
    'Odeme Tarihi' => $payment['payment_date'],
    'Odenen Tutar' => $payment['paid_amount'] . ' TL',
    'Yontem' => $payment['payment_method_tr'],
    'Odemeyi Alan' => $payment['payment_receiver_name'],
    'Makbuz No' => $payment['receipt_number'],
    'Durum' => $payment['payment_status'],
];

foreach ($fields as $label => $value) {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(50, 8, $label . ':', 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 8, $value, 0, 1);
}

$pdf->Ln(15);
$pdf->SetFont('Arial', 'B', 11);

$pdf->Cell(95, 8, 'Odemeyi Alan:', 0, 0, 'C');
$pdf->Cell(95, 8, 'Odemeyi Yapan:', 0, 1, 'C');

$pdf->Ln(5);

$pdf->Cell(95, 0, '', 'B', 0, 'C');
$pdf->Cell(95, 0, '', 'B', 1, 'C');

$pdf->Ln(5);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(95, 6, $payment['payment_receiver_name'], 0, 0, 'C');
$pdf->Cell(95, 6, $payment['resident_name'], 0, 1, 'C');

$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 8, 'Bu makbuz bilgisayar ortaminda uretilmistir.', 0, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 6, 'Tarih: ' . date('d.m.Y H:i'), 0, 1, 'C');

$pdf->Output('I', 'makbuz_'.$payment['receipt_number'].'.pdf');
exit;
?>