<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Charger la configuration
require_once __DIR__ . '/config.php';

// Vérifier que les fonctions essentielles existent
if (!function_exists('t')) {
    die('ERREUR : fonction t() non définie. Vérifiez config.php.');
}
if (!function_exists('role_badge_class')) {
    die('ERREUR : fonction role_badge_class() non définie. Vérifiez config.php.');
}

// Récupérer le portail
$portal = $_GET['portal'] ?? $_POST['portal'] ?? 'admin';
$allowedPortals = ['admin', 'caissier', 'fournisseur', 'superviseur', 'gestionnaire'];
if (!in_array($portal, $allowedPortals, true)) {
    $portal = 'admin';
}

$portalRoles = [
    'admin' => ['admin'],
    'caissier' => ['caissier'],
    'fournisseur' => ['fournisseur'],
    'superviseur' => ['superviseur'],
    'gestionnaire' => ['gestionnaire'],
];

$portalRedirect = [
    'admin' => 'index.php',
    'caissier' => 'create_invoice.php',
    'fournisseur' => 'supplier_portal.php',
    'superviseur' => 'reports.php',
    'gestionnaire' => 'warehouse_dashboard.php',
];

$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $portal = $_POST['portal'] ?? 'admin';
    if (!in_array($portal, $allowedPortals, true)) {
        $portal = 'admin';
    }

    try {
        $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $role = $user['role'] ?: 'utilisateur';
            $okRoles = $portalRoles[$portal] ?? [];
            if ($role !== 'admin' && !in_array($role, $okRoles, true)) {
                $error = t('role_mismatch');
            } else {
                // Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['email'];
                $_SESSION['nom'] = $user['nom'];
                $_SESSION['role'] = $role;
                $_SESSION['telephone'] = $user['telephone'] ?? '';
                $_SESSION['theme'] = $user['theme_pref'] ?? 'light';
                $_SESSION['portal'] = $portal;
                $_SESSION['site_id'] = isset($user['site_id']) ? (int)$user['site_id'] : 0;
                $_SESSION['fournisseur_id'] = isset($user['fournisseur_id']) ? (int)$user['fournisseur_id'] : 0;

                // Redirection selon le rôle
                if ($role === 'fournisseur') {
                    $dest = 'supplier_portal.php';
                } elseif ($role === 'gestionnaire') {
                    $dest = 'warehouse_dashboard.php';
                } elseif ($role === 'caissier') {
                    $dest = 'create_invoice.php';
                } elseif ($role === 'superviseur') {
                    $dest = 'reports.php';
                } elseif ($role === 'admin') {
                    $dest = ($portal === 'admin') ? 'index.php' : ($portalRedirect[$portal] ?? 'index.php');
                } else {
                    $dest = role_home_page();
                }

                header('Location: ' . $dest);
                exit;
            }
        } else {
            $error = t('invalid_credentials');
        }
    } catch (Exception $e) {
        $error = 'Erreur technique : ' . $e->getMessage();
    }
}

$curLang = $_SESSION['lang'] ?? 'fr';
$tabs = [
    'admin' => t('admin'),
    'caissier' => t('cashier'),
    'fournisseur' => t('supplier'),
    'superviseur' => t('supervisor'),
    'gestionnaire' => t('warehouse_manager'),
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($curLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('login') ?> - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .auth-container { max-width: 920px; }
        .auth-form { padding: 1.5rem 2rem 2rem; }
        .portal-tabs .nav-link { font-size: 0.85rem; white-space: nowrap; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Sélecteur de langue -->
        <div class="d-flex justify-content-end gap-2 mb-2">
            <a href="set_language.php?lang=fr&redirect=<?= urlencode('login.php?portal=' . $portal) ?>" class="btn btn-sm <?= $curLang === 'fr' ? 'btn-warning' : 'btn-outline-secondary' ?>">FR</a>
            <a href="set_language.php?lang=en&redirect=<?= urlencode('login.php?portal=' . $portal) ?>" class="btn btn-sm <?= $curLang === 'en' ? 'btn-warning' : 'btn-outline-secondary' ?>">EN</a>
        </div>

        <div class="auth-container mx-auto bg-white rounded shadow overflow-hidden">
            <div class="row g-0">
                <!-- Colonne gauche (info) -->
                <div class="col-md-5 d-none d-md-flex bg-primary text-white p-4 align-items-center">
                    <div>
                        <h2><i class="fas fa-warehouse me-2"></i> StockMaster</h2>
                        <p class="mt-3 mb-2"><?= t('login_as') ?></p>
                        <div class="d-flex flex-wrap gap-1 mt-3">
                            <span class="badge role-badge-admin"><?= t('admin') ?></span>
                            <span class="badge role-badge-cashier"><?= t('cashier') ?></span>
                            <span class="badge role-badge-supplier"><?= t('supplier') ?></span>
                            <span class="badge role-badge-warehouse"><?= t('warehouse_manager') ?></span>
                            <span class="badge role-badge-supervisor"><?= t('supervisor') ?></span>
                        </div>
                        <p class="small mt-4 opacity-75 mb-0"><?= t('accounts_by_admin_only') ?></p>
                    </div>
                </div>

                <!-- Colonne droite (formulaire) -->
                <div class="col-md-7 auth-form">
                    <ul class="nav nav-tabs portal-tabs mb-3 flex-nowrap overflow-auto">
                        <?php foreach ($tabs as $key => $label): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $portal === $key ? 'active' : '' ?>" href="login.php?portal=<?= $key ?>"><?= htmlspecialchars($label) ?></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <h4 class="mb-1"><?= t('login') ?></h4>
                    <p class="text-muted small mb-3">
                        <?= t('login_as') ?>:
                        <span class="badge <?= role_badge_class($portal === 'gestionnaire' ? 'gestionnaire' : ($portal === 'admin' ? 'admin' : $portal)) ?>">
                            <?= htmlspecialchars($tabs[$portal] ?? t('admin')) ?>
                        </span>
                    </p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="portal" value="<?= htmlspecialchars($portal) ?>">
                        <div class="mb-3">
                            <label class="form-label">Email / Login</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="email" required autocomplete="username">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('password') ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="password" required autocomplete="current-password">
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="login" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-1"></i> <?= t('login') ?>
                            </button>
                        </div>
                    </form>
                    <p class="text-muted small mt-3 mb-0"><?= t('accounts_by_admin_only') ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
