<?php
require_once 'config.php';
checkAuth();

// Tableau de bord complet réservé à l'administrateur
if (!is_admin()) {
    header('Location: ' . role_home_page());
    exit;
}

$theme = getCurrentTheme();

// Chart data (server-side → faster dashboard, no double AJAX)
$chartLabels = [];
$chartEntries = [];
$chartExits = [];
try {
    $days = 30;
    $dates = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = new DateTime("-{$i} days");
        $dates[$d->format('Y-m-d')] = $d->format('d/m');
    }
    $stmt = $db->prepare("SELECT DATE(created_at) as d,
        SUM(CASE WHEN type = 'entree' THEN quantite ELSE 0 END) as entrees,
        SUM(CASE WHEN type = 'sortie' THEN quantite ELSE 0 END) as sorties
        FROM mouvements WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)");
    $stmt->execute([$days - 1]);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[$row['d']] = $row;
    }
    foreach ($dates as $key => $label) {
        $chartLabels[] = $label;
        $chartEntries[] = (int)($map[$key]['entrees'] ?? 0);
        $chartExits[] = (int)($map[$key]['sorties'] ?? 0);
    }
} catch (Exception $e) {}

$catLabels = [];
$catValues = [];
try {
    $cats = $db->query("SELECT c.nom, COUNT(p.id) AS cnt FROM categories c LEFT JOIN produits p ON c.id = p.categorie_id GROUP BY c.id ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cats as $c) {
        $catLabels[] = $c['nom'];
        $catValues[] = (int)$c['cnt'];
    }
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" data-bs-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= function_exists('t') ? t('dashboard_title') : 'Dashboard' ?> - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
    <div class="wrapper">
        <!-- Sidebar -->
        <?php
        include_once('includes/sidebar.php');
        ?>
        <!-- Page Content -->
        <div id="content">
            <!-- Top Navigation -->
            <?php
            include_once('includes/navbar.php');
            ?>
            <!-- Dashboard Content -->
            <div class="container-fluid px-4">
                <div class="row my-4">
                    <div class="col-12">
                        <h2 class="mb-4"><i class="fas fa-tachometer-alt me-2"></i> <?= function_exists('t') ? t('dashboard_title') : 'Dashboard' ?></h2>
                        <hr>
                    </div>
                </div>

                <!-- Info Cards -->
                <div class="row">
                    <?php
                    // Récupérer les stats
                    $totalProducts = $db->query("SELECT COUNT(*) FROM produits")->fetchColumn();
                    $totalValue = $db->query("SELECT SUM(quantite * prix_achat) FROM produits JOIN stock ON produits.id = stock.produit_id")->fetchColumn();
                    $alertsCount = $db->query("SELECT COUNT(*) FROM produits JOIN stock ON produits.id = stock.produit_id WHERE stock.quantite <= produits.seuil_alerte")->fetchColumn();
                    $suppliersCount = $db->query("SELECT COUNT(*) FROM fournisseurs")->fetchColumn();
                    ?>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            <?= function_exists('t') ? t('products_in_stock') : 'Products in stock' ?></div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalProducts ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-boxes fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            <?= function_exists('t') ? t('total_stock_value') : 'Total stock value' ?></div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= number_format($totalValue, 0, ',', ' ') ?> FCFA
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            <?= function_exists('t') ? t('stock_alerts') : 'Stock alerts' ?></div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $alertsCount ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            <?= function_exists('t') ? t('suppliers') : 'Suppliers' ?></div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $suppliersCount ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-truck fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (is_admin() || is_superviseur()):
                    try {
                        $supplierDebt = (float)$db->query("SELECT COALESCE(SUM(montant_total - montant_paye),0) FROM fournisseur_cargaisons WHERE montant_total > montant_paye")->fetchColumn();
                        $supplierRanking = $db->query("
                            SELECT f.nom,
                                   COALESCE(SUM(c.montant_total - c.montant_paye),0) AS dette,
                                   COALESCE(SUM(c.montant_paye),0) AS paye,
                                   COUNT(c.id) AS nb
                            FROM fournisseurs f
                            LEFT JOIN fournisseur_cargaisons c ON c.fournisseur_id = f.id
                            GROUP BY f.id
                            HAVING nb > 0
                            ORDER BY dette DESC, paye DESC
                        ")->fetchAll(PDO::FETCH_ASSOC);
                        $bestMerchants = array_values(array_filter($supplierRanking, static fn($r) => (float)$r['dette'] <= 0));
                        $bestMerchants = array_slice($bestMerchants, 0, 5);
                        $worstMerchants = array_values(array_filter($supplierRanking, static fn($r) => (float)$r['dette'] > 0));
                        $worstMerchants = array_slice($worstMerchants, 0, 5);
                    } catch (Exception $e) {
                        $supplierDebt = 0;
                        $bestMerchants = [];
                        $worstMerchants = [];
                    }
                ?>
                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1"><?= t('total_debt') ?></div>
                                <div class="h5 mb-0 font-weight-bold text-danger"><?= number_format($supplierDebt, 0, ',', ' ') ?> FCFA</div>
                                <a href="supplier_finance.php" class="small"><?= t('supplier_finance') ?> →</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-success text-white py-2"><?= t('best_suppliers') ?></div>
                            <div class="card-body p-2">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($bestMerchants as $m): ?>
                                        <li class="list-group-item d-flex justify-content-between px-2 py-1">
                                            <span><?= htmlspecialchars($m['nom']) ?></span>
                                            <span class="badge bg-success"><?= t('good_merchant') ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (empty($bestMerchants)): ?><li class="list-group-item text-muted px-2">—</li><?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-12 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-danger text-white py-2"><?= t('worst_suppliers') ?></div>
                            <div class="card-body p-2">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($worstMerchants as $m): ?>
                                        <li class="list-group-item d-flex justify-content-between px-2 py-1">
                                            <span><?= htmlspecialchars($m['nom']) ?></span>
                                            <span class="text-danger fw-bold"><?= number_format((float)$m['dette'], 0, ',', ' ') ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (empty($worstMerchants)): ?><li class="list-group-item text-muted px-2">—</li><?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Charts and Tables -->
                <div class="row">
                    <!-- Stock Chart -->
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary"><?= function_exists('t') ? t('stock_trend_30_days') : 'Stock trend (last 30 days)' ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area" style="height:320px;position:relative">
                                    <canvas id="stockChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Stock Distribution -->
                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary"><?= function_exists('t') ? t('distribution_by_category') : 'Distribution by category' ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4 pb-2" style="height:260px;position:relative">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                                <div class="mt-4 text-center small" id="categoryLegend">
                                    <!-- Legend will be inserted here by JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity and Low Stock -->
                <div class="row">
                    <!-- Recent Activity -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?= function_exists('t') ? t('activity') : 'Recent activity' ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="activityTable" width="100%" cellspacing="0">
                                        <thead class="<?= $theme == 'dark' ? 'table-dark' : '' ?>">
                                            <tr>
                                                <th><?= function_exists('t') ? t('date') : 'Date' ?></th>
                                                <th><?= function_exists('t') ? t('action') : 'Action' ?></th>
                                                <th><?= function_exists('t') ? t('product') : 'Product' ?></th>
                                                <th><?= function_exists('t') ? t('quantity') : 'Quantity' ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $stmt = $db->query("
                                                SELECT m.created_at, m.type, m.quantite, p.nom as produit, u.nom as utilisateur
                                                FROM mouvements m
                                                JOIN produits p ON m.produit_id = p.id
                                                JOIN utilisateurs u ON m.utilisateur_id = u.id
                                                ORDER BY m.created_at DESC
                                                LIMIT 5
                                            ");
                                            while ($row = $stmt->fetch()):
                                            ?>
                                                <tr>
                                                    <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                                    <td><?= $row['type'] == 'entree' ? (function_exists('t') ? t('entry') : 'Entry') : (function_exists('t') ? t('exit') : 'Exit') ?></td>
                                                    <td><?= htmlspecialchars($row['produit']) ?></td>
                                                    <td><?= $row['type'] == 'entree' ? '+' : '-' ?><?= $row['quantite'] ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Low Stock Alerts -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 bg-warning">
                                <h6 class="m-0 font-weight-bold text-white"><?= function_exists('t') ? t('low_stock_alerts') : 'Low stock alerts' ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="alertsTable" width="100%" cellspacing="0">
                                        <thead class="<?= $theme == 'dark' ? 'table-dark' : '' ?>">
                                            <tr>
                                                <th><?= function_exists('t') ? t('product') : 'Product' ?></th>
                                                <th><?= function_exists('t') ? t('current_stock') : 'Current stock' ?></th>
                                                <th><?= function_exists('t') ? t('threshold') : 'Threshold' ?></th>
                                                <th><?= function_exists('t') ? t('action') : 'Action' ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $stmt = $db->query("
                                                SELECT p.id, p.nom, p.seuil_alerte, s.quantite
                                                FROM produits p
                                                JOIN stock s ON p.id = s.produit_id
                                                WHERE s.quantite <= p.seuil_alerte
                                                ORDER BY s.quantite ASC
                                                LIMIT 5
                                            ");
                                            while ($row = $stmt->fetch()):
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['nom']) ?></td>
                                                    <td class="text-danger fw-bold"><?= $row['quantite'] ?></td>
                                                    <td><?= $row['seuil_alerte'] ?></td>
                                                    <td>
                                                        <a href="order_product.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
                                                            <?= function_exists('t') ? t('order') : 'Order' ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="chartI18n" class="d-none"
                     data-entries="<?= htmlspecialchars(t('entries'), ENT_QUOTES) ?>"
                     data-exits="<?= htmlspecialchars(t('exits'), ENT_QUOTES) ?>"></div>
                <script type="application/json" id="dashboard-stock-data"><?= json_encode([
                    'labels' => $chartLabels,
                    'entrees' => $chartEntries,
                    'sorties' => $chartExits,
                ], JSON_UNESCAPED_UNICODE) ?></script>
                <script type="application/json" id="dashboard-category-data"><?= json_encode([
                    'labels' => $catLabels,
                    'values' => $catValues,
                ], JSON_UNESCAPED_UNICODE) ?></script>
                <script>
                    (function () {
                        function readJson(id) {
                            var el = document.getElementById(id);
                            if (!el) return null;
                            try { return JSON.parse(el.textContent || 'null'); } catch (e) { return null; }
                        }
                        window.DASHBOARD_STOCK_CHART = readJson('dashboard-stock-data');
                        window.DASHBOARD_CATEGORY_CHART = readJson('dashboard-category-data');
                        if (window.initDashboardCharts) {
                            setTimeout(function () { window.initDashboardCharts(); }, 50);
                        }
                    })();
                </script>
            </div>
        </div>
    </div>

    <!-- jQuery, Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="js/script.js"></script>
</body>

</html>