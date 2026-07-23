<?php
require_once '../config.php';
header('Content-Type: application/json');

try {
    $stmt = $db->query("
        SELECT 
            p.id as product_id,
            p.nom as product_name,
            s.quantite as current_quantity,
            p.seuil_alerte as alert_threshold
        FROM produits p
        JOIN stock s ON p.id = s.produit_id
        WHERE s.quantite <= p.seuil_alerte
        ORDER BY s.quantite ASC
        LIMIT 5
    ");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}