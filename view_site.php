<?php
require_once 'config.php';
checkAuth();
migrate_schema($db);

$theme = getCurrentTheme();
$site_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($site_id <= 0) {
    $_SESSION['error'] = 'Site invalide.';
    header('Location: sites.php');
    exit;
}

// Création / correction du compte gestionnaire (email + mot de passe)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_manager']) && has_role(['admin', 'superviseur'])) {
    $manager_id = (int)($_POST['manager_id'] ?? 0);
    $manager_nom = trim($_POST['manager_nom'] ?? '');
    $manager_email = trim($_POST['manager_email'] ?? '');
    $manager_tel = trim($_POST['manager_telephone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($manager_nom === '' || $manager_email === '') {
        $_SESSION['error'] = function_exists('t') ? t('all_fields_required') : 'Nom et email / login requis.';
    } elseif (!is_valid_email($manager_email)) {
        $_SESSION['error'] = function_exists('t') ? t('invalid_email') : 'Email invalide.';
    } elseif ($password !== '' && $password !== $confirm_password) {
        $_SESSION['error'] = function_exists('t') ? t('passwords_mismatch') : 'Les mots de passe ne correspondent pas.';
    } elseif ($manager_id <= 0 && $password === '') {
        $_SESSION['error'] = 'Mot de passe requis pour créer le compte.';
    } else {
        try {
            $manager_email = normalize_email($manager_email);
            if (email_taken_by_user($db, $manager_email, $manager_id > 0 ? $manager_id : 0)) {
                // Générer un login unique pour ne pas bloquer
                $manager_email = suggest_unique_user_email($db, $manager_nom ?: ('site' . $site_id), 'stockmaster.cm');
                $_SESSION['success'] = 'Email déjà pris — login généré : ' . $manager_email;
            }
            if ($manager_id > 0) {
                if ($password !== '') {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE utilisateurs SET nom=?, email=?, telephone=?, password=?, role='gestionnaire', site_id=? WHERE id=?");
                    $stmt->execute([$manager_nom, $manager_email, $manager_tel ?: null, $hashed, $site_id, $manager_id]);
                } else {
                    $stmt = $db->prepare("UPDATE utilisateurs SET nom=?, email=?, telephone=?, role='gestionnaire', site_id=? WHERE id=?");
                    $stmt->execute([$manager_nom, $manager_email, $manager_tel ?: null, $site_id, $manager_id]);
                }
                $db->prepare("UPDATE sites SET responsable=?, telephone=? WHERE id=?")->execute([$manager_nom, $manager_tel ?: null, $site_id]);
                if (empty($_SESSION['success'])) {
                    $_SESSION['success'] = 'Compte gestionnaire mis à jour.';
                } else {
                    $_SESSION['success'] .= ' — compte mis à jour.';
                }
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO utilisateurs (nom, email, telephone, password, role, theme_pref, site_id) VALUES (?, ?, ?, ?, 'gestionnaire', 'light', ?)");
                $stmt->execute([$manager_nom, $manager_email, $manager_tel ?: null, $hashed, $site_id]);
                $db->prepare("UPDATE sites SET responsable=?, telephone=? WHERE id=?")->execute([$manager_nom, $manager_tel ?: null, $site_id]);
                if (empty($_SESSION['success'])) {
                    $_SESSION['success'] = 'Compte gestionnaire créé : ' . $manager_email;
                } else {
                    $_SESSION['success'] .= ' — compte créé.';
                }
            }
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate')) {
                $_SESSION['error'] = function_exists('t') ? t('email_in_use') : 'Cet email est déjà utilisé';
            } else {
                $_SESSION['error'] = $e->getMessage();
            }
        }
    }
    header('Location: view_site.php?id=' . $site_id);
    exit;
}

$stmt = $db->prepare("SELECT * FROM sites WHERE id = ?");
$stmt->execute([$site_id]);
$site = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$site) {
    $_SESSION['error'] = 'Site introuvable.';
    header('Location: sites.php');
    exit;
}

$managerStmt = $db->prepare("SELECT id, nom, email, telephone FROM utilisateurs WHERE role = 'gestionnaire' AND site_id = ? ORDER BY id DESC LIMIT 1");
$managerStmt->execute([$site_id]);
$manager = $managerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

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
        WHERE si.site_id = ? AND si.quantite > 0
        ORDER BY si.quantite DESC, p.nom";
$stmt = $db->prepare($sql);
$stmt->execute([$site_id]);
$inventaire = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalQty = 0;
$enAlerte = 0;
foreach ($inventaire as $row) {
    $totalQty += (int)$row['quantite'];
    if ((int)$row['quantite'] <= (int)$row['seuil_alerte']) {
        $enAlerte++;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site['nom']) ?> - StockMaster</title>
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
                            <i class="fas fa-info-circle me-2"></i>
                            Informations du site
                        </h2>
                        <div class="d-flex gap-2">
                            <a href="add_inventory.php?site_id=<?= (int)$site['id'] ?>" class="btn btn-success">
                                <i class="fas fa-plus me-1"></i> Ajouter stock
                            </a>
                            <a href="site_inventory.php?site_id=<?= (int)$site['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-boxes me-1"></i> Inventaire
                            </a>
                            <a href="sites.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Retour
                            </a>
                        </div>
                    </div>
                    <div class="col-12"><hr></div>
                </div>

                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-lg-5">
                        <div class="card shadow h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><?= htmlspecialchars($site['nom']) ?></h5>
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-4">Adresse</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($site['adresse']) ?></dd>
                                    <dt class="col-sm-4">Ville</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($site['ville']) ?></dd>
                                    <dt class="col-sm-4">Pays</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($site['pays']) ?></dd>
                                    <dt class="col-sm-4">Responsable</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($site['responsable']) ?></dd>
                                    <dt class="col-sm-4">Téléphone</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($site['telephone'] ?: '—') ?></dd>
                                    <dt class="col-sm-4">Latitude</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars((string)$site['lat']) ?></dd>
                                    <dt class="col-sm-4">Longitude</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars((string)$site['lng']) ?></dd>
                                    <dt class="col-sm-4">Créé le</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars((string)$site['date_creation']) ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="card shadow text-center h-100">
                                    <div class="card-body">
                                        <div class="text-muted small">Produits</div>
                                        <div class="fs-3 fw-bold"><?= count($inventaire) ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card shadow text-center h-100">
                                    <div class="card-body">
                                        <div class="text-muted small">Quantité totale</div>
                                        <div class="fs-3 fw-bold <?= $totalQty > 0 ? 'text-success' : 'text-danger' ?>"><?= $totalQty ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card shadow text-center h-100">
                                    <div class="card-body">
                                        <div class="text-muted small">Alertes</div>
                                        <div class="fs-3 fw-bold text-warning"><?= $enAlerte ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php if ($totalQty === 0): ?>
                                <div class="col-12">
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Aucun stock sur ce site. Utilisez <strong>Ajouter stock</strong> pour saisir une quantité &gt; 0.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (has_role(['admin', 'superviseur'])): ?>
                            <div class="col-12">
                                <div class="card shadow">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><i class="fas fa-user-tie me-2"></i>Compte gestionnaire d'entrepôt</h6>
                                        <?php if (!$manager): ?>
                                            <span class="badge bg-warning text-dark">Sans compte / sans login</span>
                                        <?php elseif (str_ends_with(strtolower((string)$manager['email']), '@entrepot.local') || $manager['email'] === ''): ?>
                                            <span class="badge bg-warning text-dark">Login à corriger</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Compte OK</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" class="row g-3">
                                            <input type="hidden" name="manager_id" value="<?= (int)($manager['id'] ?? 0) ?>">
                                            <div class="col-md-6">
                                                <label class="form-label">Nom</label>
                                                <input type="text" class="form-control" name="manager_nom" required
                                                       value="<?= htmlspecialchars($manager['nom'] ?? $site['responsable'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Téléphone</label>
                                                <input type="tel" class="form-control" name="manager_telephone"
                                                       value="<?= htmlspecialchars($manager['telephone'] ?? $site['telephone'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">Email / Login <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" name="manager_email" required
                                                       pattern="[^\s@]+@[^\s@]+\.[^\s@]+"
                                                       placeholder="ex: gestionnaire.site@stockmaster.cm"
                                                       value="<?= htmlspecialchars($manager['email'] ?? '') ?>">
                                                <small class="text-muted">Format email standard. Si déjà pris, un login unique sera généré automatiquement.</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Mot de passe<?= $manager ? ' (laisser vide = inchangé)' : '' ?></label>
                                                <input type="password" class="form-control" name="password" <?= $manager ? '' : 'required' ?>>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Confirmer le mot de passe</label>
                                                <input type="password" class="form-control" name="confirm_password" <?= $manager ? '' : 'required' ?>>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" name="save_manager" value="1" class="btn btn-primary">
                                                    <?= $manager ? 'Mettre à jour le gestionnaire' : 'Créer le compte gestionnaire' ?>
                                                </button>
                                                <?php if ($manager): ?>
                                                    <a href="users.php?edit=<?= (int)$manager['id'] ?>" class="btn btn-outline-secondary">Ouvrir dans Utilisateurs</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">Inventaire du site</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle">
                                <thead class="<?= $theme == 'dark' ? 'table-dark' : '' ?>">
                                    <tr>
                                        <th>Référence</th>
                                        <th>Désignation</th>
                                        <th>Catégorie</th>
                                        <th>Quantité</th>
                                        <th>Seuil</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($inventaire)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Aucun produit enregistré pour ce site.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($inventaire as $item): ?>
                                            <?php
                                            $qty = (int)$item['quantite'];
                                            $seuil = (int)$item['seuil_alerte'];
                                            if ($qty <= 0) {
                                                $badge = 'bg-danger';
                                                $status = 'Rupture';
                                            } elseif ($qty <= $seuil) {
                                                $badge = 'bg-warning text-dark';
                                                $status = 'Stock faible';
                                            } else {
                                                $badge = 'bg-success';
                                                $status = 'Disponible';
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
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
