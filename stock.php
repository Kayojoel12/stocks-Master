<?php
// Inclusion du fichier de configuration et vérification de l'authentification
require_once 'config.php';
require_roles(['admin', 'superviseur', 'gestionnaire']);

// Récupération du thème actuel (clair ou sombre)
$theme = getCurrentTheme();

// --- Gestion de la pagination ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// --- Récupération des filtres de recherche ---
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$lowStock = isset($_GET['low_stock']) && $_GET['low_stock'] !== '' && $_GET['low_stock'] !== '0';

// --- Construction de la requête principale pour le stock ---
$query = "SELECT p.id, p.nom, p.reference, p.seuil_alerte, c.nom as categorie, s.quantite,
                 f.email as fournisseur_email, f.telephone as fournisseur_tel, f.nom as fournisseur_nom
          FROM produits p 
          LEFT JOIN categories c ON p.categorie_id = c.id 
          LEFT JOIN stock s ON p.id = s.produit_id 
          LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
          WHERE 1=1";

$params = [];

// Ajout des conditions de recherche si besoin
if (!empty($search)) {
    $query .= " AND (p.nom LIKE ? OR p.reference LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($category)) {
    $query .= " AND p.categorie_id = ?";
    $params[] = $category;
}
if ($lowStock) {
    $query .= " AND s.quantite <= p.seuil_alerte";
}

// --- Récupération du nombre total d'éléments pour la pagination ---
$countQuery = "SELECT COUNT(*)
          FROM produits p 
          LEFT JOIN categories c ON p.categorie_id = c.id 
          LEFT JOIN stock s ON p.id = s.produit_id 
          LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
          WHERE 1=1";
if (!empty($search)) {
    $countQuery .= " AND (p.nom LIKE ? OR p.reference LIKE ?)";
}
if (!empty($category)) {
    $countQuery .= " AND p.categorie_id = ?";
}
if ($lowStock) {
    $countQuery .= " AND s.quantite <= p.seuil_alerte";
}
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$total = $stmt->fetchColumn();

// --- Récupération des données de la page courante ---
$query .= " ORDER BY s.quantite ASC LIMIT $offset, $perPage";
$stmt = $db->prepare($query);
$stmt->execute($params);
$stockItems = $stmt->fetchAll();

// --- Récupération des catégories pour le filtre ---
$categories = $db->query("SELECT * FROM categories ORDER BY nom")->fetchAll();

// --- Récupération des 5 derniers produits entrants (7 jours) ---
$incomingProducts = $db->query("
    SELECT p.nom, m.quantite, m.created_at as date, m.type 
    FROM mouvements m
    JOIN produits p ON m.produit_id = p.id
    WHERE m.type = 'entree' AND m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY m.created_at DESC 
    LIMIT 5
")->fetchAll();

// --- Récupération des 5 derniers produits sortants (7 jours) ---
$outgoingProducts = $db->query("
    SELECT p.nom, fi.quantite, f.numero_facture, f.date_facture
    FROM facture_items fi
    JOIN produits p ON fi.produit_id = p.id
    JOIN factures f ON fi.facture_id = f.id
    WHERE f.date_facture >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY f.date_facture DESC 
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du stock - StockMaster</title>
    <!-- Feuilles de style Bootstrap, FontAwesome et personnalisée -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Styles pour les cartes de tableaux et badges de statut */
        .table-card {
            border-left: 4px solid;
            transition: transform 0.3s;
        }
        .table-card:hover {
            transform: translateY(-5px);
        }
        .incoming-card {
            border-left-color: #28a745;
        }
        .outgoing-card {
            border-left-color: #dc3545;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .status-low {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-out {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-ok {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
    <div class="wrapper">
        <?php include_once('includes/sidebar.php'); // Barre latérale ?>

        <div id="content">
            <?php include_once('includes/navbar.php'); // Barre de navigation ?>
            
            <div class="container-fluid px-4">
                <div class="row my-4">
                    <div class="col-12">
                        <h2 class="mb-4"><i class="fas fa-warehouse me-2"></i> <?= t('stock_management') ?></h2>
                        <hr>
                    </div>
                </div>

                <!-- Filtres de recherche et de tri -->
                <div class="card mb-4 shadow">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <label for="search" class="form-label"><?= t('search') ?></label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" placeholder="<?= t('name_or_ref') ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="category" class="form-label"><?= t('category') ?></label>
                                <select class="form-select" id="category" name="category">
                                    <option value=""><?= t('all_categories') ?></option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= (string)$category === (string)$cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="low_stock" name="low_stock" value="1"
                                           <?= $lowStock ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="low_stock"><?= t('low_stock_only') ?></label>
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

                <!-- Tableau principal du stock -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary"><?= t('stock_status') ?></h6>
                    </div>
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
                                        $qty = (int)($item['quantite'] ?? 0);
                                        $seuil = (int)$item['seuil_alerte'];
                                        $isOut = $qty <= 0;
                                        $isLow = $qty <= $seuil;
                                        $orderQty = max($seuil * 2 - $qty, 1);
                                        $statusLabel = $isOut ? t('out_of_stock') : ($isLow ? t('low_stock') : t('available'));
                                        $superUser = can_order_without_whatsapp();

                                        $waMsg = "Hello" . (!empty($item['fournisseur_nom']) ? ' ' . $item['fournisseur_nom'] : '') . ",\n\n"
                                            . "STOCK ALERT — " . COMPANY_NAME . "\n"
                                            . "Product: " . $item['nom'] . "\n"
                                            . "Ref: " . $item['reference'] . "\n"
                                            . "Qty: " . $qty . " / Threshold: " . $seuil . "\n"
                                            . "Order qty: " . $orderQty . "\n";
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
                                            <a href="stock_movement.php?product_id=<?= (int)$item['id'] ?>" 
                                               class="btn btn-sm btn-primary" title="<?= t('manage_stock') ?>">
                                                <i class="fas fa-exchange-alt"></i>
                                            </a>
                                            <?php if ($superUser): ?>
                                            <button type="button" class="btn btn-sm btn-success send-mail-btn"
                                                title="<?= t('order_email') ?>"
                                                data-product-id="<?= (int)$item['id'] ?>"
                                                data-product-name="<?= htmlspecialchars($item['nom'], ENT_QUOTES) ?>"
                                                data-reference="<?= htmlspecialchars($item['reference'], ENT_QUOTES) ?>"
                                                data-quantity="<?= $qty ?>"
                                                data-threshold="<?= $seuil ?>">
                                                <i class="fas fa-truck"></i>
                                            </button>
                                            <?php else: ?>
                                            <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank"
                                               class="btn btn-sm btn-success" title="<?= t('order_whatsapp') ?>">
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
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination des résultats -->
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" 
                                           href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&low_stock=<?= $lowStock ? 1 : 0 ?>">
                                            <?= t('previous') ?>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= ceil($total / $perPage); $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" 
                                           href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&low_stock=<?= $lowStock ? 1 : 0 ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < ceil($total / $perPage)): ?>
                                    <li class="page-item">
                                        <a class="page-link" 
                                           href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&low_stock=<?= $lowStock ? 1 : 0 ?>">
                                            <?= t('next') ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>

                <!-- Tableaux des mouvements entrants et sortants -->
                <div class="row mt-4">
                    <!-- Derniers produits entrants -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 shadow table-card incoming-card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-arrow-down me-2"></i><?= t('incoming_products') ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th><?= t('product') ?></th>
                                                <th><?= t('quantity') ?></th>
                                                <th><?= t('date') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($incomingProducts as $product): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($product['nom']) ?></td>
                                                <td class="text-success">+<?= $product['quantite'] ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($product['date'])) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="movements.php?type=entree" class="btn btn-sm btn-success mt-2">
                                    <i class="fas fa-list me-1"></i> <?= t('see_all_entries') ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Derniers produits sortants -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 shadow table-card outgoing-card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-arrow-up me-2"></i><?= t('outgoing_products') ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th><?= t('product') ?></th>
                                                <th><?= t('quantity') ?></th>
                                                <th><?= t('invoicing') ?></th>
                                                <th><?= t('date') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($outgoingProducts as $product): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($product['nom']) ?></td>
                                                <td class="text-danger">-<?= $product['quantite'] ?></td>
                                                <td><?= htmlspecialchars($product['numero_facture']) ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($product['date_facture'])) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="movements.php?type=sortie" class="btn btn-sm btn-danger mt-2">
                                    <i class="fas fa-list me-1"></i> <?= t('see_all_exits') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast de notification pour les actions AJAX -->
    <div class="position-fixed bottom-0 end-0 p-3 me-3" style="z-index: 1100; max-width: 350px;">
      <div id="mainToast" class="toast align-items-center text-bg-success border-0 w-100" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body" id="mainToastBody">
            <!-- Message -->
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    </div>

    <!-- Scripts JS : jQuery, Bootstrap et JS personnalisé -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    
    <script>
// Fonction pour afficher un toast Bootstrap avec un message
function showToast(message, type = 'success') {
    var toastEl = document.getElementById('mainToast');
    var toastBody = document.getElementById('mainToastBody');
    toastBody.textContent = message;
    toastEl.className = 'toast align-items-center text-bg-' + (type === 'danger' ? 'danger' : 'success') + ' border-0';
    var toast = new bootstrap.Toast(toastEl);
    toast.show();
}
// Script exécuté au chargement de la page
$(document).ready(function() {
    // Gestion du clic sur le bouton d'envoi d'email au fournisseur
    $('.send-mail-btn').click(function() {
        var btn = $(this);
        btn.prop('disabled', true); // Désactive le bouton pendant l'envoi
        var productId = btn.data('product-id');
        var productName = btn.data('product-name');
        var reference = btn.data('reference');
        var quantity = btn.data('quantity');
        var threshold = btn.data('threshold');
        
        // Envoi AJAX vers le script PHP d'envoi de commande
        $.ajax({
            url: 'send_order.php',
            method: 'POST',
            data: {
                product_id: productId,
                product_name: productName,
                reference: reference,
                quantity: quantity,
                threshold: threshold
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(response.message || 'Email envoyé au fournisseur !', 'success');
                    if (response.mailto) {
                        window.location.href = response.mailto;
                    }
                } else {
                    let msg = response.message || 'Erreur lors de l\'envoi de l\'email.';
                    if (response.debug) {
                        msg += '\n--- DEBUG ---\n' + JSON.stringify(response.debug, null, 2);
                    }
                    showToast(msg, 'danger');
                }
            },
            error: function() {
                showToast('Erreur lors de la communication avec le serveur.', 'danger');
            },
            complete: function() {
                btn.prop('disabled', false); // Réactive le bouton
            }
        });
    });
});
</script>
</body>
</html>