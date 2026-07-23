<?php
require_once 'config.php';
require_roles(['admin', 'superviseur', 'gestionnaire']);

$theme = getCurrentTheme();

// Vérifier l'ID du produit
if (!isset($_GET['product_id'])) {
    header("Location: stock.php");
    exit();
}

$product_id = (int)$_GET['product_id'];

// Récupérer les informations du produit
$stmt = $db->prepare("SELECT p.*, s.quantite, c.nom as categorie 
                      FROM produits p 
                      JOIN stock s ON p.id = s.produit_id 
                      LEFT JOIN categories c ON p.categorie_id = c.id 
                      WHERE p.id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: stock.php");
    exit();
}

// Traitement du formulaire de mouvement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $quantite = (int)$_POST['quantite'];
    $motif = $_POST['motif'];
    $user_id = $_SESSION['user_id'];
    
    try {
        $db->beginTransaction();
        
        // Enregistrer le mouvement
        $stmt = $db->prepare("INSERT INTO mouvements (produit_id, utilisateur_id, type, quantite, motif) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $user_id, $type, $quantite, $motif]);
        
        // Mettre à jour le stock
        if ($type === 'entree') {
            $newQuantity = $product['quantite'] + $quantite;
        } else {
            $newQuantity = $product['quantite'] - $quantite;
        }
        
        $stmt = $db->prepare("UPDATE stock SET quantite = ? WHERE produit_id = ?");
        $stmt->execute([$newQuantity, $product_id]);
        
        $db->commit();
        
        $_SESSION['success'] = t('success_add');
        header("Location: stock_movement.php?product_id=$product_id");
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        $error = t('error') . ': ' . $e->getMessage();
    }
}

// Récupérer l'historique des mouvements
$movements = $db->prepare("SELECT m.*, u.nom as utilisateur 
                           FROM mouvements m 
                           JOIN utilisateurs u ON m.utilisateur_id = u.id 
                           WHERE m.produit_id = ? 
                           ORDER BY m.created_at DESC 
                           LIMIT 10");
$movements->execute([$product_id]);
$movements = $movements->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('manage_stock') ?> - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div id="content">
            <?php include 'includes/navbar.php'; ?>
            <div class="container-fluid px-4">
                <div class="row my-4">
                    <div class="col-12">
                        <h2 class="mb-4"><i class="fas fa-exchange-alt me-2"></i> <?= t('manage_stock') ?></h2>
                        <hr>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h6 class="m-0 font-weight-bold"><?= t('product') ?></h6>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($product['nom']) ?></h5>
                                <p class="card-text">
                                    <strong><?= t('reference') ?>:</strong> <?= htmlspecialchars($product['reference']) ?><br>
                                    <strong><?= t('category') ?>:</strong> <?= htmlspecialchars($product['categorie']) ?><br>
                                    <strong><?= t('current_stock') ?>:</strong> 
                                    <span class="<?= $product['quantite'] <= $product['seuil_alerte'] ? 'text-danger fw-bold' : '' ?>">
                                        <?= $product['quantite'] ?>
                                    </span><br>
                                    <strong><?= t('threshold') ?>:</strong> <?= $product['seuil_alerte'] ?>
                                </p>
                                <a href="stock.php" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> <?= t('cancel') ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary"><?= t('transactions') ?></h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="type" class="form-label"><?= t('action') ?></label>
                                            <select class="form-select" id="type" name="type" required>
                                                <option value="entree"><?= t('entry') ?></option>
                                                <option value="sortie"><?= t('exit') ?></option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="quantite" class="form-label"><?= t('quantity') ?></label>
                                            <input type="number" class="form-control" id="quantite" name="quantite" min="1" required>
                                        </div>
                                        <div class="col-12">
                                            <label for="motif" class="form-label"><?= t('designation') ?></label>
                                            <textarea class="form-control" id="motif" name="motif" rows="2" required></textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i> <?= t('save') ?>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary"><?= t('activity') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead class="<?= $theme == 'dark' ? 'table-dark' : '' ?>">
                                            <tr>
                                                <th><?= t('date') ?></th>
                                                <th><?= t('action') ?></th>
                                                <th><?= t('quantity') ?></th>
                                                <th><?= t('user') ?></th>
                                                <th><?= t('designation') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($movements as $movement): ?>
                                            <tr>
                                                <td><?= date('d/m/Y H:i', strtotime($movement['created_at'])) ?></td>
                                                <td><?= $movement['type'] === 'entree' ? 
                                                    '<span class="badge bg-success">' . t('entry') . '</span>' : 
                                                    '<span class="badge bg-danger">' . t('exit') . '</span>' ?>
                                                </td>
                                                <td><?= $movement['quantite'] ?></td>
                                                <td><?= htmlspecialchars($movement['utilisateur']) ?></td>
                                                <td><?= htmlspecialchars($movement['motif']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>