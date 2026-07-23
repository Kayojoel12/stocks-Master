<?php
require_once 'config.php';
require_roles(['admin', 'superviseur']);

$theme = getCurrentTheme();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Recherche
$search = isset($_GET['search']) ? $_GET['search'] : '';

$query = "SELECT * FROM fournisseurs WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (nom LIKE ? OR email LIKE ? OR telephone LIKE ? OR domaine_activite LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Nombre total
$stmt = $db->prepare(str_replace('*', 'COUNT(*)', $query));
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Fournisseurs pour la page
$query .= " ORDER BY nom LIMIT $offset, $perPage";
$stmt = $db->prepare($query);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_supplier'])) {
        $nom = trim($_POST['nom'] ?? '');
        $email = normalize_email($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $domaine_activite = trim($_POST['domaine_activite'] ?? '');

        if ($nom === '' || $email === '') {
            flash_set(t('all_fields_required'), 'error', 'suppliers.php');
        } elseif (!is_valid_email($email)) {
            flash_set(t('invalid_email'), 'error', 'suppliers.php');
        } else {
            $dup = $db->prepare("SELECT id FROM fournisseurs WHERE LOWER(TRIM(email)) = ? LIMIT 1");
            $dup->execute([$email]);
            if ($dup->fetch()) {
                flash_set(t('email_in_use'), 'error', 'suppliers.php');
            } else {
                $stmt = $db->prepare("INSERT INTO fournisseurs (nom, email, telephone, adresse, domaine_activite) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$nom, $email, $telephone, $adresse, $domaine_activite])) {
                    flash_set(t('success_add'), 'success', 'suppliers.php');
                } else {
                    flash_set(t('error'), 'error', 'suppliers.php');
                }
            }
        }

        header("Location: suppliers.php");
        exit();
    } elseif (isset($_POST['edit_supplier'])) {
        $id = (int)($_POST['id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $email = normalize_email($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $domaine_activite = trim($_POST['domaine_activite'] ?? '');

        if ($id <= 0 || $nom === '' || $email === '') {
            flash_set(t('all_fields_required'), 'error', 'suppliers.php');
        } elseif (!is_valid_email($email)) {
            flash_set(t('invalid_email'), 'error', 'suppliers.php');
        } else {
            $dup = $db->prepare("SELECT id FROM fournisseurs WHERE LOWER(TRIM(email)) = ? AND id <> ? LIMIT 1");
            $dup->execute([$email, $id]);
            if ($dup->fetch()) {
                flash_set(t('email_in_use'), 'error', 'suppliers.php');
            } else {
                $stmt = $db->prepare("UPDATE fournisseurs SET nom = ?, email = ?, telephone = ?, adresse = ?, domaine_activite = ? WHERE id = ?");
                if ($stmt->execute([$nom, $email, $telephone, $adresse, $domaine_activite, $id])) {
                    flash_set(t('success_edit'), 'success', 'suppliers.php');
                } else {
                    flash_set(t('error'), 'error', 'suppliers.php');
                }
            }
        }

        header("Location: suppliers.php");
        exit();
    }
} elseif (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Vérifier si le fournisseur est utilisé
    $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE fournisseur_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Impossible de supprimer ce fournisseur car il est associé à des produits'
        ];
    } else {
        $stmt = $db->prepare("DELETE FROM fournisseurs WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Fournisseur supprimé avec succès'
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Erreur lors de la suppression du fournisseur'
            ];
        }
    }
    
    header("Location: suppliers.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('suppliers') ?> - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.460.0/dist/umd/lucide.min.js" onload="try{lucide.createIcons()}catch(e){}"></script>
    <style>
        /* Animations personnalisées */
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .btn-hover {
            transition: all 0.2s ease;
        }
        .btn-hover:hover {
            transform: scale(1.05);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Style pour les lignes du tableau */
        .table-row {
            transition: all 0.3s ease;
        }
        .table-row:hover {
            background-color: rgba(0,0,0,0.05);
        }
        
        /* Styles pour les badges de domaine */
        .badge {
            padding: 0.5em 0.75em;
            font-size: 0.85em;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .bg-success { background-color: #28a745 !important; }
        .bg-info { background-color: #17a2b8 !important; }
        .bg-warning { background-color: #ffc107 !important; color: #212529; }
        .bg-danger { background-color: #dc3545 !important; }
        .bg-secondary { background-color: #6c757d !important; }
    </style>
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
    <div class="wrapper">
        <?php include_once('includes/sidebar.php'); ?>

        <div id="content">
            <?php include_once('includes/navbar.php'); ?>

            <!-- Message d'alerte fixe en bas de la navbar -->
            <?php include 'includes/flash.php'; ?>
            <div class="container-fluid px-4">
                <!-- En-tête avec animation -->
                <div class="row my-4 animate__animated animate__fadeIn">
                    <div class="col-12">
                        <h2 class="mb-4"><i class="fas fa-truck me-2"></i> <?= t('suppliers') ?></h2>
                        <hr>
                    </div>
                </div>

                <div class="row">
                    <!-- Formulaire avec animation -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow card-hover animate__animated animate__fadeInLeft">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-plus-circle me-2"></i>
                                    <?= isset($_GET['edit']) ? t('edit') : t('add') ?> <?= t('supplier') ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $supplier = null;
                                if (isset($_GET['edit'])) {
                                    $id = $_GET['edit'];
                                    $stmt = $db->prepare("SELECT * FROM fournisseurs WHERE id = ?");
                                    $stmt->execute([$id]);
                                    $supplier = $stmt->fetch();
                                }
                                ?>
                                <form method="POST" id="supplierForm">
                                    <?php if (isset($_GET['edit'])): ?>
                                        <input type="hidden" name="id" value="<?= $supplier['id'] ?>">
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label for="nom" class="form-label">Nom</label>
                                        <input type="text" class="form-control" id="nom" name="nom" 
                                            value="<?= $supplier['nom'] ?? '' ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="domaine_activite" class="form-label">Domaine d'activité</label>
                                        <?php 
                                        ?>
                                        <select class="form-select" id="domaine_activite" name="domaine_activite" required>
                                            <option value="Alimentaire" <?= isset($supplier) && $supplier['domaine_activite'] == 'Alimentaire' ? 'selected' : '' ?>>Alimentaire</option>
                                            <option value="Textile" <?= isset($supplier) && $supplier['domaine_activite'] == 'Textile' ? 'selected' : '' ?>>Textile</option>
                                            <option value="Electronique" <?= isset($supplier) && $supplier['domaine_activite'] == 'Electronique' ? 'selected' : '' ?>>Electronique</option>
                                            <option value="BTP" <?= isset($supplier) && $supplier['domaine_activite'] == 'BTP' ? 'selected' : '' ?>>BTP</option>
                                            <option value="Service" <?= isset($supplier) && $supplier['domaine_activite'] == 'Service' ? 'selected' : '' ?>>Service</option>
                                            <option value="Autre" <?= !isset($supplier) || $supplier['domaine_activite'] == 'Autre' ? 'selected' : '' ?>>Autre</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                            value="<?= $supplier['email'] ?? '' ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="telephone" class="form-label"><?= t('phone') ?></label>
                                        <input type="text" class="form-control" id="telephone" name="telephone" 
                                            value="<?= $supplier['telephone'] ?? '' ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="adresse" class="form-label"><?= t('location') ?></label>
                                        <textarea class="form-control" id="adresse" name="adresse" rows="2"><?= $supplier['adresse'] ?? '' ?></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-hover" name="<?= isset($_GET['edit']) ? 'edit_supplier' : 'add_supplier' ?>">
                                        <i class="fas fa-save me-1"></i>
                                        <?= isset($_GET['edit']) ? t('edit') : t('add') ?>
                                    </button>
                                    <?php if (isset($_GET['edit'])): ?>
                                        <a href="suppliers.php" class="btn btn-secondary btn-hover">
                                            <i class="fas fa-times me-1"></i> <?= t('cancel') ?>
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des fournisseurs -->
                    <div class="col-md-8">
                        <div class="card shadow animate__animated animate__fadeInRight">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-list me-2"></i>
                                    <?= t('suppliers') ?>
                                </h5>
                                <button id="refreshBtn" class="btn btn-sm btn-light">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <!-- Barre de recherche -->
                                <form class="mb-4" id="searchForm">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="<?= t('search') ?>..." 
                                            name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </form>

                                <!-- Tableau -->
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead class="<?= $theme == 'dark' ? 'table-dark' : '' ?>">
                                            <tr>
                                                <th>Nom</th>
                                                <th>Domaine</th>
                                                <th>Email</th>
                                                <th>Téléphone</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="suppliersTableBody">
                                            <?php if (empty($suppliers)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">Aucun fournisseur trouvé</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($suppliers as $supplier): ?>
                                                    <tr class="table-row fade-in">
                                                        <td><?= htmlspecialchars($supplier['nom']) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= 
                                                                $supplier['domaine_activite'] == 'Alimentaire' ? 'success' : 
                                                                ($supplier['domaine_activite'] == 'Textile' ? 'info' : 
                                                                ($supplier['domaine_activite'] == 'Electronique' ? 'warning' : 
                                                                ($supplier['domaine_activite'] == 'BTP' ? 'danger' : 'secondary'))) ?>">
                                                                <?= htmlspecialchars($supplier['domaine_activite']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($supplier['email']) ?></td>
                                                        <td><?= htmlspecialchars($supplier['telephone']) ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                            <a href="suppliers.php?edit=<?= (int)$supplier['id'] ?>"
                                                               class="btn btn-warning" title="<?= t('edit') ?>" data-full-reload="1">
                                                                <i data-lucide="pencil" style="width:14px;height:14px"></i>
                                                                <span class="visually-hidden"><?= t('edit') ?></span>
                                                            </a>
                                                            <?php if (can_delete()): ?>
                                                            <button type="button" class="btn btn-danger delete-btn"
                                                                    title="<?= t('delete') ?>" data-id="<?= (int)$supplier['id'] ?>">
                                                                <i data-lucide="trash-2" style="width:14px;height:14px"></i>
                                                                <span class="visually-hidden"><?= t('delete') ?></span>
                                                            </button>
                                                            <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total > $perPage): ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center" id="pagination">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">
                                                        <i class="fas fa-angle-left"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = 1; $i <= ceil($total/$perPage); $i++): ?>
                                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < ceil($total/$perPage)): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">
                                                        <i class="fas fa-angle-right"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content <?= $theme == 'dark' ? 'bg-dark text-light' : '' ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmation</h5>
                    <button type="button" class="btn-close <?= $theme == 'dark' ? 'btn-close-white' : '' ?>" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Êtes-vous sûr de vouloir supprimer ce fournisseur ?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete"><?= t('delete') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery, Bootstrap JS, Toastr pour les notifications -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">

    <script>
    $(document).ready(function() {
        // Configuration de Toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "5000"
        };

        // Variable pour stocker l'ID à supprimer
        let supplierIdToDelete = null;

        // Gestion du clic sur le bouton supprimer
        $(document).on('click', '.delete-btn', function() {
            supplierIdToDelete = $(this).data('id');
            $('#confirmModal').modal('show');
        });

        // Confirmation de suppression
        $('#confirmDelete').click(function() {
            if (supplierIdToDelete) {
                $.ajax({
                    url: 'delete_supplier.php',
                    method: 'POST',
                    data: { 
                        id: supplierIdToDelete 
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            // Recharger la page pour voir les changements
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        } else {
                            toastr.error(response.message);
                        }
                        $('#confirmModal').modal('hide');
                    },
                    error: function(xhr, status, error) {
                        toastr.error("Erreur lors de la suppression: " + error);
                        console.error(xhr.responseText);
                        $('#confirmModal').modal('hide');
                    }
                });
            }
        });

        // Rafraîchissement de la liste
        $('#refreshBtn').click(function() {
            $(this).addClass('fa-spin');
            setTimeout(function() {
                window.location.reload();
            }, 500);
        });

        if (window.lucide) lucide.createIcons();
    });
    </script>
</body>
</html>