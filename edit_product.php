<?php
require_once 'config.php';
require_roles(['admin', 'superviseur', 'gestionnaire']); // Édition : prix réservés admin/superviseur dans le formulaire

$theme = getCurrentTheme();
$isAdmin = is_admin();
$canSetPurchase = $isAdmin;
$canSetThreshold = has_role(['admin', 'superviseur', 'gestionnaire']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: products.php');
    exit;
}

$stmt = $db->prepare("SELECT p.*, COALESCE(s.quantite, 0) AS quantite FROM produits p LEFT JOIN stock s ON s.produit_id = p.id WHERE p.id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    flash_set(t('error'), 'error', 'products.php');
    header('Location: products.php');
    exit;
}

$categories = $db->query("SELECT * FROM categories ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$fournisseurs = $db->query("SELECT id, nom, email, telephone, domaine_activite FROM fournisseurs ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $reference = trim($_POST['reference'] ?? '');
    $categorie_id = (int)($_POST['categorie_id'] ?? 0);
    $fournisseur_id = (int)($_POST['fournisseur_id'] ?? 0);
    $prix_vente = (float)($_POST['prix_vente'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $prix_achat = $canSetPurchase ? (float)($_POST['prix_achat'] ?? 0) : (float)$product['prix_achat'];
    $seuil_alerte = $canSetThreshold ? max(0, (int)($_POST['seuil_alerte'] ?? 5)) : (int)$product['seuil_alerte'];
    $imagePath = $product['image_path'];

    try {
        if ($nom === '' || $reference === '' || $categorie_id <= 0 || $fournisseur_id <= 0) {
            throw new Exception(t('all_fields_required'));
        }
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/products/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $destination = $uploadDir . uniqid('p_', true) . '.' . $extension;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $imagePath = $destination;
            }
        }

        $stmt = $db->prepare("UPDATE produits SET nom=?, reference=?, categorie_id=?, fournisseur_id=?, prix_achat=?, prix_vente=?, seuil_alerte=?, description=?, image_path=? WHERE id=?");
        $stmt->execute([$nom, $reference, $categorie_id, $fournisseur_id, $prix_achat, $prix_vente, $seuil_alerte, $description, $imagePath, $id]);
        flash_set(t('success_edit'), 'success', 'products.php');
        header('Location: products.php');
        exit;
    } catch (Exception $e) {
        flash_set($e->getMessage(), 'error', 'edit_product.php');
        header('Location: edit_product.php?id=' . $id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('edit') ?> <?= t('product') ?> - StockMaster</title>
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
            <div class="row my-4"><div class="col-12"><h2><?= t('edit') ?> — <?= htmlspecialchars($product['nom']) ?></h2><hr></div></div>
            <?php include 'includes/flash.php'; ?>
            <div class="card shadow"><div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><?= t('designation') ?>*</label>
                        <input type="text" class="form-control" name="nom" value="<?= htmlspecialchars($product['nom']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= t('reference') ?>*</label>
                        <input type="text" class="form-control" name="reference" value="<?= htmlspecialchars($product['reference']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= t('category') ?>*</label>
                        <select class="form-select" name="categorie_id" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>" <?= (int)$product['categorie_id'] === (int)$cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= t('supplier') ?>* — <?= t('full_name') ?></label>
                        <select class="form-select" id="fournisseur_id" name="fournisseur_id" required>
                            <option value=""><?= t('choose') ?></option>
                            <?php foreach ($fournisseurs as $f): ?>
                                <option value="<?= (int)$f['id'] ?>"
                                    data-nom="<?= htmlspecialchars($f['nom']) ?>"
                                    data-email="<?= htmlspecialchars($f['email'] ?? '') ?>"
                                    data-telephone="<?= htmlspecialchars($f['telephone'] ?? '') ?>"
                                    <?= (int)$product['fournisseur_id'] === (int)$f['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="row g-2 mt-2">
                            <div class="col-md-6"><input type="text" class="form-control form-control-sm" id="supplier_email" readonly placeholder="Email"></div>
                            <div class="col-md-6"><input type="text" class="form-control form-control-sm" id="supplier_telephone" readonly placeholder="<?= t('phone') ?>"></div>
                        </div>
                    </div>
                    <?php if ($canSetPurchase): ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= t('price_buy') ?>* <span class="badge role-badge-admin"><?= t('admin') ?></span></label>
                        <input type="number" step="0.01" class="form-control" name="prix_achat" value="<?= htmlspecialchars($product['prix_achat']) ?>" min="0" required>
                    </div>
                    <?php else: ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= t('price_buy') ?></label>
                        <input type="text" class="form-control" value="<?= number_format((float)$product['prix_achat'], 0, ',', ' ') ?> FCFA" disabled>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= t('price_sell') ?>*</label>
                        <input type="number" step="0.01" class="form-control" name="prix_vente" value="<?= htmlspecialchars($product['prix_vente']) ?>" min="0" required>
                    </div>
                    <?php if ($canSetThreshold): ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= t('threshold') ?>*</label>
                        <input type="number" class="form-control" name="seuil_alerte" value="<?= (int)$product['seuil_alerte'] ?>" min="0" required>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><?= t('save') ?></button>
                        <a href="products.php" class="btn btn-secondary"><?= t('cancel') ?></a>
                    </div>
                </form>
            </div></div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
lucide.createIcons();
var sel = document.getElementById('fournisseur_id');
function fill() {
    var opt = sel.options[sel.selectedIndex];
    document.getElementById('supplier_email').value = opt && opt.value ? (opt.dataset.email || '') : '';
    document.getElementById('supplier_telephone').value = opt && opt.value ? (opt.dataset.telephone || '') : '';
}
sel.addEventListener('change', fill);
fill();
</script>
</body>
</html>
