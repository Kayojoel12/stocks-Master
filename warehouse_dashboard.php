<?php
require_once 'config.php';
require_roles(['gestionnaire', 'admin', 'superviseur']);

$theme = getCurrentTheme();
migrate_schema($db);

$isWm = is_warehouse_manager();
$siteId = $isWm ? get_user_site_id($db) : (isset($_GET['site_id']) ? (int)$_GET['site_id'] : get_user_site_id($db));

$site = null;
if ($siteId > 0) {
    $stmt = $db->prepare("SELECT * FROM sites WHERE id = ?");
    $stmt->execute([$siteId]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);
}

$sites = [];
if (!$isWm) {
    $sites = $db->query("SELECT id, nom FROM sites ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
}

$inventory = [];
$lowStock = [];
$totalQty = 0;
$totalSku = 0;
if ($siteId > 0) {
    $stmt = $db->prepare("
        SELECT p.id, p.nom, p.reference, p.seuil_alerte, si.quantite
        FROM site_inventaire si
        JOIN produits p ON p.id = si.produit_id
        WHERE si.site_id = ? AND si.quantite > 0
        ORDER BY si.quantite ASC, p.nom ASC
    ");
    $stmt->execute([$siteId]);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalSku = count($inventory);
    foreach ($inventory as $row) {
        $totalQty += (int)$row['quantite'];
        if ((int)$row['quantite'] <= (int)$row['seuil_alerte']) {
            $lowStock[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('warehouse_dashboard') ?> - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.460.0/dist/umd/lucide.min.js" onload="try{lucide.createIcons()}catch(e){}"></script>
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div id="content">
        <?php include 'includes/navbar.php'; ?>
        <div class="container-fluid px-4">
            <div class="row my-4">
                <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="mb-1"><i data-lucide="warehouse" class="me-2"></i><?= t('warehouse_dashboard') ?></h2>
                        <span class="badge <?= role_badge_class('gestionnaire') ?>"><?= t('warehouse_manager') ?></span>
                    </div>
                    <?php if (!$isWm && !empty($sites)): ?>
                    <form method="GET" class="d-flex gap-2">
                        <select name="site_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value=""><?= t('choose') ?> <?= t('site') ?></option>
                            <?php foreach ($sites as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= $siteId === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="col-12"><hr></div>
            </div>

            <?php if (!$site): ?>
                <div class="alert alert-warning">
                    <?= t('no_warehouse_assigned') ?>
                    <?php if (is_admin()): ?>
                        — <a href="users.php"><?= t('users') ?></a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card shadow h-100">
                            <div class="card-body">
                                <div class="text-muted"><?= t('site') ?></div>
                                <div class="h4"><?= htmlspecialchars($site['nom']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($site['adresse'] ?? '') ?> <?= htmlspecialchars($site['ville'] ?? '') ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow h-100">
                            <div class="card-body">
                                <div class="text-muted"><?= t('products') ?></div>
                                <div class="h3"><?= $totalSku ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow h-100">
                            <div class="card-body">
                                <div class="text-muted"><?= t('quantity') ?></div>
                                <div class="h3"><?= $totalQty ?></div>
                                <?php if (count($lowStock) > 0): ?>
                                    <span class="badge bg-warning text-dark"><?= count($lowStock) ?> <?= t('low_stock') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3 d-flex flex-wrap gap-2">
                    <a class="btn btn-primary" href="site_inventory.php?site_id=<?= (int)$siteId ?>"><?= t('site_inventory') ?></a>
                    <a class="btn btn-success" href="add_inventory.php?site_id=<?= (int)$siteId ?>"><?= t('add') ?> <?= t('stock') ?></a>
                    <a class="btn btn-outline-secondary" href="view_site.php?id=<?= (int)$siteId ?>"><?= t('site') ?></a>
                    <a class="btn btn-outline-primary" href="stock.php"><?= t('stock') ?></a>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-7">
                        <div class="card shadow">
                            <div class="card-header"><?= t('site_inventory') ?></div>
                            <div class="card-body table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead><tr>
                                        <th><?= t('product') ?></th>
                                        <th><?= t('reference') ?></th>
                                        <th><?= t('quantity') ?></th>
                                        <th><?= t('status') ?></th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach (array_slice($inventory, 0, 15) as $row):
                                            $low = (int)$row['quantite'] <= (int)$row['seuil_alerte'];
                                        ?>
                                        <tr class="<?= $low ? 'table-warning' : '' ?>">
                                            <td><?= htmlspecialchars($row['nom']) ?></td>
                                            <td><?= htmlspecialchars($row['reference']) ?></td>
                                            <td class="<?= $low ? 'text-danger fw-bold' : '' ?>"><?= (int)$row['quantite'] ?></td>
                                            <td><?= $low ? t('low_stock') : t('available') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($inventory)): ?>
                                            <tr><td colspan="4" class="text-center text-muted">—</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card shadow">
                            <div class="card-header bg-warning"><?= t('low_stock_alerts') ?></div>
                            <div class="card-body table-responsive">
                                <table class="table table-sm">
                                    <thead><tr><th><?= t('product') ?></th><th><?= t('quantity') ?></th><th><?= t('threshold') ?></th></tr></thead>
                                    <tbody>
                                        <?php foreach (array_slice($lowStock, 0, 10) as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['nom']) ?></td>
                                            <td class="text-danger fw-bold"><?= (int)$row['quantite'] ?></td>
                                            <td><?= (int)$row['seuil_alerte'] ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($lowStock)): ?>
                                            <tr><td colspan="3" class="text-center text-muted">—</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
