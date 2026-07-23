<?php
require_once 'config.php';
checkAuth();

$theme = getCurrentTheme();

// Liste des sites
$sites = $db->query("SELECT id, nom, ville, adresse, responsable, telephone FROM sites ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

$site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : (int)($sites[0]['id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$site = null;
$inventaire = [];
$stats = [
    'total_produits' => 0,
    'total_quantite' => 0,
    'en_alerte' => 0,
];

if ($site_id > 0) {
    $stmtSite = $db->prepare("SELECT * FROM sites WHERE id = ?");
    $stmtSite->execute([$site_id]);
    $site = $stmtSite->fetch(PDO::FETCH_ASSOC);

    if ($site) {
        // Uniquement les produits réellement présents dans cet entrepôt (quantité > 0)
        $sql = "SELECT
                    si.id,
                    si.quantite,
                    p.reference,
                    p.nom AS designation,
                    p.seuil_alerte,
                    c.nom AS categorie
                FROM site_inventaire si
                INNER JOIN produits p ON si.produit_id = p.id
                LEFT JOIN categories c ON p.categorie_id = c.id
                WHERE si.site_id = ? AND si.quantite > 0";
        $params = [$site_id];

        if ($search !== '') {
            $sql .= " AND (p.nom LIKE ? OR p.reference LIKE ? OR c.nom LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY p.nom";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $inventaire = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats['total_produits'] = count($inventaire);
        $stats['total_quantite'] = 0;
        $stats['en_alerte'] = 0;
        foreach ($inventaire as $row) {
            $stats['total_quantite'] += (int)$row['quantite'];
            if ((int)$row['quantite'] <= (int)$row['seuil_alerte']) {
                $stats['en_alerte']++;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= function_exists('t') ? t('site_inventory') : 'Inventaire par site' ?> - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
    <div class="wrapper">
        <?php include_once('includes/sidebar.php'); ?>
        <div id="content">
            <?php include_once('includes/navbar.php'); ?>

            <div class="container-fluid px-4">
                <div class="row my-4">
                    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h2 class="mb-0">
                            <i class="fas fa-boxes me-2"></i>
                            <?= function_exists('t') ? t('site_inventory') : 'Inventaire par site' ?>
                        </h2>
                        <a href="add_inventory.php<?= $site_id ? '?site_id=' . (int)$site_id : '' ?>" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>
                            <?= function_exists('t') ? t('add_product_location') : 'Ajouter produit/emplacement' ?>
                        </a>
                    </div>
                    <div class="col-12"><hr></div>
                </div>

                <div class="card mb-4 shadow">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label"><?= function_exists('t') ? t('site') : 'Site' ?></label>
                                <select name="site_id" class="form-select" onchange="this.form.submit()">
                                    <?php if (empty($sites)): ?>
                                        <option value=""><?= function_exists('t') ? t('choose') : '-- Choisir --' ?></option>
                                    <?php else: ?>
                                        <?php foreach ($sites as $s): ?>
                                            <option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $site_id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($s['nom']) ?> — <?= htmlspecialchars($s['ville']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label"><?= function_exists('t') ? t('search') : 'Recherche' ?></label>
                                <input type="text" name="search" class="form-control"
                                       value="<?= htmlspecialchars($search) ?>"
                                       placeholder="Produit, référence, catégorie...">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i>
                                    <?= function_exists('t') ? t('filter') : 'Filtrer' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!$site): ?>
                    <div class="alert alert-warning">
                        Aucun site disponible. <a href="add_site.php">Ajouter un site</a>
                    </div>
                <?php else: ?>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card shadow h-100">
                                <div class="card-body">
                                    <h5 class="card-title mb-3"><?= htmlspecialchars($site['nom']) ?></h5>
                                    <p class="mb-1"><i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($site['adresse']) ?></p>
                                    <p class="mb-1"><i class="fas fa-city me-2"></i><?= htmlspecialchars($site['ville']) ?>, <?= htmlspecialchars($site['pays']) ?></p>
                                    <p class="mb-1"><i class="fas fa-user me-2"></i><?= htmlspecialchars($site['responsable']) ?></p>
                                    <?php if (!empty($site['telephone'])): ?>
                                        <p class="mb-0"><i class="fas fa-phone me-2"></i><?= htmlspecialchars($site['telephone']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="card shadow text-center h-100">
                                        <div class="card-body">
                                            <div class="text-muted small"><?= function_exists('t') ? t('products') : 'Produits' ?></div>
                                            <div class="fs-3 fw-bold"><?= (int)$stats['total_produits'] ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card shadow text-center h-100">
                                        <div class="card-body">
                                            <div class="text-muted small"><?= function_exists('t') ? t('quantity') : 'Quantité' ?></div>
                                            <div class="fs-3 fw-bold"><?= (int)$stats['total_quantite'] ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card shadow text-center h-100">
                                        <div class="card-body">
                                            <div class="text-muted small"><?= function_exists('t') ? t('stock_alerts') : 'Alertes' ?></div>
                                            <div class="fs-3 fw-bold text-warning"><?= (int)$stats['en_alerte'] ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">
                                Inventaire — <?= htmlspecialchars($site['nom']) ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle" width="100%">
                                    <thead class="<?= $theme == 'dark' ? 'table-dark' : '' ?>">
                                        <tr>
                                            <th><?= function_exists('t') ? t('reference') : 'Référence' ?></th>
                                            <th><?= function_exists('t') ? t('designation') : 'Désignation' ?></th>
                                            <th><?= function_exists('t') ? t('category') : 'Catégorie' ?></th>
                                            <th><?= function_exists('t') ? t('quantity') : 'Quantité' ?></th>
                                            <th><?= function_exists('t') ? t('threshold') : 'Seuil' ?></th>
                                            <th><?= function_exists('t') ? t('status') : 'Statut' ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($inventaire)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    Aucun produit en inventaire pour ce site.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($inventaire as $item): ?>
                                                <?php
                                                $qty = (int)$item['quantite'];
                                                $seuil = (int)$item['seuil_alerte'];
                                                if ($qty <= 0) {
                                                    $badge = 'bg-danger';
                                                    $status = function_exists('t') ? t('out_of_stock') : 'Rupture';
                                                } elseif ($qty <= $seuil) {
                                                    $badge = 'bg-warning text-dark';
                                                    $status = function_exists('t') ? t('low_stock') : 'Stock faible';
                                                } else {
                                                    $badge = 'bg-success';
                                                    $status = function_exists('t') ? t('available') : 'Disponible';
                                                }
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['reference']) ?></td>
                                                    <td><?= htmlspecialchars($item['designation']) ?></td>
                                                    <td><?= htmlspecialchars($item['categorie'] ?? '—') ?></td>
                                                    <td><span class="badge <?= $badge ?>"><?= $qty ?></span></td>
                                                    <td><?= $seuil ?></td>
                                                    <td><span class="badge <?= $badge ?>"><?= $status ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
