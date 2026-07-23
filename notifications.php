<?php
require_once 'config.php';
require_roles(['admin', 'superviseur']);

$theme = getCurrentTheme();
ensure_notifications_table($db);
sync_stock_alert_notifications($db);

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read' && isset($_POST['id'])) {
        $stmt = $db->prepare("UPDATE notifications SET lu = 1 WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $_SESSION['success'] = 'Notification marquée comme lue.';
        header('Location: notifications.php');
        exit;
    }

    if ($action === 'mark_all_read') {
        $db->exec("UPDATE notifications SET lu = 1 WHERE lu = 0");
        $_SESSION['success'] = 'Toutes les notifications ont été marquées comme lues.';
        header('Location: notifications.php');
        exit;
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $_SESSION['success'] = 'Notification supprimée.';
        header('Location: notifications.php');
        exit;
    }

    if ($action === 'resync') {
        sync_stock_alert_notifications($db);
        $_SESSION['success'] = 'Alertes synchronisées.';
        header('Location: notifications.php');
        exit;
    }
}

$filter = $_GET['filter'] ?? 'unread';
$sql = "SELECT n.*, p.reference, p.nom AS produit_nom, p.seuil_alerte,
               COALESCE(s.quantite, 0) AS stock_qty,
               f.nom AS fournisseur_nom, f.telephone AS fournisseur_tel, f.email AS fournisseur_email
        FROM notifications n
        LEFT JOIN produits p ON p.id = n.produit_id
        LEFT JOIN stock s ON s.produit_id = p.id
        LEFT JOIN fournisseurs f ON f.id = p.fournisseur_id";
$params = [];
if ($filter === 'unread') {
    $sql .= " WHERE n.lu = 0";
} elseif ($filter === 'read') {
    $sql .= " WHERE n.lu = 1";
}
$sql .= " ORDER BY n.lu ASC, n.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unreadCount = count_unread_notifications($db);

// Message WhatsApp global pour le super admin
$adminAlertLines = [];
foreach ($notifications as $n) {
    if ((int)$n['lu'] === 0 && in_array($n['type'], ['alerte_stock', 'alerte_site'], true)) {
        $adminAlertLines[] = '• ' . $n['titre'] . ' — ' . $n['message'];
    }
}
$adminWhatsAppMessage = "ALERTES STOCKMASTER (Super Admin)\n"
    . "Date: " . date('d/m/Y H:i') . "\n"
    . "Nombre d'alertes: " . count($adminAlertLines) . "\n\n"
    . (count($adminAlertLines) ? implode("\n", array_slice($adminAlertLines, 0, 20)) : "Aucune alerte active.")
    . "\n\nMerci de traiter rapidement.";
$adminPhone = get_super_admin_phone($db);
$adminWhatsAppUrl = whatsapp_link($adminPhone, $adminWhatsAppMessage);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.460.0/dist/umd/lucide.min.js" onload="try{lucide.createIcons()}catch(e){}"></script>
    <style>
        .notif-actions .btn i { font-size: 0.95rem; }
        .notif-icon { width: 22px; height: 22px; flex-shrink: 0; }
    </style>
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
                        <i data-lucide="bell" class="notif-icon me-2"></i>
                        <i class="fas fa-bell me-1 text-warning"></i> Notifications
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </h2>
                    <div class="d-flex flex-wrap gap-2 notif-actions">
                        <a href="<?= htmlspecialchars($adminWhatsAppUrl) ?>" target="_blank" class="btn btn-success">
                            <i class="fab fa-whatsapp me-1"></i> Alerter Super Admin (WhatsApp)
                        </a>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="resync">
                            <button class="btn btn-outline-primary"><i class="fas fa-sync-alt me-1"></i> Synchroniser</button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="mark_all_read">
                            <button class="btn btn-outline-secondary"><i class="fas fa-check-double me-1"></i> Tout marquer lu</button>
                        </form>
                    </div>
                </div>
                <div class="col-12"><hr></div>
            </div>

            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="mb-3">
                <div class="btn-group">
                    <a href="?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-danger' : 'btn-outline-danger' ?>">Non lues</a>
                    <a href="?filter=read" class="btn btn-sm <?= $filter === 'read' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Lues</a>
                    <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">Toutes</a>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="p-4 text-center text-muted">Aucune notification pour ce filtre.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $n): ?>
                                <?php
                                $qty = (int)$n['stock_qty'];
                                $seuil = (int)($n['seuil_alerte'] ?? 0);
                                $orderQty = max($seuil * 2 - $qty, 1);
                                $waMsg = "Bonjour" . (!empty($n['fournisseur_nom']) ? ' ' . $n['fournisseur_nom'] : '') . ",\n\n"
                                    . "ALERTE STOCK — " . COMPANY_NAME . "\n"
                                    . "Produit: " . ($n['produit_nom'] ?? $n['titre']) . "\n"
                                    . "Référence: " . ($n['reference'] ?? '—') . "\n"
                                    . "Stock actuel: " . $qty . "\n"
                                    . "Seuil d'alerte: " . $seuil . "\n"
                                    . "Quantité à commander: " . $orderQty . "\n\n"
                                    . "Merci de confirmer disponibilité et délai de livraison.\n"
                                    . "Contact: " . PHONE_NUMBER;
                                $waPhone = !empty($n['fournisseur_tel']) ? $n['fournisseur_tel'] : ADMIN_WHATSAPP;
                                $waUrl = whatsapp_link($waPhone, $waMsg);

                                $mailSubject = rawurlencode('Commande urgente - ' . ($n['produit_nom'] ?? $n['titre']));
                                $mailBody = rawurlencode(
                                    "Bonjour" . (!empty($n['fournisseur_nom']) ? ' ' . $n['fournisseur_nom'] : '') . ",\n\n"
                                    . "Nous vous contactons concernant une alerte de stock:\n\n"
                                    . "Produit: " . ($n['produit_nom'] ?? '') . "\n"
                                    . "Référence: " . ($n['reference'] ?? '') . "\n"
                                    . "Stock actuel: " . $qty . "\n"
                                    . "Seuil d'alerte: " . $seuil . "\n"
                                    . "Quantité souhaitée: " . $orderQty . "\n\n"
                                    . "Merci de nous confirmer la disponibilité.\n\n"
                                    . "Cordialement,\n" . COMPANY_NAME
                                );
                                $mailTo = !empty($n['fournisseur_email']) ? $n['fournisseur_email'] : ADMIN_EMAIL;
                                $mailtoUrl = 'mailto:' . rawurlencode($mailTo) . '?subject=' . $mailSubject . '&body=' . $mailBody;
                                ?>
                                <div class="list-group-item <?= (int)$n['lu'] === 0 ? ($theme == 'dark' ? 'bg-dark border-warning' : 'bg-warning-subtle') : '' ?>">
                                    <div class="d-flex flex-wrap justify-content-between gap-2">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <?php if ($n['niveau'] === 'danger'): ?>
                                                    <span class="badge bg-danger">Rupture</span>
                                                <?php elseif ($n['niveau'] === 'warning'): ?>
                                                    <span class="badge bg-warning text-dark">Alerte</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">Info</span>
                                                <?php endif; ?>
                                                <strong><?= htmlspecialchars($n['titre']) ?></strong>
                                                <?php if ((int)$n['lu'] === 0): ?>
                                                    <span class="badge bg-primary">Nouveau</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small mb-1"><?= htmlspecialchars($n['message']) ?></div>
                                            <div class="text-muted small">
                                                <?= date('d/m/Y H:i', strtotime($n['created_at'])) ?>
                                                <?php if (!empty($n['fournisseur_nom'])): ?>
                                                    — Fournisseur: <?= htmlspecialchars($n['fournisseur_nom']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-start gap-1 flex-wrap">
                                            <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" class="btn btn-sm btn-success" title="WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                            <a href="<?= htmlspecialchars($mailtoUrl) ?>" class="btn btn-sm btn-warning" title="Email">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                            <?php if ((int)$n['lu'] === 0): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-secondary" title="Marquer lu"><i class="fas fa-check"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="alert alert-info">
                <i data-lucide="info" style="width:16px;height:16px"></i>
                Configurez le numéro WhatsApp des super utilisateurs dans <strong>Utilisateurs</strong>.
                Numéro super admin actif: <strong><?= htmlspecialchars($adminPhone) ?></strong>
            </div>

            <?php $supers = get_super_admins($db); if (!empty($supers)): ?>
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">Super utilisateurs (WhatsApp)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr><th>Nom</th><th>Rôle</th><th>Téléphone</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($supers as $su): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($su['nom']) ?></td>
                                        <td><?= htmlspecialchars(role_label($su['role'] ?? 'admin')) ?></td>
                                        <td><?= htmlspecialchars($su['telephone'] ?: '—') ?></td>
                                        <td>
                                            <?php if (!empty($su['telephone'])): ?>
                                                <a class="btn btn-sm btn-success" target="_blank"
                                                   href="<?= htmlspecialchars(whatsapp_link($su['telephone'], $adminWhatsAppMessage)) ?>">
                                                    WhatsApp
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Ajouter un numéro</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide && lucide.createIcons) lucide.createIcons();
});
</script>
</body>
</html>
