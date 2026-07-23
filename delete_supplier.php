<?php
require_once 'config.php';
checkAuth();
if (!can_delete()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Suppression réservée à l'administrateur"]);
    exit;
}

header('Content-Type: application/json');

try {
    // Vérifier si l'ID est présent
    if (!isset($_POST['id'])) {
        throw new Exception("ID du fournisseur manquant");
    }

    $id = (int)$_POST['id'];
    
    // Vérifier si le fournisseur est utilisé
    $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE fournisseur_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        throw new Exception("Impossible de supprimer ce fournisseur car il est associé à des produits");
    }

    // Supprimer le fournisseur
    $stmt = $db->prepare("DELETE FROM fournisseurs WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("Fournisseur non trouvé ou déjà supprimé");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Fournisseur supprimé avec succès'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}   