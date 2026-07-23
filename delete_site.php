<?php
require_once 'config.php';
checkAuth();
if (!can_delete()) {
    $_SESSION['error'] = 'Suppression réservée à l\'administrateur.';
    header('Location: sites.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: sites.php');
    exit();
}

$id = (int)$_GET['id'];

// Vérifier que le site existe
$stmt = $db->prepare('SELECT * FROM sites WHERE id = ?');
$stmt->execute([$id]);
$site = $stmt->fetch();
if (!$site) {
    $_SESSION['error'] = 'Site introuvable.';
    header('Location: sites.php');
    exit();
}

// Supprimer le site
$stmt = $db->prepare('DELETE FROM sites WHERE id = ?');
if ($stmt->execute([$id])) {
    $_SESSION['success'] = 'Site supprimé avec succès!';
} else {
    $_SESSION['error'] = 'Erreur lors de la suppression du site.';
}
header('Location: sites.php');
exit(); 