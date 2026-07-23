<?php
require_once 'config.php';
checkAuth();
require_roles(['admin', 'caissier', 'gestionnaire', 'superviseur']);

$theme = getCurrentTheme();
$showPurchasePrice = can_set_prices();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Recherche et filtrage
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$query = "SELECT p.*, c.nom as categorie, s.quantite 
          FROM produits p 
          LEFT JOIN categories c ON p.categorie_id = c.id 
          LEFT JOIN stock s ON p.id = s.produit_id 
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (p.nom LIKE ? OR p.reference LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $query .= " AND p.categorie_id = ?";
    $params[] = $category;
}

// Nombre total de produits
$stmt = $db->prepare(str_replace('p.*, c.nom as categorie, s.quantite', 'COUNT(*)', $query));
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Produits pour la page actuelle
$query .= " ORDER BY p.nom LIMIT $offset, $perPage";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Catégories pour le filtre
$categories = $db->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
    <div class="wrapper">
        <!-- Sidebar (identique à index.php) -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navigation (identique à index.php) -->
            <?php
                include_once('includes/navbar.php');
            ?>  

            <!-- Products Content -->
            <div class="container-fluid px-4">
                <div class="row my-4">
                    <div class="col-12">
                        <h2 class="mb-4"><i class="fas fa-boxes me-2"></i> Liste des produits</h2>
                        <hr>
                    </div>
                </div>
                <!-- Messages d'alerte -->
                <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>

                <!-- Filtres et recherche -->
                <div class="card mb-4 shadow">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Recherche</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" placeholder="Nom ou référence...">
                            </div>
                            <div class="col-md-4">
                                <label for="category" class="form-label">Catégorie</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">Toutes les catégories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i> Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Liste des produits -->
                <div class="card shadow mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Produits</h6>
                        <a href="add_inventory.php" class="btn btn-sm btn-success">
                            <i class="fas fa-plus me-1"></i> Ajouter
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead class="<?= $theme == 'dark' ? 'table-dark' : '' ?>">
                                    <tr>
                                        <th>Référence</th>
                                        <th>Nom</th>
                                        <th>Catégorie</th>
                                        <?php if ($showPurchasePrice): ?><th><?= t('price_buy') ?></th><?php endif; ?>
                                        <th><?= t('price_sell') ?></th>
                                        <th>Stock</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['reference']) ?></td>
                                        <td><?= htmlspecialchars($product['nom']) ?></td>
                                        <td><?= htmlspecialchars($product['categorie']) ?></td>
                                        <?php if ($showPurchasePrice): ?>
                                        <td><?= number_format($product['prix_achat'], 0, ',', ' ') ?> FCFA</td>
                                        <?php endif; ?>
                                        <td><?= number_format($product['prix_vente'], 0, ',', ' ') ?> FCFA</td>
                                        <td class="<?= $product['quantite'] <= $product['seuil_alerte'] ? 'text-danger fw-bold' : '' ?>">
                                            <?= $product['quantite'] ?>
                                        </td>
                                        <td>
                                            <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-primary" title="<?= t('edit') ?>">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <?php if (can_delete()): ?>
                                            <a href="delete_product.php?id=<?= $product['id'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('<?= t('confirm_delete') ?>')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="stock_movement.php?product_id=<?= $product['id'] ?>" class="btn btn-sm btn-secondary" title="<?= t('manage_stock') ?>">
                                                <i class="fas fa-right-left"></i>
                                            </a>
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
                                        <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= $search ?>&category=<?= $category ?>">
                                            Précédent
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= ceil($total / $perPage); $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= $search ?>&category=<?= $category ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < ceil($total / $perPage)): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= $search ?>&category=<?= $category ?>">
                                            Suivant
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery, Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/script.js"></script>
</body>
</html>