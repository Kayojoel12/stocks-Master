<?php
require_once 'config.php';

// === Vérification des constantes utilisées ===
if (!defined('COMPANY_NAME')) {
    define('COMPANY_NAME', 'StockMaster');
}
if (!defined('PHONE_NUMBER')) {
    define('PHONE_NUMBER', '+237 XXX XXX XXX');
}
// ADMIN_WHATSAPP est déjà défini dans config.php

require_roles(['admin', 'superviseur', 'gestionnaire']);

$theme = getCurrentTheme();

// --- Pagination ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// --- Filtres ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$lowStock = isset($_GET['low_stock']) && $_GET['low_stock'] == '1';

// --- Requête de comptage (pour pagination) ---
$countParams = [];
$countSql = "SELECT COUNT(*) FROM produits p 
             LEFT JOIN stock s ON p.id = s.produit_id 
             WHERE 1=1";
if (!empty($search)) {
    $countSql .= " AND (p.nom LIKE ? OR p.reference LIKE ?)";
    $countParams[] = "%$search%";
    $countParams[] = "%$search%";
}
if ($category > 0) {
    $countSql .= " AND p.categorie_id = ?";
    $countParams[] = $category;
}
if ($lowStock) {
    $countSql .= " AND COALESCE(s.quantite, 0) <= p.seuil_alerte";
}
$stmt = $db->prepare($countSql);
$stmt->execute($countParams);
$total = $stmt->fetchColumn();

// --- Requête principale ---
$params = [];
$sql = "SELECT p.id, p.nom, p.reference, p.seuil_alerte, 
               c.nom AS categorie, 
               COALESCE(s.quantite, 0) AS quantite,
               f.email AS fournisseur_email, 
               f.telephone AS fournisseur_tel, 
               f.nom AS fournisseur_nom
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        LEFT JOIN stock s ON p.id = s.produit_id
        LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (p.nom LIKE ? OR p.reference LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category > 0) {
    $sql .= " AND p.categorie_id = ?";
    $params[] = $category;
}
if ($lowStock) {
    $sql .= " AND COALESCE(s.quantite, 0) <= p.seuil_alerte";
}
$sql .= " ORDER BY quantite ASC LIMIT $offset, $perPage";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$stockItems = $stmt->fetchAll();

// --- Catégories pour le filtre ---
$categories = $db->query("SELECT id, nom FROM categories ORDER BY nom")->fetchAll();

// --- Derniers mouvements entrants ---
$incoming = $db->query("
    SELECT p.nom, m.quantite, m.created_at AS date
    FROM mouvements m
    JOIN produits p ON m.produit_id = p.id
    WHERE m.type = 'entree' AND m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY m.created_at DESC LIMIT 5
")->fetchAll();

// --- Dernières sorties (via factures) ---
$outgoing = $db->query("
    SELECT p.nom, fi.quantite, f.numero_facture, f.date_facture AS date
    FROM facture_items fi
    JOIN produits p ON fi.produit_id = p.id
    JOIN factures f ON fi.facture_id = f.id
    WHERE f.date_facture >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY f.date_facture DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du stock - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .table-card { border-left: 4px solid; transition: transform .3s; }
        .table-card:hover { transform: translateY(-5px); }
        .incoming-card { border-left-color: #28a745; }
        .outgoing-card { border-left-color: #dc3545; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; }
        .status-low { background: #fff3cd; color: #856404; }
        .status-out { background: #f8d7da; color: #721c24; }
        .status-ok { background: #d4edda; color: #155724; }
    </style>
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
<div class="wrapper">
    <?php include_once('includes/sidebar.php'); ?>
    <div id="content">
        <?php include_once('includes/navbar.php'); ?>
        <div class="container-fluid px-4">
            <div class="row my-4">
                <div class="col-12">
                    <h2 class="mb-4"><i class="fas fa-warehouse me-2"></i> <?= t('stock_management') ?></h2>
                    <hr>
                </div>
            </div>

            <!-- Filtres -->
            <div class="card mb-4 shadow">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label"><?= t('search') ?></label>
                            <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= t('name_or_ref') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= t('category') ?></label>
                            <select class="form-select" name="category">
                                <option value=""><?= t('all_categories') ?></option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="low_stock" value="1" <?= $lowStock ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= t('low_stock_only') ?></label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> <?= t('filter') ?>
                            </button>
                            <a href="stock.php" class="btn btn-secondary">
                                <i class="fas fa-sync-alt me-1"></i> <?= t('reset') ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tableau du stock -->
            <div class="card shadow mb-4">
                <div class="card-header"><h6 class="m-0 font-weight-bold text-primary"><?= t('stock_status') ?></h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead class="<?= $theme == 'dark' ? 'table-dark' : '' ?>">
                                <tr>
                                    <th><?= t('product') ?></th>
                                    <th><?= t('reference') ?></th>
                                    <th><?= t('category') ?></th>
                                    <th><?= t('quantity') ?></th>
                                    <th><?= t('threshold') ?></th>
                                    <th><?= t('email_supplier') ?></th>
                                    <th><?= t('status') ?></th>
                                    <th><?= t('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stockItems as $item):
                                    $qty = (int)$item['quantite'];
                                    $seuil = (int)$item['seuil_alerte'];
                                    $isOut = $qty <= 0;
                                    $isLow = $qty <= $seuil;
                                    $orderQty = max($seuil * 2 - $qty, 1);
                                    $waMsg = "Bonjour" . (!empty($item['fournisseur_nom']) ? ' ' . $item['fournisseur_nom'] : '') . ",\n\n"
                                        . "ALERTE STOCK — " . COMPANY_NAME . "\n"
                                        . "Produit: " . $item['nom'] . "\n"
                                        . "Référence: " . $item['reference'] . "\n"
                                        . "Stock actuel: " . $qty . "\n"
                                        . "Seuil: " . $seuil . "\n"
                                        . "Quantité à commander: " . $orderQty . "\n\n"
                                        . "Merci de confirmer disponibilité.\n"
                                        . "Contact: " . PHONE_NUMBER;
                                    $waPhone = !empty($item['fournisseur_tel']) ? $item['fournisseur_tel'] : get_super_admin_phone($db);
                                    $waUrl = whatsapp_link($waPhone, $waMsg);
                                ?>
                                <tr class="<?= $isOut ? 'table-danger' : ($isLow ? 'table-warning' : '') ?>">
                                    <td><?= htmlspecialchars($item['nom']) ?></td>
                                    <td><?= htmlspecialchars($item['reference']) ?></td>
                                    <td><?= htmlspecialchars($item['categorie'] ?? '—') ?></td>
                                    <td class="<?= $isLow ? 'text-danger fw-bold' : '' ?>"><?= $qty ?></td>
                                    <td><?= $seuil ?></td>
                                    <td><?= htmlspecialchars($item['fournisseur_email'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($isOut): ?>
                                            <span class="status-badge status-out"><?= t('out_of_stock') ?></span>
                                        <?php elseif ($isLow): ?>
                                            <span class="status-badge status-low"><?= t('low_stock') ?></span>
                                        <?php else: ?>
                                            <span class="status-badge status-ok"><?= t('available') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="stock_movement.php?product_id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-primary" title="<?= t('manage_stock') ?>">
                                            <i class="fas fa-exchange-alt"></i>
                                        </a>
                                        <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" class="btn btn-sm btn-success" title="<?= t('order_whatsapp') ?>">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-warning send-mail-btn"
                                            title="<?= t('email_supplier') ?>"
                                            data-product-id="<?= (int)$item['id'] ?>"
                                            data-product-name="<?= htmlspecialchars($item['nom'], ENT_QUOTES) ?>"
                                            data-reference="<?= htmlspecialchars($item['reference'], ENT_QUOTES) ?>"
                                            data-quantity="<?= $qty ?>"
                                            data-threshold="<?= $seuil ?>">
                                            <i class="fas fa-envelope"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&low_stock=<?= $lowStock ? 1 : 0 ?>"><?= t('previous') ?></a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= ceil($total / $perPage); $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&low_stock=<?= $lowStock ? 1 : 0 ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < ceil($total / $perPage)): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&low_stock=<?= $lowStock ? 1 : 0 ?>"><?= t('next') ?></a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>

            <!-- Entrants / Sortants -->
            <div class="row mt-4">
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow table-card incoming-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-arrow-down me-2"></i><?= t('incoming_products') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead><tr><th><?= t('product') ?></th><th><?= t('quantity') ?></th><th><?= t('date') ?></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($incoming as $p): ?>
                                            <tr><td><?= htmlspecialchars($p['nom']) ?></td><td class="text-success">+<?= $p['quantite'] ?></td><td><?= date('d/m/Y H:i', strtotime($p['date'])) ?></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="movements.php?type=entree" class="btn btn-sm btn-success mt-2"><i class="fas fa-list me-1"></i> <?= t('see_all_entries') ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow table-card outgoing-card">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-arrow-up me-2"></i><?= t('outgoing_products') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead><tr><th><?= t('product') ?></th><th><?= t('quantity') ?></th><th><?= t('invoicing') ?></th><th><?= t('date') ?></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($outgoing as $p): ?>
                                            <tr><td><?= htmlspecialchars($p['nom']) ?></td><td class="text-danger">-<?= $p['quantite'] ?></td><td><?= htmlspecialchars($p['numero_facture']) ?></td><td><?= date('d/m/Y H:i', strtotime($p['date'])) ?></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="movements.php?type=sortie" class="btn btn-sm btn-danger mt-2"><i class="fas fa-list me-1"></i> <?= t('see_all_exits') ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3 me-3" style="z-index:1100; max-width:350px;">
    <div id="mainToast" class="toast align-items-center text-bg-success border-0 w-100" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="mainToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/script.js"></script>
<script>
function showToast(msg, type='success') {
    const toast = document.getElementById('mainToast');
    document.getElementById('mainToastBody').textContent = msg;
    toast.className = 'toast align-items-center text-bg-' + (type === 'danger' ? 'danger' : 'success') + ' border-0';
    bootstrap.Toast.getOrCreateInstance(toast).show();
}
$(document).ready(function() {
    $('.send-mail-btn').click(function() {
        const btn = $(this);
        btn.prop('disabled', true);
        $.ajax({
            url: 'send_order.php',
            method: 'POST',
            data: {
                product_id: btn.data('product-id'),
                product_name: btn.data('product-name'),
                reference: btn.data('reference'),
                quantity: btn.data('quantity'),
                threshold: btn.data('threshold')
            },
            dataType: 'json',
            success: function(r) {
                if (r.success) {
                    showToast(r.message || 'Email envoyé !', 'success');
                    if (r.mailto) window.location.href = r.mailto;
                } else {
                    showToast(r.message || 'Erreur d\'envoi.', 'danger');
                }
            },
            error: function() { showToast('Erreur serveur.', 'danger'); },
            complete: function() { btn.prop('disabled', false); }
        });
    });
});
</script>
</body>
</html>
