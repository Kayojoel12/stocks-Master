<?php
require_once 'config.php';
require_roles(['admin', 'superviseur']);

$theme = getCurrentTheme();
migrate_schema($db);

// Demandes de tranche en attente (admin / superviseur)
$pendingTranches = [];
try {
    $pendingTranches = $db->query("SELECT d.*, f.nom AS fournisseur_nom, c.reference AS cargo_ref
        FROM fournisseur_demandes_paiement d
        JOIN fournisseurs f ON f.id = d.fournisseur_id
        LEFT JOIN fournisseur_cargaisons c ON c.id = d.cargaison_id
        WHERE d.statut IN ('soumise','en_cours')
        ORDER BY d.created_at DESC
        LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pendingTranches = [];
}

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_cargo'])) {
        $fid = (int)$_POST['fournisseur_id'];
        $ref = trim($_POST['reference'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $total = (float)($_POST['montant_total'] ?? 0);
        $date = $_POST['date_cargaison'] ?? date('Y-m-d');
        if ($fid <= 0 || $ref === '' || $total <= 0) {
            $_SESSION['error'] = t('all_fields_required');
        } else {
            $stmt = $db->prepare("INSERT INTO fournisseur_cargaisons (fournisseur_id, reference, description, montant_total, montant_paye, date_cargaison, statut) VALUES (?, ?, ?, ?, 0, ?, 'ouverte')");
            $stmt->execute([$fid, $ref, $desc, $total, $date]);
            $_SESSION['success'] = t('success_add');
        }
        header('Location: supplier_finance.php');
        exit;
    }

    if (isset($_POST['add_payment'])) {
        $cid = (int)$_POST['cargaison_id'];
        $amount = (float)($_POST['montant'] ?? 0);
        $type = $_POST['type_reglement'] ?? 'paiement';
        $notes = trim($_POST['notes'] ?? '');
        $c = $db->prepare("SELECT * FROM fournisseur_cargaisons WHERE id = ?");
        $c->execute([$cid]);
        $cargo = $c->fetch(PDO::FETCH_ASSOC);
        if (!$cargo || $amount <= 0) {
            $_SESSION['error'] = t('error');
        } else {
            $db->beginTransaction();
            try {
                $db->prepare("INSERT INTO fournisseur_reglements (cargaison_id, fournisseur_id, montant, type_reglement, notes, utilisateur_id) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$cid, $cargo['fournisseur_id'], $amount, $type, $notes, $_SESSION['user_id']]);
                $newPaid = (float)$cargo['montant_paye'] + $amount;
                $statut = 'ouverte';
                if ($newPaid >= (float)$cargo['montant_total']) {
                    $statut = 'soldee';
                    $newPaid = (float)$cargo['montant_total'];
                } elseif ($newPaid > 0) {
                    $statut = 'partielle';
                }
                $db->prepare("UPDATE fournisseur_cargaisons SET montant_paye = ?, statut = ? WHERE id = ?")
                    ->execute([$newPaid, $statut, $cid]);
                $db->commit();
                $_SESSION['success'] = t('success_add');
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error'] = $e->getMessage();
            }
        }
        header('Location: supplier_finance.php');
        exit;
    }
}

$search = trim($_GET['search'] ?? '');
$params = [];
$sql = "SELECT c.*, f.nom AS fournisseur_nom,
               (c.montant_total - c.montant_paye) AS dette
        FROM fournisseur_cargaisons c
        JOIN fournisseurs f ON f.id = c.fournisseur_id
        WHERE 1=1";
if ($search !== '') {
    $sql .= " AND (c.reference LIKE ? OR f.nom LIKE ? OR c.description LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY c.date_cargaison DESC, c.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$cargos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fournisseurs = $db->query("SELECT id, nom FROM fournisseurs ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

$totalDebt = (float)$db->query("SELECT COALESCE(SUM(montant_total - montant_paye),0) FROM fournisseur_cargaisons WHERE montant_total > montant_paye")->fetchColumn();
$totalPaid = (float)$db->query("SELECT COALESCE(SUM(montant_paye),0) FROM fournisseur_cargaisons")->fetchColumn();

// Ranking fournisseurs
$ranking = $db->query("
    SELECT f.id, f.nom,
           COUNT(c.id) AS nb_cargos,
           COALESCE(SUM(c.montant_total),0) AS volume,
           COALESCE(SUM(c.montant_paye),0) AS paye,
           COALESCE(SUM(c.montant_total - c.montant_paye),0) AS dette,
           SUM(CASE WHEN c.statut = 'soldee' THEN 1 ELSE 0 END) AS soldes,
           SUM(CASE WHEN c.statut IN ('ouverte','partielle','retard') AND (c.montant_total - c.montant_paye) > 0 THEN 1 ELSE 0 END) AS impayes
    FROM fournisseurs f
    LEFT JOIN fournisseur_cargaisons c ON c.fournisseur_id = f.id
    GROUP BY f.id
    ORDER BY soldes DESC, dette ASC, volume DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('supplier_finance') ?> - StockMaster</title>
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
            <div class="row my-4">
                <div class="col-12"><h2><i data-lucide="wallet" class="me-2"></i><?= t('supplier_finance') ?></h2><hr></div>
            </div>

            <?php if (!empty($_SESSION['success'])): ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-4"><div class="card shadow"><div class="card-body"><div class="text-muted"><?= t('total_debt') ?></div><div class="h3 text-danger"><?= number_format($totalDebt, 0, ',', ' ') ?> FCFA</div></div></div></div>
                <div class="col-md-4"><div class="card shadow"><div class="card-body"><div class="text-muted"><?= t('amount_paid') ?></div><div class="h3 text-success"><?= number_format($totalPaid, 0, ',', ' ') ?> FCFA</div></div></div></div>
                <div class="col-md-4"><div class="card shadow"><div class="card-body"><div class="text-muted"><?= t('cargos') ?></div><div class="h3"><?= count($cargos) ?></div></div></div></div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card shadow h-100">
                        <div class="card-header bg-primary text-white"><?= t('add_cargo') ?></div>
                        <div class="card-body">
                            <form method="POST" class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label"><?= t('supplier') ?></label>
                                    <select name="fournisseur_id" class="form-select" required>
                                        <option value=""><?= t('choose') ?></option>
                                        <?php foreach ($fournisseurs as $f): ?>
                                            <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= t('reference') ?></label>
                                    <input type="text" name="reference" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= t('date') ?></label>
                                    <input type="date" name="date_cargaison" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= t('value') ?></label>
                                    <input type="number" step="0.01" min="1" name="montant_total" class="form-control" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label"><?= t('designation') ?></label>
                                    <textarea name="description" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="col-12"><button class="btn btn-primary" name="add_cargo" value="1"><?= t('save') ?></button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow h-100">
                        <div class="card-header bg-success text-white"><?= t('record_payment') ?></div>
                        <div class="card-body">
                            <form method="POST" class="row g-2">
                                <div class="col-12">
                                    <label class="form-label"><?= t('cargo') ?></label>
                                    <select name="cargaison_id" class="form-select" required>
                                        <option value=""><?= t('choose') ?></option>
                                        <?php foreach ($cargos as $c): if ((float)$c['dette'] <= 0) continue; ?>
                                            <option value="<?= (int)$c['id'] ?>">
                                                <?= htmlspecialchars($c['reference'] . ' — ' . $c['fournisseur_nom'] . ' (due ' . number_format($c['dette'], 0, ',', ' ') . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= t('amount_paid') ?></label>
                                    <input type="number" step="0.01" min="1" name="montant" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= t('status') ?></label>
                                    <select name="type_reglement" class="form-select">
                                        <option value="paiement"><?= t('amount_paid') ?></option>
                                        <option value="avance">Avance</option>
                                        <option value="avoir">Avoir</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Notes"></textarea>
                                </div>
                                <div class="col-12"><button class="btn btn-success" name="add_payment" value="1"><?= t('save') ?></button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?= t('cargos') ?></span>
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($search) ?>" placeholder="<?= t('search') ?>...">
                        <button class="btn btn-sm btn-primary"><?= t('filter') ?></button>
                        <a href="supplier_finance.php" class="btn btn-sm btn-secondary"><?= t('reset') ?></a>
                    </form>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped align-middle">
                        <thead><tr>
                            <th><?= t('reference') ?></th><th><?= t('supplier') ?></th><th><?= t('date') ?></th>
                            <th><?= t('value') ?></th><th><?= t('amount_paid') ?></th><th><?= t('amount_due') ?></th><th><?= t('status') ?></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($cargos as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['reference']) ?></td>
                                    <td><?= htmlspecialchars($c['fournisseur_nom']) ?></td>
                                    <td><?= htmlspecialchars($c['date_cargaison']) ?></td>
                                    <td><?= number_format((float)$c['montant_total'], 0, ',', ' ') ?></td>
                                    <td class="text-success"><?= number_format((float)$c['montant_paye'], 0, ',', ' ') ?></td>
                                    <td class="text-danger fw-bold"><?= number_format((float)$c['dette'], 0, ',', ' ') ?></td>
                                    <td><span class="badge bg-<?= $c['statut'] === 'soldee' ? 'success' : ($c['statut'] === 'partielle' ? 'warning' : 'danger') ?>"><?= htmlspecialchars($c['statut']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($cargos)): ?><tr><td colspan="7" class="text-center text-muted">—</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card shadow">
                        <div class="card-header bg-success text-white"><?= t('best_suppliers') ?> (<?= t('good_merchant') ?>)</div>
                        <div class="card-body table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th><?= t('supplier') ?></th><th><?= t('cargos') ?></th><th><?= t('amount_due') ?></th></tr></thead>
                                <tbody>
                                <?php
                                $best = array_values(array_filter($ranking, static fn($r) => (float)$r['dette'] <= 0 && (int)$r['nb_cargos'] > 0));
                                $best = array_slice($best, 0, 5);
                                foreach ($best as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['nom']) ?></td>
                                        <td><?= (int)$r['nb_cargos'] ?></td>
                                        <td class="text-success">0</td>
                                    </tr>
                                <?php endforeach; if (empty($best)): ?>
                                    <tr><td colspan="3" class="text-muted text-center">—</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow">
                        <div class="card-header bg-danger text-white"><?= t('worst_suppliers') ?> (<?= t('bad_merchant') ?>)</div>
                        <div class="card-body table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th><?= t('supplier') ?></th><th><?= t('amount_due') ?></th><th><?= t('unpaid') ?></th></tr></thead>
                                <tbody>
                                <?php
                                $worst = $ranking;
                                usort($worst, static fn($a, $b) => (float)$b['dette'] <=> (float)$a['dette']);
                                $worst = array_values(array_filter($worst, static fn($r) => (float)$r['dette'] > 0));
                                $worst = array_slice($worst, 0, 5);
                                foreach ($worst as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['nom']) ?></td>
                                        <td class="text-danger fw-bold"><?= number_format((float)$r['dette'], 0, ',', ' ') ?></td>
                                        <td><?= (int)$r['impayes'] ?></td>
                                    </tr>
                                <?php endforeach; if (empty($worst)): ?>
                                    <tr><td colspan="3" class="text-muted text-center">—</td></tr>
                                <?php endif; ?>
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
<script>lucide.createIcons();</script>
</body>
</html>
