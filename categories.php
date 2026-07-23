<?php
require_once 'config.php';
require_roles(['admin', 'superviseur']);

$theme = getCurrentTheme();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $nom = $_POST['nom'];
        $description = $_POST['description'];
        
        $stmt = $db->prepare("INSERT INTO categories (nom, description) VALUES (?, ?)");
        $stmt->execute([$nom, $description]);
        
        flash_set("Catégorie ajoutée avec succès", 'success', 'categories.php');
        header("Location: categories.php");
        exit();
    } elseif (isset($_POST['edit_category'])) {
        $id = $_POST['id'];
        $nom = $_POST['nom'];
        $description = $_POST['description'];
        
        $stmt = $db->prepare("UPDATE categories SET nom = ?, description = ? WHERE id = ?");
        $stmt->execute([$nom, $description, $id]);
        
        flash_set("Catégorie modifiée avec succès", 'success', 'categories.php');
        header("Location: categories.php");
        exit();
    } elseif (isset($_GET['delete'])) {
        $id = $_GET['delete'];
        
        // Vérifier si la catégorie est utilisée
        $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE categorie_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            flash_set("Impossible de supprimer cette catégorie car elle est utilisée par des produits", 'error', 'categories.php');
        } else {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            flash_set("Catégorie supprimée avec succès", 'success', 'categories.php');
        }
        
        header("Location: categories.php");
        exit();
    }
}

// Récupérer toutes les catégories
$categories = $db->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catégories - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
    <div class="wrapper">
        <!-- Sidebar (identique à index.php) -->
        <?php
            include_once('includes/sidebar.php');
        ?> 

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navigation (identique à index.php) -->
            <?php
                include_once('includes/navbar.php');
            ?> 
            <!-- Categories Content -->
            <div class="container-fluid px-4">
                <div class="row my-4">
                    <div class="col-12">
                        <h2 class="mb-4"><i class="fas fa-tags me-2"></i> Catégories</h2>
                        <hr>
                    </div>
                </div>

                <?php include 'includes/flash.php'; ?>

                <div class="row">
                    <!-- Formulaire d'ajout/modification -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <?= isset($_GET['edit']) ? 'Modifier' : 'Ajouter' ?> une catégorie
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?php if (isset($_GET['edit'])): 
                                        $editId = $_GET['edit'];
                                        $editCat = $db->query("SELECT * FROM categories WHERE id = $editId")->fetch();
                                    ?>
                                        <input type="hidden" name="id" value="<?= $editCat['id'] ?>">
                                        <div class="mb-3">
                                            <label for="nom" class="form-label">Nom</label>
                                            <input type="text" class="form-control" id="nom" name="nom" 
                                                   value="<?= htmlspecialchars($editCat['nom']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description" 
                                                      rows="3"><?= htmlspecialchars($editCat['description']) ?></textarea>
                                        </div>
                                        <button type="submit" name="edit_category" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Enregistrer
                                        </button>
                                        <a href="categories.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-1"></i> Annuler
                                        </a>
                                    <?php else: ?>
                                        <div class="mb-3">
                                            <label for="nom" class="form-label">Nom</label>
                                            <input type="text" class="form-control" id="nom" name="nom" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                        </div>
                                        <button type="submit" name="add_category" class="btn btn-primary">
                                            <i class="fas fa-plus me-1"></i> Ajouter
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des catégories -->
                    <div class="col-md-8">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Liste des catégories</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead class="<?= $theme == 'dark' ? 'table-dark' : '' ?>">
                                            <tr>
                                                <th>Nom</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $cat): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($cat['nom']) ?></td>
                                                <td><?= htmlspecialchars($cat['description']) ?></td>
                                                <td>
                                                    <a href="categories.php?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="categories.php?delete=<?= $cat['id'] ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
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

    <!-- jQuery, Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/script.js"></script>
</body>
</html>