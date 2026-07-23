<?php
require_once 'config.php';
require_roles(['admin', 'superviseur']);

$theme = getCurrentTheme();

$endDate = $_GET['end_date'] ?? date('Y-m-d');
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$siteFilter = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;

function money_fcfa($n) {
    return number_format((float)$n, 0, ',', ' ') . ' FCFA';
}

function escape_sql_value($v) {
    if ($v === null) return 'NULL';
    return "'" . str_replace(["\\", "'"], ["\\\\", "''"], (string)$v) . "'";
}

function get_sites(PDO $db) {
    return $db->query("SELECT * FROM sites ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
}

function get_site(PDO $db, $id) {
    $stmt = $db->prepare("SELECT * FROM sites WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_site_inventory(PDO $db, $siteId) {
    $stmt = $db->prepare("
        SELECT p.reference, p.nom, c.nom AS categorie, si.quantite,
               p.prix_achat, p.prix_vente, (si.quantite * p.prix_achat) AS valeur_stock
        FROM site_inventaire si
        JOIN produits p ON p.id = si.produit_id
        LEFT JOIN categories c ON c.id = p.categorie_id
        WHERE si.site_id = ? AND si.quantite > 0
        ORDER BY p.nom
    ");
    $stmt->execute([$siteId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_transactions(PDO $db, $startDate, $endDate) {
    $stmt = $db->prepare("
        SELECT m.id, m.created_at, m.type, m.quantite, m.motif, m.notes,
               p.reference, p.nom AS produit, COALESCE(u.nom, '—') AS utilisateur
        FROM mouvements m
        JOIN produits p ON p.id = m.produit_id
        LEFT JOIN utilisateurs u ON u.id = m.utilisateur_id
        WHERE m.created_at BETWEEN ? AND ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_invoices(PDO $db, $startDate, $endDate) {
    $stmt = $db->prepare("
        SELECT id, numero_facture, date_facture, client_nom, client_contact,
               montant_total, remise, montant_final, mode_paiement
        FROM factures
        WHERE date_facture BETWEEN ? AND ?
        ORDER BY date_facture DESC
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_stock_detail(PDO $db) {
    return $db->query("
        SELECT p.reference, p.nom, COALESCE(c.nom, '—') AS categorie,
               COALESCE(s.quantite, 0) AS quantite, p.prix_achat, p.prix_vente,
               (COALESCE(s.quantite, 0) * COALESCE(p.prix_achat, 0)) AS valeur_stock
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        LEFT JOIN stock s ON p.id = s.produit_id
        ORDER BY p.nom
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// ===== EXPORTS =====
$export = $_GET['export'] ?? '';

if ($export === 'excel_stock') {
    $data = fetch_stock_detail($db);
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment;filename="stock_' . date('Y-m-d') . '.xls"');
    echo "\xEF\xBB\xBF";
    echo "<table border='1'><tr><th>Référence</th><th>Produit</th><th>Catégorie</th><th>Quantité</th><th>Prix Achat</th><th>Prix Vente</th><th>Valeur</th></tr>";
    foreach ($data as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['reference']) . '</td>';
        echo '<td>' . htmlspecialchars($row['nom']) . '</td>';
        echo '<td>' . htmlspecialchars($row['categorie']) . '</td>';
        echo '<td>' . (int)$row['quantite'] . '</td>';
        echo '<td>' . $row['prix_achat'] . '</td>';
        echo '<td>' . $row['prix_vente'] . '</td>';
        echo '<td>' . $row['valeur_stock'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

if ($export === 'excel_transactions') {
    $data = fetch_transactions($db, $startDate, $endDate);
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment;filename="transactions_' . date('Y-m-d') . '.xls"');
    echo "\xEF\xBB\xBF";
    echo "<table border='1'><tr><th>Date</th><th>Type</th><th>Référence</th><th>Produit</th><th>Quantité</th><th>Motif</th><th>Utilisateur</th></tr>";
    foreach ($data as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
        echo '<td>' . htmlspecialchars($row['type']) . '</td>';
        echo '<td>' . htmlspecialchars($row['reference']) . '</td>';
        echo '<td>' . htmlspecialchars($row['produit']) . '</td>';
        echo '<td>' . (int)$row['quantite'] . '</td>';
        echo '<td>' . htmlspecialchars($row['motif'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['utilisateur']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

if ($export === 'excel_site' && $siteFilter > 0) {
    $site = get_site($db, $siteFilter);
    if (!$site) { die('Site introuvable'); }
    $data = get_site_inventory($db, $siteFilter);
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $site['nom']);
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment;filename="entrepot_' . $safe . '_' . date('Y-m-d') . '.xls"');
    echo "\xEF\xBB\xBF";
    echo '<h3>Entrepôt : ' . htmlspecialchars($site['nom']) . ' — ' . htmlspecialchars($site['ville']) . '</h3>';
    echo '<p>Adresse : ' . htmlspecialchars($site['adresse']) . ' | Responsable : ' . htmlspecialchars($site['responsable']) . '</p>';
    echo "<table border='1'><tr><th>Référence</th><th>Produit</th><th>Catégorie</th><th>Quantité</th><th>Prix Achat</th><th>Prix Vente</th><th>Valeur</th></tr>";
    foreach ($data as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['reference']) . '</td>';
        echo '<td>' . htmlspecialchars($row['nom']) . '</td>';
        echo '<td>' . htmlspecialchars($row['categorie'] ?? '—') . '</td>';
        echo '<td>' . (int)$row['quantite'] . '</td>';
        echo '<td>' . $row['prix_achat'] . '</td>';
        echo '<td>' . $row['prix_vente'] . '</td>';
        echo '<td>' . $row['valeur_stock'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

if ($export === 'pdf_site' && $siteFilter > 0) {
    require_once __DIR__ . '/tcpdf/TCPDF-6.6.2/TCPDF-6.6.2/tcpdf.php';
    $site = get_site($db, $siteFilter);
    if (!$site) { die('Site introuvable'); }
    $data = get_site_inventory($db, $siteFilter);
    $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $site['nom']);

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('StockMaster');
    $pdf->SetAuthor('StockMaster');
    $pdf->SetTitle('Entrepôt ' . $site['nom']);
    $pdf->setPrintHeader(false);
    $pdf->SetMargins(12, 15, 12);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 8, 'STOCKMASTER — Rapport Entrepôt', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, $site['nom'] . ' — ' . $site['ville'], 0, 1, 'C');
    $pdf->Cell(0, 5, 'Adresse: ' . $site['adresse'] . ' | Resp: ' . $site['responsable'], 0, 1, 'C');
    $pdf->Cell(0, 5, 'Généré le ' . date('d/m/Y H:i'), 0, 1, 'C');
    $pdf->Ln(4);

    $html = '<table border="1" cellpadding="4">
        <tr style="background-color:#0f3460;color:#fff;">
            <th width="18%"><b>Réf.</b></th>
            <th width="32%"><b>Produit</b></th>
            <th width="18%"><b>Catégorie</b></th>
            <th width="10%"><b>Qté</b></th>
            <th width="11%"><b>P.Achat</b></th>
            <th width="11%"><b>Valeur</b></th>
        </tr>';
    $total = 0;
    foreach ($data as $row) {
        $total += (float)$row['valeur_stock'];
        $html .= '<tr>
            <td>' . htmlspecialchars($row['reference']) . '</td>
            <td>' . htmlspecialchars($row['nom']) . '</td>
            <td>' . htmlspecialchars($row['categorie'] ?? '—') . '</td>
            <td align="right">' . (int)$row['quantite'] . '</td>
            <td align="right">' . number_format((float)$row['prix_achat'], 0, ',', ' ') . '</td>
            <td align="right">' . number_format((float)$row['valeur_stock'], 0, ',', ' ') . '</td>
        </tr>';
    }
    if (empty($data)) {
        $html .= '<tr><td colspan="6" align="center">Aucun produit en stock sur cet entrepôt</td></tr>';
    }
    $html .= '<tr style="background-color:#e9ecef;"><td colspan="5" align="right"><b>TOTAL</b></td><td align="right"><b>' . number_format($total, 0, ',', ' ') . '</b></td></tr></table>';
    $pdf->SetFont('helvetica', '', 9);
    $pdf->writeHTML($html, true, false, false, false, '');
    $pdf->Output('entrepot_' . $safe . '_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

if ($export === 'pdf') {
    require_once __DIR__ . '/tcpdf/TCPDF-6.6.2/TCPDF-6.6.2/tcpdf.php';

    $sites = get_sites($db);
    $stock = fetch_stock_detail($db);
    $transactions = fetch_transactions($db, $startDate, $endDate);
    $invoices = fetch_invoices($db, $startDate, $endDate);
    $sitesCount = count($sites);
    $totalValue = array_sum(array_map(static fn($r) => (float)$r['valeur_stock'], $stock));
    $alerts = $db->query("SELECT COUNT(*) FROM produits p JOIN stock s ON p.id = s.produit_id WHERE s.quantite <= p.seuil_alerte")->fetchColumn();

    class StockMasterPDF extends TCPDF {
        public function Header() {
            $this->SetFillColor(15, 52, 96);
            $this->Rect(0, 0, $this->getPageWidth(), 28, 'F');
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('helvetica', 'B', 18);
            $this->SetY(8);
            $this->Cell(0, 8, 'STOCKMASTER', 0, 1, 'C');
            $this->SetFont('helvetica', '', 9);
            $this->Cell(0, 5, 'Rapport professionnel de gestion de stock', 0, 1, 'C');
            $this->Ln(8);
        }
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->SetTextColor(120, 120, 120);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . '  |  Généré le ' . date('d/m/Y H:i') . '  |  Confidentiel', 0, 0, 'C');
        }
    }

    $pdf = new StockMasterPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('StockMaster');
    $pdf->SetAuthor('StockMaster');
    $pdf->SetTitle('Rapport Stock ' . $startDate . ' - ' . $endDate);
    $pdf->SetMargins(12, 36, 12);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(12);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $pdf->SetTextColor(33, 37, 41);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Synthèse de la période', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Du ' . date('d/m/Y', strtotime($startDate)) . ' au ' . date('d/m/Y', strtotime($endDate)), 0, 1, 'L');
    $pdf->Ln(2);

    // KPI boxes
    $kpis = [
        ['Entrepôts', (string)$sitesCount],
        ['Produits', (string)count($stock)],
        ['Valeur stock', money_fcfa($totalValue)],
        ['Mouvements', (string)count($transactions)],
        ['Factures', (string)count($invoices)],
        ['Alertes', (string)$alerts],
    ];
    $boxW = 30;
    $x0 = $pdf->GetX();
    $y0 = $pdf->GetY();
    foreach ($kpis as $i => $kpi) {
        $x = $x0 + ($i % 6) * ($boxW + 1.5);
        $pdf->SetXY($x, $y0);
        $pdf->SetFillColor(240, 244, 248);
        $pdf->SetDrawColor(200, 210, 220);
        $pdf->RoundedRect($x, $y0, $boxW, 18, 2, '1111', 'DF');
        $pdf->SetXY($x, $y0 + 2);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell($boxW, 5, $kpi[0], 0, 2, 'C');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(15, 52, 96);
        $pdf->Cell($boxW, 8, $kpi[1], 0, 0, 'C');
    }
    $pdf->SetY($y0 + 22);
    $pdf->SetTextColor(33, 37, 41);

    // Entrepôts
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, '1. Entrepôts / Sites (' . $sitesCount . ')', 0, 1);
    $pdf->SetFont('helvetica', '', 8);
    $htmlSites = '<table border="1" cellpadding="4">
        <tr style="background-color:#0f3460;color:#fff;">
            <th width="28%"><b>Nom</b></th>
            <th width="22%"><b>Ville</b></th>
            <th width="25%"><b>Responsable</b></th>
            <th width="12%"><b>Qté</b></th>
            <th width="13%"><b>Valeur</b></th>
        </tr>';
    foreach ($sites as $s) {
        $sum = $db->prepare("SELECT COALESCE(SUM(si.quantite),0), COALESCE(SUM(si.quantite * p.prix_achat),0)
            FROM site_inventaire si JOIN produits p ON p.id = si.produit_id WHERE si.site_id = ?");
        $sum->execute([$s['id']]);
        $sr = $sum->fetch(PDO::FETCH_NUM);
        $htmlSites .= '<tr>
            <td>' . htmlspecialchars($s['nom']) . '</td>
            <td>' . htmlspecialchars($s['ville']) . '</td>
            <td>' . htmlspecialchars($s['responsable']) . '</td>
            <td align="right">' . (int)$sr[0] . '</td>
            <td align="right">' . number_format((float)$sr[1], 0, ',', ' ') . '</td>
        </tr>';
    }
    $htmlSites .= '</table>';
    $pdf->writeHTML($htmlSites, true, false, false, false, '');

    // Transactions
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, '2. Transactions / Mouvements (' . count($transactions) . ')', 0, 1);
    $pdf->SetFont('helvetica', '', 8);
    $htmlTx = '<table border="1" cellpadding="3">
        <tr style="background-color:#0f3460;color:#fff;">
            <th width="18%"><b>Date</b></th>
            <th width="12%"><b>Type</b></th>
            <th width="35%"><b>Produit</b></th>
            <th width="10%"><b>Qté</b></th>
            <th width="25%"><b>Utilisateur</b></th>
        </tr>';
    $txLimit = array_slice($transactions, 0, 40);
    if (empty($txLimit)) {
        $htmlTx .= '<tr><td colspan="5" align="center">Aucune transaction sur la période</td></tr>';
    } else {
        foreach ($txLimit as $tx) {
            $typeColor = $tx['type'] === 'entree' ? '#198754' : '#dc3545';
            $htmlTx .= '<tr>
                <td>' . date('d/m/Y H:i', strtotime($tx['created_at'])) . '</td>
                <td style="color:' . $typeColor . ';"><b>' . strtoupper($tx['type']) . '</b></td>
                <td>' . htmlspecialchars($tx['produit']) . '</td>
                <td align="right">' . (int)$tx['quantite'] . '</td>
                <td>' . htmlspecialchars($tx['utilisateur']) . '</td>
            </tr>';
        }
    }
    if (count($transactions) > 40) {
        $htmlTx .= '<tr><td colspan="5" align="center">… ' . (count($transactions) - 40) . ' autres lignes (voir export Excel)</td></tr>';
    }
    $htmlTx .= '</table>';
    $pdf->writeHTML($htmlTx, true, false, false, false, '');

    // Stock
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, '3. Détail du stock global', 0, 1);
    $pdf->SetFont('helvetica', '', 8);
    $htmlStock = '<table border="1" cellpadding="3">
        <tr style="background-color:#0f3460;color:#fff;">
            <th width="16%"><b>Réf.</b></th>
            <th width="30%"><b>Produit</b></th>
            <th width="18%"><b>Catégorie</b></th>
            <th width="10%"><b>Qté</b></th>
            <th width="13%"><b>P. Achat</b></th>
            <th width="13%"><b>Valeur</b></th>
        </tr>';
    foreach ($stock as $row) {
        $htmlStock .= '<tr>
            <td>' . htmlspecialchars($row['reference']) . '</td>
            <td>' . htmlspecialchars($row['nom']) . '</td>
            <td>' . htmlspecialchars($row['categorie']) . '</td>
            <td align="right">' . (int)$row['quantite'] . '</td>
            <td align="right">' . number_format((float)$row['prix_achat'], 0, ',', ' ') . '</td>
            <td align="right">' . number_format((float)$row['valeur_stock'], 0, ',', ' ') . '</td>
        </tr>';
    }
    $htmlStock .= '<tr style="background-color:#e9ecef;">
        <td colspan="5" align="right"><b>TOTAL</b></td>
        <td align="right"><b>' . number_format($totalValue, 0, ',', ' ') . '</b></td>
    </tr></table>';
    $pdf->writeHTML($htmlStock, true, false, false, false, '');

    // Factures période
    $pdf->Ln(4);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, '4. Factures de la période (' . count($invoices) . ')', 0, 1);
    $pdf->SetFont('helvetica', '', 8);
    $htmlInv = '<table border="1" cellpadding="3">
        <tr style="background-color:#0f3460;color:#fff;">
            <th width="16%"><b>N°</b></th>
            <th width="18%"><b>Date</b></th>
            <th width="28%"><b>Client</b></th>
            <th width="18%"><b>Montant</b></th>
            <th width="20%"><b>Paiement</b></th>
        </tr>';
    $invTotal = 0;
    foreach ($invoices as $inv) {
        $invTotal += (float)$inv['montant_final'];
        $htmlInv .= '<tr>
            <td>' . htmlspecialchars($inv['numero_facture']) . '</td>
            <td>' . date('d/m/Y', strtotime($inv['date_facture'])) . '</td>
            <td>' . htmlspecialchars($inv['client_nom']) . '</td>
            <td align="right">' . number_format((float)$inv['montant_final'], 0, ',', ' ') . '</td>
            <td>' . htmlspecialchars($inv['mode_paiement']) . '</td>
        </tr>';
    }
    if (empty($invoices)) {
        $htmlInv .= '<tr><td colspan="5" align="center">Aucune facture</td></tr>';
    }
    $htmlInv .= '<tr style="background-color:#e9ecef;">
        <td colspan="3" align="right"><b>CA période</b></td>
        <td align="right"><b>' . number_format($invTotal, 0, ',', ' ') . '</b></td>
        <td></td>
    </tr></table>';
    $pdf->writeHTML($htmlInv, true, false, false, false, '');

    $pdf->Output('rapport_stockmaster_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

// ===== PAGE DATA =====
$sites = get_sites($db);
$sitesCount = count($sites);
$stock = fetch_stock_detail($db);
$transactions = fetch_transactions($db, $startDate, $endDate);
$invoices = fetch_invoices($db, $startDate, $endDate);
$totalProducts = count($stock);
$totalValue = array_sum(array_map(static fn($r) => (float)$r['valeur_stock'], $stock));
$alertsCount = (int)$db->query("SELECT COUNT(*) FROM produits p JOIN stock s ON p.id = s.produit_id WHERE s.quantite <= p.seuil_alerte")->fetchColumn();
$movementsCount = count($transactions);
$invoicesCount = count($invoices);
$invoiceCA = array_sum(array_map(static fn($r) => (float)$r['montant_final'], $invoices));

$siteStats = [];
foreach ($sites as $s) {
    $st = $db->prepare("SELECT COALESCE(SUM(si.quantite),0) AS q, COALESCE(SUM(si.quantite * p.prix_achat),0) AS v
        FROM site_inventaire si JOIN produits p ON p.id = si.produit_id WHERE si.site_id = ?");
    $st->execute([$s['id']]);
    $siteStats[$s['id']] = $st->fetch(PDO::FETCH_ASSOC);
}

$movementsData = $db->query("
    SELECT DATE(created_at) as date, type, SUM(quantite) as total
    FROM mouvements
    WHERE created_at BETWEEN '$startDate' AND '$endDate 23:59:59'
    GROUP BY DATE(created_at), type
    ORDER BY date
")->fetchAll(PDO::FETCH_ASSOC);

$categoriesData = $db->query("
    SELECT COALESCE(c.nom, 'Sans catégorie') as category,
           COUNT(p.id) as product_count,
           COALESCE(SUM(s.quantite),0) as total_quantity,
           COALESCE(SUM(s.quantite * p.prix_achat),0) as total_value
    FROM produits p
    LEFT JOIN categories c ON p.categorie_id = c.id
    LEFT JOIN stock s ON p.id = s.produit_id
    GROUP BY c.id
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .card-hover { transition: transform .2s, box-shadow .2s; }
        .card-hover:hover { transform: translateY(-3px); box-shadow: 0 8px 18px rgba(0,0,0,.12); }
        .chart-container { position: relative; height: 300px; width: 100%; }
        .kpi-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; opacity: .8; }
    </style>
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div id="content">
        <?php include 'includes/navbar.php'; ?>
        <div class="container-fluid px-4">
            <div class="row my-4">
                <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Rapports</h2>
                    <div class="btn-group">
                        <a href="?export=excel_stock&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-success">
                            <i class="fas fa-file-excel me-1"></i> Excel stock
                        </a>
                        <a href="?export=excel_transactions&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-outline-success">
                            <i class="fas fa-file-excel me-1"></i> Excel transactions
                        </a>
                        <a href="?export=pdf&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-danger">
                            <i class="fas fa-file-pdf me-1"></i> PDF professionnel
                        </a>
                    </div>
                </div>
                <hr class="mt-3">
            </div>

            <div class="card mb-4 shadow">
                <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-filter me-2"></i> Période</h5></div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Date de début</label>
                            <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Date de fin</label>
                            <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Appliquer</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-2 col-md-4 mb-3">
                    <div class="card border-start border-primary border-4 shadow h-100 card-hover">
                        <div class="card-body">
                            <div class="kpi-label text-primary">Entrepôts</div>
                            <div class="h4 mb-0"><?= $sitesCount ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 mb-3">
                    <div class="card border-start border-info border-4 shadow h-100 card-hover">
                        <div class="card-body">
                            <div class="kpi-label text-info">Produits</div>
                            <div class="h4 mb-0"><?= $totalProducts ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 mb-3">
                    <div class="card border-start border-success border-4 shadow h-100 card-hover">
                        <div class="card-body">
                            <div class="kpi-label text-success">Valeur stock</div>
                            <div class="h6 mb-0"><?= money_fcfa($totalValue) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 mb-3">
                    <div class="card border-start border-warning border-4 shadow h-100 card-hover">
                        <div class="card-body">
                            <div class="kpi-label text-warning">Alertes</div>
                            <div class="h4 mb-0"><?= $alertsCount ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 mb-3">
                    <div class="card border-start border-secondary border-4 shadow h-100 card-hover">
                        <div class="card-body">
                            <div class="kpi-label">Transactions</div>
                            <div class="h4 mb-0"><?= $movementsCount ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 mb-3">
                    <div class="card border-start border-danger border-4 shadow h-100 card-hover">
                        <div class="card-body">
                            <div class="kpi-label text-danger">CA factures</div>
                            <div class="h6 mb-0"><?= money_fcfa($invoiceCA) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Entrepôts -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-warehouse me-2"></i> Entrepôts (<?= $sitesCount ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Ville</th>
                                    <th>Responsable</th>
                                    <th>Quantité</th>
                                    <th>Valeur</th>
                                    <th>Exports</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sites)): ?>
                                    <tr><td colspan="6" class="text-center text-muted">Aucun entrepôt</td></tr>
                                <?php else: ?>
                                    <?php foreach ($sites as $s): ?>
                                        <?php $st = $siteStats[$s['id']] ?? ['q' => 0, 'v' => 0]; ?>
                                        <tr>
                                            <td><a href="view_site.php?id=<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nom']) ?></a></td>
                                            <td><?= htmlspecialchars($s['ville']) ?></td>
                                            <td><?= htmlspecialchars($s['responsable']) ?></td>
                                            <td><span class="badge <?= (int)$st['q'] > 0 ? 'bg-success' : 'bg-danger' ?>"><?= (int)$st['q'] ?></span></td>
                                            <td><?= money_fcfa($st['v']) ?></td>
                                            <td class="text-nowrap">
                                                <a class="btn btn-sm btn-success" title="Excel inventaire" href="?export=excel_site&site_id=<?= (int)$s['id'] ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>">
                                                    <i class="fas fa-file-excel"></i> Excel
                                                </a>
                                                <a class="btn btn-sm btn-danger" title="PDF inventaire" href="?export=pdf_site&site_id=<?= (int)$s['id'] ?>">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </a>
                                                <a class="btn btn-sm btn-outline-primary" href="site_inventory.php?site_id=<?= (int)$s['id'] ?>">
                                                    <i class="fas fa-list"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-8 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Mouvements</h5></div>
                        <div class="card-body"><div class="chart-container"><canvas id="movementsChart"></canvas></div></div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Catégories</h5></div>
                        <div class="card-body"><div class="chart-container"><canvas id="categoriesChart"></canvas></div></div>
                    </div>
                </div>
            </div>

            <!-- Listing transactions -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i> Listing des transactions (<?= $movementsCount ?>)</h5>
                    <a href="?export=excel_transactions&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-sm btn-light">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="txTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Référence</th>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Motif</th>
                                    <th>Utilisateur</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></td>
                                        <td>
                                            <?php if ($tx['type'] === 'entree'): ?>
                                                <span class="badge bg-success"><?= t('entry') ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><?= t('exit') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($tx['reference']) ?></td>
                                        <td><?= htmlspecialchars($tx['produit']) ?></td>
                                        <td><?= (int)$tx['quantite'] ?></td>
                                        <td><?= htmlspecialchars($tx['motif'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($tx['utilisateur']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Factures -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-receipt me-2"></i> Factures (<?= $invoicesCount ?>)</h5>
                    <span class="badge bg-light text-dark">CA : <?= money_fcfa($invoiceCA) ?></span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="invTable">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Montant</th>
                                    <th>Paiement</th>
                                    <th>Ticket PDF</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($inv['numero_facture']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($inv['date_facture'])) ?></td>
                                        <td><?= htmlspecialchars($inv['client_nom']) ?></td>
                                        <td><?= money_fcfa($inv['montant_final']) ?></td>
                                        <td><?= htmlspecialchars($inv['mode_paiement']) ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-danger" href="invoice_pdf.php?id=<?= (int)$inv['id'] ?>" target="_blank">
                                                <i class="fas fa-file-pdf"></i> Ticket caisse
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Stock detail -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-table me-2"></i> Détail des produits</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="productsTable">
                            <thead>
                                <tr>
                                    <th>Référence</th>
                                    <th>Produit</th>
                                    <th>Catégorie</th>
                                    <th>Quantité</th>
                                    <th>Prix Achat</th>
                                    <th>Prix Vente</th>
                                    <th>Valeur Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stock as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['reference']) ?></td>
                                        <td><?= htmlspecialchars($product['nom']) ?></td>
                                        <td><?= htmlspecialchars($product['categorie']) ?></td>
                                        <td><?= (int)$product['quantite'] ?></td>
                                        <td><?= number_format((float)$product['prix_achat'], 0, ',', ' ') ?></td>
                                        <td><?= number_format((float)$product['prix_vente'], 0, ',', ' ') ?></td>
                                        <td><?= number_format((float)$product['valeur_stock'], 0, ',', ' ') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function() {
    $('#productsTable, #txTable, #invTable').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json' },
        pageLength: 10,
        order: []
    });

    const movementsData = <?= json_encode($movementsData, JSON_UNESCAPED_UNICODE) ?>;
    const dates = [...new Set(movementsData.map(d => d.date))];
    const entries = dates.map(date => {
        const row = movementsData.find(d => d.date === date && d.type === 'entree');
        return row ? Number(row.total) : 0;
    });
    const exits = dates.map(date => {
        const row = movementsData.find(d => d.date === date && d.type === 'sortie');
        return row ? Number(row.total) : 0;
    });

    new Chart(document.getElementById('movementsChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [
                { label: <?= json_encode(t('entries'), JSON_UNESCAPED_UNICODE) ?>, data: entries, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.15)', fill: true, tension: .25 },
                { label: <?= json_encode(t('exits'), JSON_UNESCAPED_UNICODE) ?>, data: exits, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,.15)', fill: true, tension: .25 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });

    new Chart(document.getElementById('categoriesChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($categoriesData, 'category'), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                data: <?= json_encode(array_map('floatval', array_column($categoriesData, 'total_value'))) ?>,
                backgroundColor: ['#0f3460','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#fd7e14','#6f42c1']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '65%' }
    });
});
</script>
</body>
</html>
