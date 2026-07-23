<?php
require_once 'config.php';
require_roles(['admin', 'caissier']); // Création produits : admin / superviseur (pas caissier)

$theme = getCurrentTheme();
$isAdmin = is_admin();
$canSetPurchase = $isAdmin;
$canSetThreshold = has_role(['admin', 'superviseur', 'gestionnaire']);

$categories = $db->query("SELECT * FROM categories ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$fournisseurs = $db->query("SELECT id, nom, email, telephone, domaine_activite FROM fournisseurs ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $reference = trim($_POST['reference'] ?? '');
    $categorie_id = (int)($_POST['categorie_id'] ?? 0);
    $fournisseur_id = (int)($_POST['fournisseur_id'] ?? 0);
    $quantite = (int)($_POST['quantite'] ?? 0);
    $prix_vente = (float)($_POST['prix_vente'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $imagePath = null;

    if ($canSetPurchase) {
        $prix_achat = (float)($_POST['prix_achat'] ?? 0);
    } else {
        $prix_achat = 0;
    }

    if ($canSetThreshold) {
        $seuil_alerte = max(0, (int)($_POST['seuil_alerte'] ?? 5));
    } else {
        $seuil_alerte = 5;
    }

    try {
        if ($nom === '' || $reference === '' || $categorie_id <= 0 || $fournisseur_id <= 0) {
            throw new Exception(t('all_fields_required'));
        }
        if ($canSetPurchase && $prix_achat < 0) {
            throw new Exception(t('error'));
        }

        $db->beginTransaction();

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/products/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowedTypes, true)) {
                throw new Exception('JPEG, PNG, GIF only');
            }
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('p_', true) . '.' . $extension;
            $destination = $uploadDir . $filename;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                throw new Exception('Upload error');
            }
            $imagePath = $destination;
        }

        $stmt = $db->prepare("INSERT INTO produits (nom, reference, categorie_id, fournisseur_id, prix_achat, prix_vente, seuil_alerte, description, image_path)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nom, $reference, $categorie_id, $fournisseur_id, $prix_achat, $prix_vente, $seuil_alerte, $description, $imagePath]);
        $product_id = (int)$db->lastInsertId();

        $db->prepare("INSERT INTO stock (produit_id, quantite) VALUES (?, ?)")->execute([$product_id, $quantite]);
        $db->prepare("INSERT INTO mouvements (produit_id, utilisateur_id, type, quantite, motif) VALUES (?, ?, 'entree', ?, 'Stock initial')")
            ->execute([$product_id, $_SESSION['user_id'], $quantite]);

        $db->commit();
        flash_set(t('success_add'), 'success', 'products.php');
        header('Location: products.php');
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (!empty($destination) && file_exists($destination)) unlink($destination);
        flash_set($e->getMessage(), 'error', 'add_product.php');
        header('Location: add_product.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('add_product') ?> - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.460.0/dist/umd/lucide.min.js" onload="try{lucide.createIcons()}catch(e){}"></script>
    <style>
        .image-preview { max-width: 200px; max-height: 200px; margin-top: 10px; display: none; }
        .supplier-tabs .nav-link { font-size: 0.85rem; }
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
                    <h2 class="mb-4"><i data-lucide="plus-circle" class="me-2"></i> <?= t('add_product') ?></h2>
                    <hr>
                </div>
            </div>
            <?php include 'includes/flash.php'; ?>

            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card shadow">
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="productForm">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Image</label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                        <img id="imagePreview" class="image-preview rounded border" alt="">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"><?= t('designation') ?>*</label>
                                        <input type="text" class="form-control" name="nom" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"><?= t('reference') ?>*</label>
                                        <input type="text" class="form-control" name="reference" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"><?= t('category') ?>*</label>
                                        <select class="form-select" name="categorie_id" required>
                                            <option value=""><?= t('choose') ?></option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"><?= t('quantity') ?>*</label>
                                        <input type="number" class="form-control" name="quantite" min="0" value="0" required>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label"><?= t('supplier') ?>* — <?= t('full_name') ?></label>
                                        <ul class="nav nav-tabs supplier-tabs mb-2" id="supplierDomainTabs" role="tablist">
                                            <li class="nav-item"><button type="button" class="nav-link active" data-domain="">Tous</button></li>
                                            <?php
                                            $domains = array_values(array_unique(array_filter(array_map(static fn($f) => $f['domaine_activite'] ?? '', $fournisseurs))));
                                            sort($domains);
                                            foreach ($domains as $dom):
                                            ?>
                                            <li class="nav-item">
                                                <button type="button" class="nav-link" data-domain="<?= htmlspecialchars($dom) ?>"><?= htmlspecialchars($dom) ?></button>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <select class="form-select" id="fournisseur_id" name="fournisseur_id" required>
                                            <option value=""><?= t('choose') ?> <?= t('full_name') ?></option>
                                            <?php foreach ($fournisseurs as $fourn): ?>
                                                <option value="<?= (int)$fourn['id'] ?>"
                                                        data-domain="<?= htmlspecialchars($fourn['domaine_activite'] ?? '') ?>"
                                                        data-nom="<?= htmlspecialchars($fourn['nom']) ?>"
                                                        data-email="<?= htmlspecialchars($fourn['email'] ?? '') ?>"
                                                        data-telephone="<?= htmlspecialchars($fourn['telephone'] ?? '') ?>">
                                                    <?= htmlspecialchars($fourn['nom']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="row g-2 mt-2" id="supplierContactBox">
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted"><?= t('full_name') ?></label>
                                                <input type="text" class="form-control form-control-sm" id="supplier_nom" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">Email</label>
                                                <input type="text" class="form-control form-control-sm" id="supplier_email" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted"><?= t('phone') ?></label>
                                                <input type="text" class="form-control form-control-sm" id="supplier_telephone" readonly>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($canSetPurchase): ?>
                                    <div class="col-md-6">
                                        <label class="form-label"><?= t('price_buy') ?> (FCFA)* <span class="badge role-badge-admin"><?= t('admin') ?></span></label>
                                        <input type="number" step="0.01" class="form-control" id="prix_achat" name="prix_achat" min="0" required>
                                    </div>
                                    <?php else: ?>
                                        <input type="hidden" name="prix_achat" value="0">
                                        <div class="col-md-6">
                                            <label class="form-label"><?= t('price_buy') ?></label>
                                            <input type="text" class="form-control" value="— (<?= t('admin') ?> only)" disabled>
                                        </div>
                                    <?php endif; ?>

                                    <div class="col-md-6">
                                        <label class="form-label"><?= t('price_sell') ?> (FCFA)*</label>
                                        <input type="number" step="0.01" class="form-control" id="prix_vente" name="prix_vente" min="0" required>
                                    </div>

                                    <?php if ($canSetThreshold): ?>
                                    <div class="col-md-6">
                                        <label class="form-label"><?= t('threshold') ?>*</label>
                                        <input type="number" class="form-control" name="seuil_alerte" min="0" value="5" required>
                                    </div>
                                    <?php else: ?>
                                        <input type="hidden" name="seuil_alerte" value="5">
                                    <?php endif; ?>

                                    <div class="col-12">
                                        <label class="form-label"><?= t('designation') ?></label>
                                        <textarea class="form-control" name="description" rows="3"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
                                        <a href="products.php" class="btn btn-secondary"><?= t('cancel') ?></a>
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
<script>
lucide.createIcons();
(function() {
    var sel = document.getElementById('fournisseur_id');
    function fillContact() {
        var opt = sel.options[sel.selectedIndex];
        document.getElementById('supplier_nom').value = opt && opt.value ? (opt.dataset.nom || '') : '';
        document.getElementById('supplier_email').value = opt && opt.value ? (opt.dataset.email || '') : '';
        document.getElementById('supplier_telephone').value = opt && opt.value ? (opt.dataset.telephone || '') : '';
    }
    sel.addEventListener('change', fillContact);

    document.querySelectorAll('#supplierDomainTabs [data-domain]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#supplierDomainTabs .nav-link').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            var domain = btn.getAttribute('data-domain') || '';
            Array.from(sel.options).forEach(function(opt, i) {
                if (i === 0) { opt.hidden = false; return; }
                var match = !domain || (opt.dataset.domain || '') === domain;
                opt.hidden = !match;
            });
            sel.value = '';
            fillContact();
        });
    });

    document.getElementById('image')?.addEventListener('change', function(e) {
        var preview = document.getElementById('imagePreview');
        var file = e.target.files[0];
        if (!file) { preview.style.display = 'none'; return; }
        var reader = new FileReader();
        reader.onload = function(ev) { preview.src = ev.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(file);
    });
})();
</script>
</body>
</html>
