<?php
require_once 'config.php';
require_roles(['admin']); // Super admin only creates / edits profiles & passwords

$theme = getCurrentTheme();
migrate_schema($db);

$users = $db->query("SELECT u.id, u.nom, u.email, u.telephone, u.role, u.theme_pref, u.fournisseur_id, u.site_id,
                            f.nom AS fournisseur_nom, s.nom AS site_nom
                     FROM utilisateurs u
                     LEFT JOIN fournisseurs f ON f.id = u.fournisseur_id
                     LEFT JOIN sites s ON s.id = u.site_id
                     ORDER BY u.nom")->fetchAll(PDO::FETCH_ASSOC);
$fournisseursList = $db->query("SELECT id, nom, email, telephone FROM fournisseurs ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$sitesList = $db->query("SELECT id, nom, adresse, ville, responsable, telephone FROM sites ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user']) || isset($_POST['edit_user'])) {
        $isEdit = isset($_POST['edit_user']);
        $id = (int)($_POST['id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'caissier';
        $theme_pref = $_POST['theme_pref'] ?? 'light';
        $fournisseur_id = !empty($_POST['fournisseur_id']) ? (int)$_POST['fournisseur_id'] : null;
        $site_id = !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null;

        // Plus de rôle "utilisateur" à la création / modification
        $allowedRoles = ['admin', 'superviseur', 'caissier', 'gestionnaire', 'fournisseur'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'caissier';
        }
        if ($role !== 'fournisseur') {
            $fournisseur_id = null;
        }
        if ($role !== 'gestionnaire') {
            $site_id = null;
        }

        if ($isEdit && $id <= 0) {
            flash_set(t('error'), 'error', 'users.php');
        } elseif ($nom === '' || $email === '' || (!$isEdit && $password === '')) {
            flash_set(t('all_fields_required'), 'error', 'users.php');
        } elseif (!is_valid_email($email)) {
            flash_set(t('invalid_email'), 'error', 'users.php');
        } elseif ($password !== '' && $password !== $confirm_password) {
            flash_set(t('passwords_mismatch'), 'error', 'users.php');
        } elseif ($role === 'fournisseur' && !$fournisseur_id) {
            flash_set(t('choose') . ' ' . t('supplier'), 'error', 'users.php');
        } elseif ($role === 'gestionnaire' && !$site_id) {
            flash_set(t('choose') . ' ' . t('site'), 'error', 'users.php');
        } else {
            $email = normalize_email($email);

            // Si email déjà pris : pour fournisseur/gestionnaire, proposer un login unique automatiquement
            // seulement si on a collé par erreur l'email métier déjà lié à un autre compte
            if (email_taken_by_user($db, $email, $isEdit ? $id : 0)) {
                flash_set(t('email_in_use'), 'error', 'users.php');
            } elseif ($isEdit) {
                try {
                    if ($password !== '') {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE utilisateurs SET nom=?, email=?, telephone=?, password=?, role=?, theme_pref=?, fournisseur_id=?, site_id=? WHERE id=?");
                        $stmt->execute([$nom, $email, $telephone ?: null, $hashed, $role, $theme_pref, $fournisseur_id, $site_id, $id]);
                    } else {
                        $stmt = $db->prepare("UPDATE utilisateurs SET nom=?, email=?, telephone=?, role=?, theme_pref=?, fournisseur_id=?, site_id=? WHERE id=?");
                        $stmt->execute([$nom, $email, $telephone ?: null, $role, $theme_pref, $fournisseur_id, $site_id, $id]);
                    }
                    flash_set(t('success_edit'), 'success', 'users.php');
                } catch (PDOException $e) {
                    if ((int)$e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate')) {
                        flash_set(t('email_in_use'), 'error', 'users.php');
                    } else {
                        flash_set($e->getMessage(), 'error', 'users.php');
                    }
                }
            } else {
                try {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO utilisateurs (nom, email, telephone, password, role, theme_pref, fournisseur_id, site_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nom, $email, $telephone ?: null, $hashed, $role, $theme_pref, $fournisseur_id, $site_id]);
                    flash_set(t('success_add'), 'success', 'users.php');
                } catch (PDOException $e) {
                    if ((int)$e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate')) {
                        flash_set(t('email_in_use'), 'error', 'users.php');
                    } else {
                        flash_set($e->getMessage(), 'error', 'users.php');
                    }
                }
            }
        }
        header("Location: users.php" . ($isEdit && $id > 0 ? '?edit=' . $id : ''));
        exit;
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id === (int)$_SESSION['user_id']) {
        flash_set(t('error'), 'error', 'users.php');
    } else {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM mouvements WHERE utilisateur_id = ?");
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                flash_set(t('error'), 'error', 'users.php');
            } else {
                $stmt = $db->prepare("DELETE FROM utilisateurs WHERE id = ?");
                $stmt->execute([$id]);
                flash_set(t('success_delete'), 'success', 'users.php');
            }
        } catch (PDOException $e) {
            flash_set($e->getMessage(), 'error', 'users.php');
        }
    }
    header("Location: users.php");
    exit;
}

$userToEdit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $userToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('users') ?> - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.460.0/dist/umd/lucide.min.js" onload="try{lucide.createIcons()}catch(e){}"></script>
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
<div class="wrapper">
    <?php include_once('includes/sidebar.php'); ?>
    <div id="content">
        <?php include_once('includes/navbar.php'); ?>
        <div class="container-fluid px-4">
            <div class="row my-4">
                <div class="col-12">
                    <h2 class="mb-2"><i data-lucide="users" class="me-2"></i> <?= t('users') ?></h2>
                    <p class="text-muted small mb-0"><?= t('accounts_by_admin_only') ?></p>
                    <hr>
                </div>
            </div>

            <?php include 'includes/flash.php'; ?>

            <div class="mb-3 d-flex flex-wrap gap-2">
                <span class="badge role-badge-admin"><?= t('admin') ?></span>
                <span class="badge role-badge-supervisor"><?= t('supervisor') ?></span>
                <span class="badge role-badge-cashier"><?= t('cashier') ?></span>
                <span class="badge role-badge-warehouse"><?= t('warehouse_manager') ?></span>
                <span class="badge role-badge-supplier"><?= t('supplier') ?></span>
                <span class="badge role-badge-user"><?= t('user') ?></span>
            </div>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><?= isset($userToEdit) ? t('edit') : t('add') ?> <?= t('user') ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($userToEdit): ?><input type="hidden" name="id" value="<?= (int)$userToEdit['id'] ?>"><?php endif; ?>
                                <div class="mb-3">
                                    <label class="form-label"><?= t('full_name') ?></label>
                                    <input type="text" class="form-control" name="nom" required value="<?= htmlspecialchars($userToEdit['nom'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email / Login</label>
                                    <input type="email" class="form-control" name="email" id="userEmail" required
                                           pattern="[^\s@]+@[^\s@]+\.[^\s@]+"
                                           title="<?= htmlspecialchars(t('invalid_email')) ?>"
                                           placeholder="ex: prenom.nom@stockmaster.cm"
                                           value="<?= htmlspecialchars($userToEdit['email'] ?? '') ?>">
                                    <small class="text-muted"><?= t('email_supplier_hint') ?></small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= t('phone') ?> (WhatsApp)</label>
                                    <input type="tel" class="form-control" name="telephone" placeholder="2376XXXXXXXX" value="<?= htmlspecialchars($userToEdit['telephone'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= t('password') ?></label>
                                    <input type="password" class="form-control" name="password" <?= $userToEdit ? '' : 'required' ?>>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= t('confirm_password') ?></label>
                                    <input type="password" class="form-control" name="confirm_password" <?= $userToEdit ? '' : 'required' ?>>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= t('role') ?></label>
                                    <select class="form-select" name="role" id="userRole" required>
                                        <?php
                                        $roles = [
                                            'admin' => t('admin'),
                                            'superviseur' => t('supervisor'),
                                            'caissier' => t('cashier'),
                                            'gestionnaire' => t('warehouse_manager'),
                                            'fournisseur' => t('supplier'),
                                        ];
                                        $cur = $userToEdit['role'] ?? 'caissier';
                                        if ($cur === 'utilisateur') {
                                            $cur = 'caissier';
                                        }
                                        foreach ($roles as $val => $lab):
                                        ?>
                                            <option value="<?= $val ?>" <?= $cur === $val ? 'selected' : '' ?>><?= $lab ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3" id="supplierLinkBox" style="<?= ($userToEdit['role'] ?? '') === 'fournisseur' ? '' : 'display:none' ?>">
                                    <label class="form-label"><?= t('supplier') ?> — <?= t('full_name') ?></label>
                                    <select class="form-select" name="fournisseur_id" id="linkFournisseur">
                                        <option value=""><?= t('choose') ?></option>
                                        <?php foreach ($fournisseursList as $f): ?>
                                            <option value="<?= (int)$f['id'] ?>"
                                                data-nom="<?= htmlspecialchars($f['nom']) ?>"
                                                data-email="<?= htmlspecialchars($f['email'] ?? '') ?>"
                                                data-telephone="<?= htmlspecialchars($f['telephone'] ?? '') ?>"
                                                <?= (int)($userToEdit['fournisseur_id'] ?? 0) === (int)$f['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($f['nom']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="row g-2 mt-2">
                                        <div class="col-12">
                                            <input type="text" class="form-control form-control-sm" id="preview_supplier_nom" readonly placeholder="<?= t('full_name') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" class="form-control form-control-sm" id="preview_supplier_email" readonly placeholder="Email">
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" class="form-control form-control-sm" id="preview_supplier_tel" readonly placeholder="<?= t('phone') ?>">
                                        </div>
                                    </div>
                                    <small class="text-muted"><?= t('accounts_by_admin_only') ?></small>
                                </div>
                                <div class="mb-3" id="siteLinkBox" style="<?= ($userToEdit['role'] ?? '') === 'gestionnaire' ? '' : 'display:none' ?>">
                                    <label class="form-label"><?= t('linked_site') ?></label>
                                    <select class="form-select" name="site_id" id="linkSite">
                                        <option value=""><?= t('choose') ?></option>
                                        <?php foreach ($sitesList as $s): ?>
                                            <option value="<?= (int)$s['id'] ?>"
                                                    data-nom="<?= htmlspecialchars($s['responsable'] ?: $s['nom']) ?>"
                                                    data-telephone="<?= htmlspecialchars($s['telephone'] ?? '') ?>"
                                                    data-adresse="<?= htmlspecialchars($s['adresse'] ?? '') ?>"
                                                    data-ville="<?= htmlspecialchars($s['ville'] ?? '') ?>"
                                                    data-site="<?= htmlspecialchars($s['nom'] ?? '') ?>"
                                                    <?= (int)($userToEdit['site_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($s['nom']) ?><?= !empty($s['ville']) ? ' — ' . htmlspecialchars($s['ville']) : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="row g-2 mt-2">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control form-control-sm" id="preview_site_nom" readonly placeholder="Entrepôt">
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" class="form-control form-control-sm" id="preview_site_ville" readonly placeholder="Ville">
                                        </div>
                                        <div class="col-12">
                                            <input type="text" class="form-control form-control-sm" id="preview_site_adresse" readonly placeholder="Adresse">
                                        </div>
                                    </div>
                                    <small class="text-muted">La sélection d’un entrepôt préremplit le nom et le téléphone du gestionnaire s’ils sont vides.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= t('settings') ?></label>
                                    <select class="form-select" name="theme_pref">
                                        <option value="light" <?= ($userToEdit['theme_pref'] ?? '') === 'light' ? 'selected' : '' ?>><?= t('light_mode') ?></option>
                                        <option value="dark" <?= ($userToEdit['theme_pref'] ?? '') === 'dark' ? 'selected' : '' ?>><?= t('dark_mode') ?></option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary" name="<?= $userToEdit ? 'edit_user' : 'add_user' ?>">
                                    <?= $userToEdit ? t('edit') : t('add') ?>
                                </button>
                                <?php if ($userToEdit): ?>
                                    <a href="users.php" class="btn btn-secondary"><?= t('cancel') ?></a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white"><h5 class="mb-0"><?= t('users') ?></h5></div>
                        <div class="card-body table-responsive">
                            <table class="table align-middle">
                                <thead class="<?= $theme == 'dark' ? 'table-dark' : '' ?>">
                                    <tr>
                                        <th><?= t('full_name') ?></th>
                                        <th>Login</th>
                                        <th><?= t('phone') ?></th>
                                        <th><?= t('role') ?></th>
                                        <th><?= t('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($user['nom']) ?>
                                                <?php if (!empty($user['fournisseur_nom'])): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars($user['fournisseur_nom']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($user['site_nom'])): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars($user['site_nom']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <?php if (!empty($user['telephone'])): ?>
                                                    <?= htmlspecialchars($user['telephone']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= role_badge_class($user['role']) ?>"><?= role_label($user['role']) ?></span>
                                            </td>
                                            <td>
                                                <a href="users.php?edit=<?= (int)$user['id'] ?>" class="btn btn-sm btn-warning"><i data-lucide="pencil" style="width:14px;height:14px"></i></a>
                                                <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                                                    <a href="users.php?delete=<?= (int)$user['id'] ?>" class="btn btn-sm btn-danger"
                                                       onclick="return confirm('<?= t('confirm_delete') ?>')"><i data-lucide="trash-2" style="width:14px;height:14px"></i></a>
                                                <?php endif; ?>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
lucide.createIcons();
function syncRoleBoxes() {
    var role = document.getElementById('userRole')?.value;
    document.getElementById('supplierLinkBox').style.display = role === 'fournisseur' ? '' : 'none';
    document.getElementById('siteLinkBox').style.display = role === 'gestionnaire' ? '' : 'none';
}
function fillSupplierPreview() {
    var sel = document.getElementById('linkFournisseur');
    if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    var nom = opt && opt.value ? (opt.dataset.nom || '') : '';
    var email = opt && opt.value ? (opt.dataset.email || '') : '';
    var tel = opt && opt.value ? (opt.dataset.telephone || '') : '';
    document.getElementById('preview_supplier_nom').value = nom;
    document.getElementById('preview_supplier_email').value = email;
    document.getElementById('preview_supplier_tel').value = tel;
    // Ne jamais copier l'email métier fournisseur dans le login (souvent déjà pris)
    var isEdit = !!document.querySelector('input[name="id"]');
    if (isEdit || !opt || !opt.value) return;
    var emailInput = document.querySelector('input[name="email"]');
    var telInput = document.querySelector('input[name="telephone"]');
    var nomInput = document.querySelector('input[name="nom"]');
    if (nomInput && !nomInput.value.trim()) nomInput.value = nom;
    if (telInput && !telInput.value.trim()) telInput.value = tel;
    if (emailInput && !emailInput.value.trim() && nom) {
        var slug = String(nom).toLowerCase().replace(/[^a-z0-9]+/g, '.').replace(/^\.+|\.+$/g, '');
        if (slug) emailInput.value = 'fourn.' + slug + '@stockmaster.cm';
    }
}
function fillSitePreview(fromUser) {
    var sel = document.getElementById('linkSite');
    if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    var siteNom = opt && opt.value ? (opt.dataset.site || '') : '';
    var ville = opt && opt.value ? (opt.dataset.ville || '') : '';
    var adresse = opt && opt.value ? (opt.dataset.adresse || '') : '';
    var nom = opt && opt.value ? (opt.dataset.nom || '') : '';
    var tel = opt && opt.value ? (opt.dataset.telephone || '') : '';
    var pNom = document.getElementById('preview_site_nom');
    var pVille = document.getElementById('preview_site_ville');
    var pAdr = document.getElementById('preview_site_adresse');
    if (pNom) pNom.value = siteNom;
    if (pVille) pVille.value = ville;
    if (pAdr) pAdr.value = adresse;
    if (!opt || !opt.value) return;
    var nomInput = document.querySelector('input[name="nom"]');
    var telInput = document.querySelector('input[name="telephone"]');
    var emailInput = document.querySelector('input[name="email"]');
    if (nomInput && !nomInput.value.trim()) nomInput.value = nom;
    if (telInput && !telInput.value.trim()) telInput.value = tel;
    if (fromUser && emailInput && !emailInput.value.trim() && siteNom) {
        var slug = String(siteNom).toLowerCase().replace(/[^a-z0-9]+/g, '.').replace(/^\.+|\.+$/g, '');
        if (slug) emailInput.value = 'entrepot.' + slug + '@stockmaster.cm';
    }
}
document.getElementById('userRole')?.addEventListener('change', syncRoleBoxes);
document.getElementById('linkFournisseur')?.addEventListener('change', fillSupplierPreview);
document.getElementById('linkSite')?.addEventListener('change', function() { fillSitePreview(true); });
syncRoleBoxes();
fillSupplierPreview();
fillSitePreview(false);
</script>
</body>
</html>
