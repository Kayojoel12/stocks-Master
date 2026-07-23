<?php
/**
 * Ticket de caisse PDF style supermarché / magasin
 */
require_once 'config.php';
checkAuth();

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoiceId <= 0) {
    die('Facture invalide');
}

$stmt = $db->prepare("SELECT * FROM factures WHERE id = ?");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$invoice) {
    die('Facture introuvable');
}

$itemsStmt = $db->prepare("
    SELECT fi.*, p.nom AS produit_nom, p.reference
    FROM facture_items fi
    JOIN produits p ON fi.produit_id = p.id
    WHERE fi.facture_id = ?
");
$itemsStmt->execute([$invoiceId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$userName = 'Caisse';
if (!empty($invoice['utilisateur_id'])) {
    try {
        $u = $db->prepare("SELECT nom, email FROM utilisateurs WHERE id = ?");
        $u->execute([$invoice['utilisateur_id']]);
        $user = $u->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $userName = $user['nom'] ?: ($user['email'] ?? 'Caisse');
        }
    } catch (Exception $e) {}
}

$paymentLabels = [
    'cash' => 'ESPECES',
    'card' => 'CARTE BANCAIRE',
    'transfer' => 'VIREMENT',
    'check' => 'CHEQUE',
];
$payment = $paymentLabels[$invoice['mode_paiement']] ?? strtoupper((string)$invoice['mode_paiement']);

require_once __DIR__ . '/tcpdf/TCPDF-6.6.2/TCPDF-6.6.2/tcpdf.php';

// Format ticket thermique ~80mm
$pageWidth = 80;
$pageHeight = 200 + (count($items) * 8);

$pdf = new TCPDF('P', 'mm', [$pageWidth, max(140, $pageHeight)], true, 'UTF-8', false);
$pdf->SetCreator('StockMaster');
$pdf->SetAuthor('StockMaster');
$pdf->SetTitle('Ticket ' . $invoice['numero_facture']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(4, 4, 4);
$pdf->SetAutoPageBreak(true, 4);
$pdf->AddPage();
$pdf->SetTextColor(0, 0, 0);

$w = $pageWidth - 8;

// En-tête magasin
$pdf->SetFont('courier', 'B', 12);
$pdf->Cell($w, 5, 'STOCKMASTER MARKET', 0, 1, 'C');
$pdf->SetFont('courier', '', 7);
$pdf->Cell($w, 3.5, 'Votre magasin de confiance', 0, 1, 'C');
$pdf->Cell($w, 3.5, 'Tel: ' . (defined('PHONE_NUMBER') ? PHONE_NUMBER : '+237 XXX XXX XXX'), 0, 1, 'C');
$pdf->Cell($w, 3.5, 'Email: commandes@stockmaster.local', 0, 1, 'C');
$pdf->Ln(1);
$pdf->Cell($w, 0, str_repeat('-', 42), 0, 1, 'C');
$pdf->Ln(1);

$pdf->SetFont('courier', 'B', 9);
$pdf->Cell($w, 4, 'TICKET DE CAISSE', 0, 1, 'C');
$pdf->SetFont('courier', '', 7);
$pdf->Cell($w, 3.5, 'N° ' . $invoice['numero_facture'], 0, 1, 'C');
$pdf->Cell($w, 3.5, date('d/m/Y H:i:s', strtotime($invoice['date_facture'])), 0, 1, 'C');
$pdf->Cell($w, 3.5, 'Caissier: ' . $userName, 0, 1, 'C');
$pdf->Ln(1);
$pdf->Cell($w, 0, str_repeat('-', 42), 0, 1, 'C');
$pdf->Ln(1);

$pdf->SetFont('courier', '', 7);
$pdf->Cell($w, 3.5, 'Client: ' . $invoice['client_nom'], 0, 1, 'L');
if (!empty($invoice['client_contact'])) {
    $pdf->Cell($w, 3.5, 'Contact: ' . $invoice['client_contact'], 0, 1, 'L');
}
$pdf->Ln(1);
$pdf->Cell($w, 0, str_repeat('-', 42), 0, 1, 'C');
$pdf->Ln(1);

// En-tête articles
$pdf->SetFont('courier', 'B', 7);
$pdf->Cell($w * 0.48, 4, 'ARTICLE', 0, 0, 'L');
$pdf->Cell($w * 0.14, 4, 'QTE', 0, 0, 'R');
$pdf->Cell($w * 0.18, 4, 'P.U.', 0, 0, 'R');
$pdf->Cell($w * 0.20, 4, 'TOTAL', 0, 1, 'R');
$pdf->SetFont('courier', '', 7);
$pdf->Cell($w, 0, str_repeat('-', 42), 0, 1, 'C');
$pdf->Ln(1);

foreach ($items as $item) {
    $lineTotal = (float)$item['prix_unitaire'] * (int)$item['quantite'];
    $name = $item['produit_nom'];
    if (mb_strlen($name) > 22) {
        $name = mb_substr($name, 0, 21) . '.';
    }
    $pdf->Cell($w * 0.48, 4, $name, 0, 0, 'L');
    $pdf->Cell($w * 0.14, 4, (string)(int)$item['quantite'], 0, 0, 'R');
    $pdf->Cell($w * 0.18, 4, number_format((float)$item['prix_unitaire'], 0, '', ' '), 0, 0, 'R');
    $pdf->Cell($w * 0.20, 4, number_format($lineTotal, 0, '', ' '), 0, 1, 'R');
    if (!empty($item['reference'])) {
        $pdf->SetFont('courier', '', 6);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell($w, 3, '  ' . $item['reference'], 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('courier', '', 7);
    }
}

$pdf->Ln(1);
$pdf->Cell($w, 0, str_repeat('-', 42), 0, 1, 'C');
$pdf->Ln(1);

$pdf->SetFont('courier', '', 8);
$pdf->Cell($w * 0.55, 4, 'SOUS-TOTAL', 0, 0, 'L');
$pdf->Cell($w * 0.45, 4, number_format((float)$invoice['montant_total'], 0, '', ' ') . ' FCFA', 0, 1, 'R');

if ((float)$invoice['remise'] > 0) {
    $pdf->Cell($w * 0.55, 4, 'REMISE', 0, 0, 'L');
    $pdf->Cell($w * 0.45, 4, '-' . number_format((float)$invoice['remise'], 0, '', ' ') . ' FCFA', 0, 1, 'R');
}

$pdf->SetFont('courier', 'B', 10);
$pdf->Cell($w * 0.55, 6, 'TOTAL A PAYER', 0, 0, 'L');
$pdf->Cell($w * 0.45, 6, number_format((float)$invoice['montant_final'], 0, '', ' ') . ' F', 0, 1, 'R');

$pdf->SetFont('courier', '', 7);
$pdf->Ln(1);
$pdf->Cell($w, 0, str_repeat('-', 42), 0, 1, 'C');
$pdf->Ln(1);
$pdf->Cell($w, 3.5, 'Mode de paiement: ' . $payment, 0, 1, 'L');
$pdf->Cell($w, 3.5, 'Articles: ' . count($items), 0, 1, 'L');

if (!empty($invoice['notes'])) {
    $pdf->Ln(1);
    $pdf->MultiCell($w, 3.5, 'Note: ' . $invoice['notes'], 0, 'L');
}

$pdf->Ln(2);
$pdf->Cell($w, 0, str_repeat('*', 42), 0, 1, 'C');
$pdf->Ln(1);
$pdf->SetFont('courier', 'B', 8);
$pdf->Cell($w, 4, 'MERCI DE VOTRE VISITE !', 0, 1, 'C');
$pdf->SetFont('courier', '', 7);
$pdf->Cell($w, 3.5, 'A bientot chez StockMaster', 0, 1, 'C');
$pdf->Cell($w, 3.5, 'Conservez ce ticket', 0, 1, 'C');
$pdf->Ln(2);

// Code-barres du numéro de facture
try {
    $style = [
        'position' => 'C',
        'align' => 'C',
        'stretch' => false,
        'fitwidth' => true,
        'cellfitalign' => '',
        'border' => false,
        'hpadding' => 'auto',
        'vpadding' => 'auto',
        'fgcolor' => [0, 0, 0],
        'bgcolor' => false,
        'text' => true,
        'font' => 'courier',
        'fontsize' => 6,
        'stretchtext' => 0,
    ];
    $code = preg_replace('/[^A-Za-z0-9]/', '', (string)$invoice['numero_facture']);
    if ($code === '') {
        $code = (string)$invoice['id'];
    }
    $pdf->write1DBarcode($code, 'C128', '', '', $w, 12, 0.3, $style, 'N');
} catch (Exception $e) {
    // ignore barcode errors
}

$pdf->Ln(3);
$pdf->SetFont('courier', '', 6);
$pdf->Cell($w, 3, date('d/m/Y H:i:s'), 0, 1, 'C');
$pdf->Cell($w, 3, 'www.stockmaster.local', 0, 1, 'C');

$pdf->Output('ticket_' . $invoice['numero_facture'] . '.pdf', 'D');
exit;
