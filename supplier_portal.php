<?php
/**
 * Portail fournisseur
 * - Photos : réservé UNIQUEMENT au rôle fournisseur
 * - Demande de paiement (prochaine tranche) → notifie admin + superviseur
 */
require_once 'config.php';
require_roles(['fournisseur', 'admin', 'superviseur']);

$theme = getCurrentTheme();
migrate_schema($db);

$isSupplierUser = is_fournisseur();
$canModerate = has_role(['admin', 'superviseur']);

$fid = 0;
if ($isSupplierUser) {
    $stmt = $db->prepare("SELECT fournisseur_id FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $fid = (int)$stmt->fetchColumn();
} elseif ($canModerate && isset($_GET['fournisseur_id'])) {
    $fid = (int)$_GET['fournisseur_id'];
}

if ($fid <= 0) {
    flash_set('Aucun fournisseur lié à ce compte.', 'error', role_home_page());
    header('Location: ' . role_home_page());
    exit;
}

$redirectBase = 'supplier_portal.php' . ($isSupplierUser ? '' : '?fournisseur_id=' . $fid);

// ——— Actions POST (écriture = fournisseur uniquement) ———
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Seul le fournisseur peut ajouter / supprimer photos & soumettre demande de paiement
    if (in_array($action, ['upload_photo', 'delete_photo', 'demande_tranche'], true) && !$isSupplierUser) {
        flash_set('Action réservée au compte fournisseur.', 'error', 'supplier_portal.php');
        header('Location: ' . $redirectBase);
        exit;
    }

    if ($action === 'upload_photo' && $isSupplierUser) {
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($titre === '') {
            flash_set('Le titre de la photo est requis.', 'error', 'supplier_portal.php');
        } else {
            [$path, $err] = save_supplier_photo_upload($fid, 'photo');
            if ($err) {
                flash_set($err, 'error', 'supplier_portal.php');
            } else {
                $db->prepare("INSERT INTO fournisseur_photos (fournisseur_id, titre, description, image_path) VALUES (?, ?, ?, ?)")
                    ->execute([$fid, $titre, $description ?: null, $path]);
                flash_set('Photo ajoutée avec succès.', 'success', 'supplier_portal.php');
            }
        }
        header('Location: ' . $redirectBase . '#photos');
        exit;
    }

    if ($action === 'delete_photo' && $isSupplierUser) {
        $pid = (int)($_POST['photo_id'] ?? 0);
        $row = $db->prepare("SELECT * FROM fournisseur_photos WHERE id = ? AND fournisseur_id = ?");
        $row->execute([$pid, $fid]);
        $photo = $row->fetch(PDO::FETCH_ASSOC);
        if ($photo) {
            $full = __DIR__ . '/' . ltrim($photo['image_path'], '/');
            if (is_file($full)) {
                @unlink($full);
            }
            $db->prepare("DELETE FROM fournisseur_photos WHERE id = ? AND fournisseur_id = ?")->execute([$pid, $fid]);
            flash_set('Photo supprimée.', 'success', 'supplier_portal.php');
        }
        header('Location: ' . $redirectBase . '#photos');
        exit;
    }

    if ($action === 'demande_tranche' && $isSupplierUser) {
        $cargaison_id = !empty($_POST['cargaison_id']) ? (int)$_POST['cargaison_id'] : null;
        $montant = (float)($_POST['montant'] ?? 0);
        $tranche = trim($_POST['tranche'] ?? 'Prochaine tranche');
        $message = trim($_POST['message'] ?? '');

        if ($montant <= 0) {
            flash_set('Indiquez un montant de tranche valide.', 'error', 'supplier_portal.php');
            header('Location: ' . $redirectBase . '#tranche');
            exit;
        }
        if ($tranche === '') {
            $tranche = 'Prochaine tranche';
        }

        // Vérifier cargaison liée au fournisseur si fournie
        $cargoRef = null;
        if ($cargaison_id) {
            $c = $db->prepare("SELECT reference, (montant_total - montant_paye) AS dette FROM fournisseur_cargaisons WHERE id = ? AND fournisseur_id = ?");
            $c->execute([$cargaison_id, $fid]);
            $cargo = $c->fetch(PDO::FETCH_ASSOC);
            if (!$cargo) {
                flash_set('Cargaison invalide.', 'error', 'supplier_portal.php');
                header('Location: ' . $redirectBase . '#tranche');
                exit;
            }
            $cargoRef = $cargo['reference'];
        }

        $db->prepare("INSERT INTO fournisseur_demandes_paiement
            (fournisseur_id, cargaison_id, montant, tranche, message, statut, created_by)
            VALUES (?, ?, ?, ?, ?, 'soumise', ?)")
            ->execute([$fid, $cargaison_id, $montant, $tranche, $message ?: null, $_SESSION['user_id']]);

        $supNom = $db->prepare("SELECT nom FROM fournisseurs WHERE id = ?");
        $supNom->execute([$fid]);
        $nomFourn = $supNom->fetchColumn() ?: 'Fournisseur';

        $titreNotif = 'Demande de paiement — ' . $tranche;
        $msgNotif = $nomFourn . ' demande le paiement de la prochaine tranche : '
            . number_format($montant, 0, ',', ' ') . " FCFA"
            . ($cargoRef ? ' (cargaison ' . $cargoRef . ')' : '')
            . ($message ? "\nMessage : " . $message : '');

        try {
            notify_roles($db, ['admin', 'superviseur'], 'demande_tranche', $titreNotif, $msgNotif, 'warning');
        } catch (Exception $e) {}

        flash_set('Demande de paiement envoyée à l\'administrateur et au superviseur.', 'success', 'supplier_portal.php');
        header('Location: ' . $redirectBase . '#tranche');
        exit;
    }

    // Traitement demande (admin / superviseur)
    if ($action === 'traiter_demande' && $canModerate) {
        $did = (int)($_POST['demande_id'] ?? 0);
        $newStatut = $_POST['statut'] ?? '';
        $notes = trim($_POST['notes_traitement'] ?? '');
        if (!in_array($newStatut, ['en_cours', 'payee', 'refusee'], true)) {
            flash_set('Statut invalide.', 'error', 'supplier_portal.php');
        } else {
            $db->prepare("UPDATE fournisseur_demandes_paiement
                SET statut = ?, traite_par = ?, traite_at = NOW(), notes_traitement = ?
                WHERE id = ? AND fournisseur_id = ?")
                ->execute([$newStatut, $_SESSION['user_id'], $notes ?: null, $did, $fid]);
            flash_set('Demande mise à jour.', 'success', 'supplier_portal.php');
        }
        header('Location: ' . $redirectBase . '#tranche');
        exit;
    }
}

$supplier = $db->prepare("SELECT * FROM fournisseurs WHERE id = ?");
$supplier->execute([$fid]);
$supplier = $supplier->fetch(PDO::FETCH_ASSOC);

$cargos = $db->prepare("SELECT *, (montant_total - montant_paye) AS dette FROM fournisseur_cargaisons WHERE fournisseur_id = ? ORDER BY date_cargaison DESC");
$cargos->execute([$fid]);
$cargos = $cargos->fetchAll(PDO::FETCH_ASSOC);

$payments = $db->prepare("SELECT r.*, c.reference FROM fournisseur_reglements r JOIN fournisseur_cargaisons c ON c.id = r.cargaison_id WHERE r.fournisseur_id = ? ORDER BY r.date_reglement DESC LIMIT 30");
$payments->execute([$fid]);
$payments = $payments->fetchAll(PDO::FETCH_ASSOC);

$photos = $db->prepare("SELECT * FROM fournisseur_photos WHERE fournisseur_id = ? ORDER BY created_at DESC");
$photos->execute([$fid]);
$photos = $photos->fetchAll(PDO::FETCH_ASSOC);

$demandesPaiement = $db->prepare("SELECT d.*, c.reference AS cargo_ref, u.nom AS traite_par_nom
    FROM fournisseur_demandes_paiement d
    LEFT JOIN fournisseur_cargaisons c ON c.id = d.cargaison_id
    LEFT JOIN utilisateurs u ON u.id = d.traite_par
    WHERE d.fournisseur_id = ?
    ORDER BY d.created_at DESC");
$demandesPaiement->execute([$fid]);
$demandesPaiement = $demandesPaiement->fetchAll(PDO::FETCH_ASSOC);

$unpaidCargos = array_values(array_filter($cargos, static fn($c) => (float)$c['dette'] > 0));
$totalDue = array_sum(array_map(static fn($c) => max(0, (float)$c['dette']), $cargos));
$totalPaid = array_sum(array_map(static fn($c) => (float)$c['montant_paye'], $cargos));

$statutPayBadge = [
    'soumise' => 'primary',
    'en_cours' => 'warning',
    'payee' => 'success',
    'refusee' => 'danger',
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('supplier_portal') ?> - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .photo-card img { height: 160px; object-fit: cover; width: 100%; }
        .photo-card { height: 100%; }
    </style>
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div id="content">
        <?php include 'includes/navbar.php'; ?>
        <div class="container-fluid px-4">
            <div class="row my-4">
                <div class="col-12">
                    <h2><i class="fas fa-store me-2"></i><?= t('supplier_portal') ?> — <?= htmlspecialchars($supplier['nom'] ?? '') ?></h2>
                    <?php if (!$isSupplierUser): ?>
                        <div class="alert alert-info py-2">Consultation admin / superviseur — l’ajout de photos et les demandes de tranche sont réservés au fournisseur.</div>
                    <?php endif; ?>
                    <hr>
                </div>
            </div>

            <?php include 'includes/flash.php'; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-4"><div class="card shadow border-0"><div class="card-body"><div class="text-muted small"><?= t('amount_due') ?></div><div class="h3 text-danger mb-0"><?= number_format($totalDue, 0, ',', ' ') ?> FCFA</div></div></div></div>
                <div class="col-md-4"><div class="card shadow border-0"><div class="card-body"><div class="text-muted small"><?= t('amount_paid') ?></div><div class="h3 text-success mb-0"><?= number_format($totalPaid, 0, ',', ' ') ?> FCFA</div></div></div></div>
                <div class="col-md-4"><div class="card shadow border-0"><div class="card-body"><div class="text-muted small">Photos</div><div class="h3 mb-0"><?= count($photos) ?></div></div></div></div>
            </div>

            <ul class="nav nav-pills mb-4 gap-1 flex-wrap" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#photos"><i class="fas fa-camera me-1"></i> Mes photos</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tranche"><i class="fas fa-money-check-alt me-1"></i> Demande de paiement</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#cargaisons"><i class="fas fa-truck me-1"></i> Cargaisons</a></li>
            </ul>

            <div class="tab-content pb-5">
                <!-- ========== PHOTOS (fournisseur only upload) ========== -->
                <div class="tab-pane fade show active" id="photos">
                    <div class="row g-4">
                        <?php if ($isSupplierUser): ?>
                        <div class="col-lg-4">
                            <div class="card shadow border-primary">
                                <div class="card-header bg-primary text-white">
                                    <i class="fas fa-upload me-1"></i> Ajouter une photo
                                    <span class="badge bg-light text-dark ms-1">Fournisseur uniquement</span>
                                </div>
                                <div class="card-body">
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="upload_photo">
                                        <div class="mb-3">
                                            <label class="form-label">Titre *</label>
                                            <input type="text" name="titre" class="form-control" required placeholder="Ex: Lot ordinateurs HP">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" class="form-control" rows="2" placeholder="Détails de l'équipement…"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Photo (JPG/PNG, max 5 Mo) *</label>
                                            <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-camera me-1"></i> Enregistrer la photo
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="<?= $isSupplierUser ? 'col-lg-8' : 'col-12' ?>">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Galerie</h5>
                                <span class="badge bg-secondary"><?= count($photos) ?> photo(s)</span>
                            </div>
                            <?php if (empty($photos)): ?>
                                <div class="alert alert-secondary">Aucune photo pour le moment.<?= $isSupplierUser ? ' Ajoutez vos équipements ici.' : '' ?></div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($photos as $ph): ?>
                                        <div class="col-sm-6 col-md-4">
                                            <div class="card photo-card shadow-sm">
                                                <img src="<?= htmlspecialchars(product_image_url($ph['image_path'])) ?>"
                                                     alt="<?= htmlspecialchars($ph['titre']) ?>" class="card-img-top">
                                                <div class="card-body">
                                                    <h6 class="mb-1"><?= htmlspecialchars($ph['titre']) ?></h6>
                                                    <?php if ($ph['description']): ?>
                                                        <p class="small text-muted mb-2"><?= htmlspecialchars($ph['description']) ?></p>
                                                    <?php endif; ?>
                                                    <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($ph['created_at'])) ?></div>
                                                    <?php if ($isSupplierUser): ?>
                                                        <form method="POST" class="mt-2" onsubmit="return confirm('Supprimer cette photo ?');">
                                                            <input type="hidden" name="action" value="delete_photo">
                                                            <input type="hidden" name="photo_id" value="<?= (int)$ph['id'] ?>">
                                                            <button class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-trash me-1"></i> Supprimer</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ========== DEMANDE PAIEMENT PROCHAINE TRANCHE ========== -->
                <div class="tab-pane fade" id="tranche">
                    <div class="row g-4">
                        <?php if ($isSupplierUser): ?>
                        <div class="col-lg-5">
                            <div class="card shadow border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <i class="fas fa-file-invoice-dollar me-1"></i> Demander le paiement de la prochaine tranche
                                </div>
                                <div class="card-body">
                                    <p class="small text-muted">La demande est envoyée à l’<strong>administrateur</strong> et au <strong>superviseur</strong>.</p>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="demande_tranche">
                                        <div class="mb-3">
                                            <label class="form-label">Cargaison concernée</label>
                                            <select name="cargaison_id" class="form-select">
                                                <option value="">— Optionnel —</option>
                                                <?php foreach ($unpaidCargos as $c): ?>
                                                    <option value="<?= (int)$c['id'] ?>">
                                                        <?= htmlspecialchars($c['reference']) ?> — dû <?= number_format((float)$c['dette'], 0, ',', ' ') ?> F
                                                    </option>
                                                <?php endforeach; ?>
                                                <?php foreach ($cargos as $c): ?>
                                                    <?php if ((float)$c['dette'] <= 0) continue; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Libellé de la tranche *</label>
                                            <input type="text" name="tranche" class="form-control" required
                                                   value="Prochaine tranche" placeholder="Ex: 2e tranche — 40%">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Montant demandé (FCFA) *</label>
                                            <input type="number" name="montant" class="form-control" min="1" step="1" required
                                                   placeholder="Ex: 500000">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Message / justification</label>
                                            <textarea name="message" class="form-control" rows="3"
                                                      placeholder="Précisez les livraisons effectuées, échéance souhaitée…"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-warning w-100">
                                            <i class="fas fa-paper-plane me-1"></i> Soumettre à l’admin &amp; superviseur
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="<?= $isSupplierUser ? 'col-lg-7' : 'col-12' ?>">
                            <div class="card shadow">
                                <div class="card-header">Demandes de paiement</div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Tranche</th>
                                                <th>Montant</th>
                                                <th>Cargaison</th>
                                                <th>Statut</th>
                                                <?php if ($canModerate): ?><th>Traiter</th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($demandesPaiement as $d): ?>
                                            <tr>
                                                <td class="small"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($d['tranche']) ?></strong>
                                                    <?php if ($d['message']): ?>
                                                        <div class="small text-muted"><?= htmlspecialchars($d['message']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fw-bold"><?= number_format((float)$d['montant'], 0, ',', ' ') ?> F</td>
                                                <td><?= htmlspecialchars($d['cargo_ref'] ?? '—') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $statutPayBadge[$d['statut']] ?? 'secondary' ?>"><?= htmlspecialchars($d['statut']) ?></span>
                                                    <?php if ($d['traite_par_nom']): ?>
                                                        <div class="small text-muted"><?= htmlspecialchars($d['traite_par_nom']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($canModerate): ?>
                                                <td>
                                                    <?php if ($d['statut'] === 'soumise' || $d['statut'] === 'en_cours'): ?>
                                                    <form method="POST" class="d-flex flex-wrap gap-1">
                                                        <input type="hidden" name="action" value="traiter_demande">
                                                        <input type="hidden" name="demande_id" value="<?= (int)$d['id'] ?>">
                                                        <select name="statut" class="form-select form-select-sm" style="width:auto">
                                                            <option value="en_cours">En cours</option>
                                                            <option value="payee">Payée</option>
                                                            <option value="refusee">Refusée</option>
                                                        </select>
                                                        <button class="btn btn-sm btn-primary">OK</button>
                                                    </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($demandesPaiement)): ?>
                                            <tr><td colspan="<?= $canModerate ? 6 : 5 ?>" class="text-center text-muted py-4">Aucune demande de paiement</td></tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========== CARGAISONS ========== -->
                <div class="tab-pane fade" id="cargaisons">
                    <div class="card shadow mb-4">
                        <div class="card-header"><?= t('cargos') ?></div>
                        <div class="card-body table-responsive">
                            <table class="table table-striped align-middle">
                                <thead><tr><th><?= t('reference') ?></th><th><?= t('date') ?></th><th><?= t('value') ?></th><th><?= t('amount_paid') ?></th><th><?= t('amount_due') ?></th><th><?= t('status') ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($cargos as $c): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($c['reference']) ?></td>
                                        <td><?= htmlspecialchars($c['date_cargaison']) ?></td>
                                        <td><?= number_format((float)$c['montant_total'], 0, ',', ' ') ?></td>
                                        <td><?= number_format((float)$c['montant_paye'], 0, ',', ' ') ?></td>
                                        <td class="<?= (float)$c['dette'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>"><?= number_format((float)$c['dette'], 0, ',', ' ') ?></td>
                                        <td><?= htmlspecialchars($c['statut']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($cargos)): ?><tr><td colspan="6" class="text-center text-muted">—</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card shadow">
                        <div class="card-header"><?= t('amount_received') ?></div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-sm mb-0">
                                <thead><tr><th><?= t('date') ?></th><th><?= t('cargo') ?></th><th><?= t('amount_paid') ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['date_reglement']) ?></td>
                                        <td><?= htmlspecialchars($p['reference']) ?></td>
                                        <td class="text-success"><?= number_format((float)$p['montant'], 0, ',', ' ') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($payments)): ?><tr><td colspan="3" class="text-center text-muted">—</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var hash = (location.hash || '').replace('#', '');
    if (hash) {
        var link = document.querySelector('.nav-pills a[href="#' + hash + '"]');
        if (link && window.bootstrap) new bootstrap.Tab(link).show();
    }
})();
</script>
</body>
</html>
