<?php
require_once '../config.php';
header('Content-Type: application/json');

try {
    $stmt = $db->query("
        SELECT 
            DATE_FORMAT(m.created_at, '%d/%m/%Y %H:%i') as date,
            m.type,
            m.quantite as quantity,
            p.nom as product
        FROM mouvements m
        JOIN produits p ON m.produit_id = p.id
        ORDER BY m.created_at DESC
        LIMIT 5
    ");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}