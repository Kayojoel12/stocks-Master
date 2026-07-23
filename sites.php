<?php
require_once 'config.php';
require_roles(['admin', 'superviseur', 'gestionnaire']);

$theme = getCurrentTheme();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Recherche
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM sites WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (nom LIKE ? OR adresse LIKE ? OR ville LIKE ? OR pays LIKE ? OR responsable LIKE ? OR telephone LIKE ?)";
    $params = array_fill(0, 6, "%$search%");
}

// Nombre total de sites
$stmt = $db->prepare(str_replace('*', 'COUNT(*)', $query));
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Sites pour la page actuelle
$query .= " ORDER BY nom LIMIT $offset, $perPage";
$stmt = $db->prepare($query);
$stmt->execute($params);
$sites = $stmt->fetchAll();

// Quantités totales d'inventaire par site
$qtyBySite = [];
try {
    $qtyRows = $db->query("SELECT site_id, COALESCE(SUM(quantite), 0) AS total_qty FROM site_inventaire GROUP BY site_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($qtyRows as $row) {
        $qtyBySite[(int)$row['site_id']] = (int)$row['total_qty'];
    }
} catch (Exception $e) {
    $qtyBySite = [];
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" data-bs-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= function_exists('t') ? t('sites_list') : 'Sites' ?> - StockMaster</title>
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
                    <div class="col-12">
                        <h2 class="mb-4"><i class="fas fa-map-marked-alt me-2"></i> <?= function_exists('t') ? t('sites_list') : 'Site list' ?></h2>
                        <hr>
                    </div>
                </div>

                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Filtres et recherche -->
                <div class="card mb-4 shadow">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <label for="search" class="form-label"><?= function_exists('t') ? t('search') : 'Search' ?></label>
                                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= function_exists('t') ? t('search') : 'Search' ?>...">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i> <?= function_exists('t') ? t('filter') : 'Filter' ?>
                                </button>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <a href="add_site.php" class="btn btn-success w-100">
                                    <i class="fas fa-plus me-1"></i> <?= function_exists('t') ? t('add') : 'Add' ?>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Liste des sites -->
                <div class="card shadow mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><?= function_exists('t') ? t('site_management') : 'Sites' ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead class="<?= $theme == 'dark' ? 'table-dark' : '' ?>">
                                    <tr>
                                        <th><?= function_exists('t') ? t('designation') : 'Name' ?></th>
                                        <th>Address</th>
                                        <th>City</th>
                                        <th>Country</th>
                                        <th>Manager</th>
                                        <th><?= function_exists('t') ? t('phone') : 'Phone' ?></th>
                                        <th><?= function_exists('t') ? t('quantity') : 'Quantity' ?></th>
                                        <th><?= function_exists('t') ? t('created_at') : 'Created at' ?></th>
                                        <th><?= function_exists('t') ? t('actions') : 'Actions' ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sites as $site): ?>
                                        <?php $siteQty = $qtyBySite[(int)$site['id']] ?? 0; ?>
                                        <tr>
                                            <td><?= htmlspecialchars($site['nom']) ?></td>
                                            <td><?= htmlspecialchars($site['adresse']) ?></td>
                                            <td><?= htmlspecialchars($site['ville']) ?></td>
                                            <td><?= htmlspecialchars($site['pays']) ?></td>
                                            <td><?= htmlspecialchars($site['responsable']) ?></td>
                                            <td><?= htmlspecialchars($site['telephone']) ?></td>
                                            <td>
                                                <?php if ($siteQty > 0): ?>
                                                    <span class="badge bg-success"><?= $siteQty ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $site['date_creation'] ?></td>
                                            <td class="text-nowrap">
                                                <a href="view_site.php?id=<?= (int)$site['id'] ?>" class="btn btn-sm btn-info text-white" title="Voir les informations">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="site_inventory.php?site_id=<?= (int)$site['id'] ?>" class="btn btn-sm btn-secondary" title="Inventaire">
                                                    <i class="fas fa-boxes"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-success open-inventory-modal" data-site-id="<?= (int)$site['id'] ?>" title="<?= function_exists('t') ? t('add_product_location') : 'Add product/location' ?>">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <?php if (can_delete()): ?>
                                                <a href="delete_site.php?id=<?= (int)$site['id'] ?>" class="btn btn-sm btn-danger" title="<?= function_exists('t') ? t('delete') : 'Delete' ?>" onclick="return confirm('<?= function_exists('t') ? t('confirm_delete') : 'Delete this record?' ?>')"><i class="fas fa-trash"></i></a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>"><?= function_exists('t') ? t('previous') : 'Previous' ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= ceil($total / $perPage); $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($page < ceil($total / $perPage)): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>"><?= function_exists('t') ? t('next') : 'Next' ?></a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modale d'ajout d'inventaire -->
    <div class="modal fade" id="inventoryModal" tabindex="-1" aria-labelledby="inventoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="inventoryModalLabel"><?= function_exists('t') ? t('add_product_location') : 'Add product/location' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="modal-inventory-form">
                        <input type="hidden" name="site_id" id="modal-site-id">
                        <input type="hidden" name="mode" value="set">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label mb-0"><?= function_exists('t') ? t('product') : 'Product' ?></label>
                                <select class="form-select form-select-sm" name="produit_id" id="modal-produit-id">
                                    <option value=""><?= function_exists('t') ? t('choose') : '-- Choose --' ?></option>
                                    <?php foreach ($db->query('SELECT id, nom, reference FROM produits ORDER BY nom') as $prod): ?>
                                        <option value="<?= $prod['id'] ?>">[<?= htmlspecialchars($prod['reference']) ?>] <?= htmlspecialchars($prod['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control form-control-sm mt-1" name="new_produit" id="modal-new-produit" placeholder="<?= function_exists('t') ? t('new_product') : 'New product' ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0"><?= function_exists('t') ? t('category') : 'Category' ?></label>
                                <select class="form-select form-select-sm" name="categorie_id" id="modal-categorie-id">
                                    <option value=""><?= function_exists('t') ? t('choose') : '-- Choose --' ?></option>
                                    <?php foreach ($db->query('SELECT id, nom FROM categories ORDER BY nom') as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control form-control-sm mt-1" name="new_categorie" id="modal-new-categorie" placeholder="<?= function_exists('t') ? t('new_category') : 'New category' ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0"><?= function_exists('t') ? t('quantity') : 'Quantity' ?></label>
                                <input type="number" class="form-control form-control-sm" name="quantite" id="modal-quantite" min="1" value="1" required>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= function_exists('t') ? t('save') : 'Save' ?></button>
                                <span class="modal-inventory-msg ms-2"></span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <script>
        $(document).ready(function() {
            $('.open-inventory-modal').on('click', function() {
                var siteId = $(this).data('site-id');
                $('#modal-inventory-form')[0].reset();
                $('#modal-site-id').val(siteId);
                $('.modal-inventory-msg').text('');
                var modal = new bootstrap.Modal(document.getElementById('inventoryModal'));
                modal.show();
            });

            $('#modal-inventory-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var msg = form.find('.modal-inventory-msg');
                msg.text('');
                $.ajax({
                    url: 'add_inventory.php',
                    method: 'POST',
                    data: form.serialize() + '&ajax=1',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            msg.text(response.message || '<?= function_exists('t') ? t('add_success') : 'Added!' ?>').removeClass('text-danger').addClass('text-success');
                            var siteId = $('#modal-site-id').val();
                            form[0].reset();
                            $('#modal-site-id').val(siteId);
                        } else {
                            msg.text(response.message || '<?= function_exists('t') ? t('error') : 'Error' ?>').removeClass('text-success').addClass('text-danger');
                        }
                    },
                    error: function() {
                        msg.text('<?= function_exists('t') ? t('server_error') : 'Server error' ?>').removeClass('text-success').addClass('text-danger');
                    }
                });
            });
        });
    </script>
</body>

</html>