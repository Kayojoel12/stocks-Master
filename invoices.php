<?php
require_once 'config.php';
require_roles(['admin', 'caissier', 'superviseur']);

$theme = getCurrentTheme();
migrate_schema($db);

// Confirmation paiement reçu — une seule fois, non modifiable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    try {
        if (!can_cashier_ops()) {
            flash_set('Action réservée à la caisse.', 'error', 'invoices.php');
            header('Location: invoices.php');
            exit;
        }

        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            flash_set(t('error'), 'error', 'invoices.php');
            header('Location: invoices.php');
            exit;
        }

        $check = $db->prepare("SELECT id, paiement_recu, montant_final, mode_paiement FROM factures WHERE id = ?");
        $check->execute([$invoiceId]);
        $facture = $check->fetch(PDO::FETCH_ASSOC);

        if (!$facture) {
            flash_set('Facture introuvable.', 'error', 'invoices.php');
            header('Location: invoices.php');
            exit;
        }

        if ((int)$facture['paiement_recu'] === 1) {
            flash_set('Ce versement a déjà été confirmé et ne peut plus être modifié.', 'warning', 'invoices.php');
            header('Location: invoices.php');
            exit;
        }

        $allowedModes = ['cash', 'card', 'transfer', 'check'];
        $mode = strtolower(trim((string)($facture['mode_paiement'] ?? 'cash')));
        if (!in_array($mode, $allowedModes, true)) {
            $mode = 'cash';
        }

        $db->beginTransaction();

        $stmt = $db->prepare("UPDATE factures
            SET paiement_recu = 1,
                paiement_confirme_par = ?,
                paiement_confirme_at = NOW()
            WHERE id = ? AND paiement_recu = 0");
        $stmt->execute([$_SESSION['user_id'], $invoiceId]);

        if ($stmt->rowCount() === 0) {
            $db->rollBack();
            flash_set('Ce versement a déjà été confirmé et ne peut plus être modifié.', 'warning', 'invoices.php');
            header('Location: invoices.php');
            exit;
        }

        try {
            $db->exec("ALTER TABLE paiements MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
        } catch (Exception $e) {}

        $exists = $db->prepare("SELECT id FROM paiements WHERE facture_id = ? LIMIT 1");
        $exists->execute([$invoiceId]);
        if (!$exists->fetchColumn()) {
            $ins = $db->prepare("INSERT INTO paiements (facture_id, montant, mode_paiement, notes) VALUES (?, ?, ?, ?)");
            $ins->execute([
                $invoiceId,
                $facture['montant_final'],
                $mode,
                'Versement confirmé une seule fois — caissier #' . (int)$_SESSION['user_id']
            ]);
        }

        $db->commit();
        flash_set('Versement marqué comme reçu (définitif).', 'success', 'invoices.php');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        flash_set('Erreur lors de la confirmation : ' . $e->getMessage(), 'error', 'invoices.php');
    }

    header('Location: invoices.php');
    exit;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$filterStatus = $_GET['status'] ?? 'all';
$searchTerm = trim($_GET['search'] ?? '');

$where = " WHERE 1=1 ";
$params = [];
if ($filterStatus === 'paid') {
    $where .= " AND f.paiement_recu = 1 ";
} elseif ($filterStatus === 'unpaid') {
    $where .= " AND f.paiement_recu = 0 ";
}
if ($searchTerm !== '') {
    $where .= " AND (f.numero_facture LIKE ? OR f.client_nom LIKE ? OR f.client_contact LIKE ?) ";
    $like = '%' . $searchTerm . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM factures f" . $where);
$countStmt->execute($params);
$totalInvoices = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalInvoices / $perPage));

$sql = "SELECT f.*,
               CASE WHEN f.paiement_recu = 1 THEN 'paid' ELSE 'unpaid' END AS statut,
               u.nom AS caissier_nom
        FROM factures f
        LEFT JOIN utilisateurs u ON u.id = f.paiement_confirme_par
        $where
        ORDER BY f.date_facture DESC
        LIMIT $offset, $perPage";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('invoice_list') ?> - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.460.0/dist/umd/lucide.min.js" onload="try{lucide.createIcons()}catch(e){}"></script>
    <style>
        .invoice-status { padding: 5px 10px; border-radius: 20px; font-size: .85rem; font-weight: bold; }
        .status-paid { background: #d1e7dd; color: #0f5132; }
        .status-unpaid { background: #f8d7da; color: #842029; }
        [data-bs-theme="dark"] .status-paid { background: #ffc107; color: #1a1a1a; }
        [data-bs-theme="dark"] .status-unpaid { background: #ff8c00; color: #1a1a1a; }
        [data-bs-theme="dark"] .text-muted { color: #ffc107 !important; }
    </style>
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
<div class="wrapper">
    <?php include_once('includes/sidebar.php'); ?>
    <div id="content">
        <?php include_once('includes/navbar.php'); ?>
        <div class="container-fluid px-4">
            <div class="row my-4">
                <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2 class="mb-0"><i data-lucide="receipt" class="me-2"></i> <?= t('invoice_list') ?></h2>
                    <?php if (can_cashier_ops()): ?>
                    <a href="create_invoice.php" class="btn btn-primary">
                        <i data-lucide="plus" style="width:16px;height:16px"></i> <?= t('create_invoice') ?>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="col-12"><hr></div>
            </div>

            <?php include_once('includes/flash.php'); ?>
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="card mb-4 shadow">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <select class="form-select" name="status">
                                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>><?= t('status') ?>: All</option>
                                <option value="paid" <?= $filterStatus === 'paid' ? 'selected' : '' ?>><?= t('payment_received') ?></option>
                                <option value="unpaid" <?= $filterStatus === 'unpaid' ? 'selected' : '' ?>><?= t('payment_pending') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="<?= t('search') ?>...">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100"><?= t('filter') ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="<?= $theme == 'dark' ? 'table-dark' : '' ?>">
                            <tr>
                                <th>N°</th>
                                <th><?= t('date') ?></th>
                                <th>Client</th>
                                <th>Montant</th>
                                <th>Paiement</th>
                                <th><?= t('status') ?></th>
                                <th><?= t('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr><td colspan="7" class="text-center text-muted">Aucune facture</td></tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($invoice['numero_facture']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($invoice['date_facture'])) ?></td>
                                        <td><?= htmlspecialchars($invoice['client_nom']) ?></td>
                                        <td><?= number_format((float)$invoice['montant_final'], 0, ',', ' ') ?> FCFA</td>
                                        <td><?= htmlspecialchars($invoice['mode_paiement']) ?></td>
                                        <td>
                                            <?php if ((int)$invoice['paiement_recu'] === 1): ?>
                                                <span class="invoice-status status-paid"><?= t('payment_received') ?></span>
                                                <?php if (!empty($invoice['caissier_nom'])): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars($invoice['caissier_nom']) ?>
                                                        <?php if (!empty($invoice['paiement_confirme_at'])): ?>
                                                            — <?= date('d/m/Y H:i', strtotime($invoice['paiement_confirme_at'])) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="invoice-status status-unpaid"><?= t('payment_pending') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="invoice_pdf.php?id=<?= (int)$invoice['id'] ?>&download=1" class="btn btn-sm btn-danger" title="<?= t('download_ticket') ?>" data-spa-ignore="1" data-full-reload="1">
                                                <i data-lucide="download" style="width:14px;height:14px"></i>
                                            </a>
                                            <?php if (can_cashier_ops() && (int)$invoice['paiement_recu'] === 0): ?>
                                                <form method="POST" action="invoices.php" class="d-inline" data-spa-ignore="1"
                                                      onsubmit="return confirm('<?= t('confirm_payment_received') ?>\n\nAttention : confirmation définitive, non modifiable.');">
                                                    <input type="hidden" name="confirm_payment" value="1">
                                                    <input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>">
                                                    <input type="hidden" name="paiement_recu" value="1">
                                                    <button type="submit" class="btn btn-sm btn-success" title="<?= t('mark_payment_received') ?>">
                                                        <i data-lucide="banknote" style="width:14px;height:14px"></i>
                                                    </button>
                                                </form>
                                            <?php elseif ((int)$invoice['paiement_recu'] === 1): ?>
                                                <button type="button" class="btn btn-sm btn-warning" disabled title="Versement déjà confirmé (non modifiable)">
                                                    <i data-lucide="lock" style="width:14px;height:14px"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (can_delete()): ?>
                                                <a href="delete_invoice.php?id=<?= (int)$invoice['id'] ?>" class="btn btn-sm btn-outline-danger delete-invoice" title="<?= t('delete') ?>">
                                                    <i data-lucide="trash-2" style="width:14px;height:14px"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($searchTerm) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
