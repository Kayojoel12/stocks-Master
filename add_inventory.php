<?php
require_once 'config.php';
checkAuth();

$theme = getCurrentTheme();

$sites = $db->query("SELECT * FROM sites ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$products = $db->query("SELECT id, reference, nom FROM produits ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

$success = $error = null;
$selected_site_id = (int)($_POST['site_id'] ?? $_GET['site_id'] ?? ($sites[0]['id'] ?? 0));
$selected_product_id = (int)($_POST['produit_id'] ?? 0);
$current_qty = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = isset($_POST['ajax']);
    $site_id = isset($_POST['site_id']) ? (int)$_POST['site_id'] : 0;
    $produit_id = isset($_POST['produit_id']) ? (int)$_POST['produit_id'] : 0;
    $quantite = isset($_POST['quantite']) ? (int)$_POST['quantite'] : 0;
    $mode = $_POST['mode'] ?? 'set'; // set = remplacer, add = ajouter

    // Compatibilité avec l'ancien formulaire / modale (création produit)
    $new_produit = trim($_POST['new_produit'] ?? '');
    $categorie_id = isset($_POST['categorie_id']) && $_POST['categorie_id'] !== '' ? (int)$_POST['categorie_id'] : null;
    $new_categorie = trim($_POST['new_categorie'] ?? '');

    $selected_site_id = $site_id;
    $selected_product_id = $produit_id;

    try {
        if ($site_id <= 0) {
            throw new Exception("Site invalide.");
        }
        if ($quantite <= 0) {
            throw new Exception("La quantité doit être supérieure à 0.");
        }

        $db->beginTransaction();

        if ($new_categorie !== '') {
            $stmt = $db->prepare("INSERT INTO categories (nom) VALUES (?)");
            $stmt->execute([$new_categorie]);
            $categorie_id = (int)$db->lastInsertId();
        }

        if ($new_produit !== '') {
            $ref = 'PRD-' . strtoupper(substr(uniqid(), -6));
            $stmt = $db->prepare("INSERT INTO produits (reference, nom, categorie_id) VALUES (?, ?, ?)");
            $stmt->execute([$ref, $new_produit, $categorie_id]);
            $produit_id = (int)$db->lastInsertId();
            $selected_product_id = $produit_id;
        }

        if ($produit_id <= 0) {
            throw new Exception("Veuillez choisir un produit.");
        }

        if ($categorie_id) {
            $stmt = $db->prepare("UPDATE produits SET categorie_id = ? WHERE id = ?");
            $stmt->execute([$categorie_id, $produit_id]);
        }

        // Vérifier que le site et le produit existent
        $checkSite = $db->prepare("SELECT id FROM sites WHERE id = ?");
        $checkSite->execute([$site_id]);
        if (!$checkSite->fetchColumn()) {
            throw new Exception("Site introuvable.");
        }

        $checkProd = $db->prepare("SELECT id FROM produits WHERE id = ?");
        $checkProd->execute([$produit_id]);
        if (!$checkProd->fetchColumn()) {
            throw new Exception("Produit introuvable.");
        }

        $stmt = $db->prepare("SELECT id, quantite FROM site_inventaire WHERE site_id = ? AND produit_id = ?");
        $stmt->execute([$site_id, $produit_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = ($mode === 'add') ? ((int)$existing['quantite'] + $quantite) : $quantite;
            $upd = $db->prepare("UPDATE site_inventaire SET quantite = ? WHERE id = ?");
            $upd->execute([$newQty, $existing['id']]);
        } else {
            $ins = $db->prepare("INSERT INTO site_inventaire (site_id, produit_id, quantite) VALUES (?, ?, ?)");
            $ins->execute([$site_id, $produit_id, $quantite]);
            $newQty = $quantite;
        }

        $db->commit();
        $success = "Inventaire mis à jour avec succès (quantité : {$newQty}).";

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => $success, 'quantite' => $newQty]);
            exit;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
}

// Quantité actuelle pour le site/produit sélectionnés
if ($selected_site_id > 0 && $selected_product_id > 0) {
    $qtyStmt = $db->prepare("SELECT quantite FROM site_inventaire WHERE site_id = ? AND produit_id = ?");
    $qtyStmt->execute([$selected_site_id, $selected_product_id]);
    $current_qty = $qtyStmt->fetchColumn();
    if ($current_qty !== false) {
        $current_qty = (int)$current_qty;
    } else {
        $current_qty = null;
    }
}

// Recharger les produits (au cas où un nouveau a été créé)
$products = $db->query("SELECT id, reference, nom FROM produits ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$categories = $db->query("SELECT id, nom FROM categories ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter à l'inventaire - StockMaster</title>
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
                        <h2 class="mb-4"><i class="fas fa-plus-circle me-2"></i> Ajouter un produit à l'inventaire</h2>
                        <hr>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="card shadow">
                            <div class="card-body">
                                <form method="POST" id="inventoryForm">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="site_id" class="form-label">Site</label>
                                            <select class="form-select" id="site_id" name="site_id" required>
                                                <?php if (empty($sites)): ?>
                                                    <option value="">Aucun site</option>
                                                <?php else: ?>
                                                    <?php foreach ($sites as $site): ?>
                                                        <option value="<?= (int)$site['id'] ?>" <?= (int)$site['id'] === (int)$selected_site_id ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($site['nom']) ?> - <?= htmlspecialchars($site['ville']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="produit_id" class="form-label">Produit</label>
                                            <select class="form-select" id="produit_id" name="produit_id" required>
                                                <option value="">-- Choisir --</option>
                                                <?php foreach ($products as $prod): ?>
                                                    <option value="<?= (int)$prod['id'] ?>" <?= (int)$prod['id'] === (int)$selected_product_id ? 'selected' : '' ?>>
                                                        [<?= htmlspecialchars($prod['reference']) ?>] <?= htmlspecialchars($prod['nom']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if ($current_qty !== null): ?>
                                                <small class="text-muted">Quantité actuelle sur ce site : <strong><?= $current_qty ?></strong></small>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="categorie_id" class="form-label">Catégorie (optionnel)</label>
                                            <select class="form-select" id="categorie_id" name="categorie_id">
                                                <option value="">-- Ne pas changer --</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label for="quantite" class="form-label">Quantité</label>
                                            <input type="number" class="form-control" id="quantite" name="quantite" min="1" value="1" required>
                                        </div>

                                        <div class="col-md-3">
                                            <label for="mode" class="form-label">Mode</label>
                                            <select class="form-select" id="mode" name="mode">
                                                <option value="set">Définir la quantité</option>
                                                <option value="add">Ajouter à la quantité</option>
                                            </select>
                                        </div>

                                        <div class="col-12 mt-4">
                                            <button type="submit" class="btn btn-primary me-2">
                                                <i class="fas fa-save me-1"></i> Enregistrer
                                            </button>
                                            <a href="site_inventory.php?site_id=<?= (int)$selected_site_id ?>" class="btn btn-secondary">
                                                <i class="fas fa-arrow-left me-1"></i> Retour à l'inventaire
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
