<?php
require_once '../config.php';
header('Content-Type: application/json');

try {
    $stats = [
        'total_products' => $db->query("SELECT COUNT(*) FROM produits")->fetchColumn(),
        'total_value' => (float)$db->query("SELECT IFNULL(SUM(quantite * prix_achat), 0) FROM produits JOIN stock ON produits.id = stock.produit_id")->fetchColumn(),
        'alerts_count' => $db->query("SELECT COUNT(*) FROM produits JOIN stock ON produits.id = stock.produit_id WHERE stock.quantite <= produits.seuil_alerte")->fetchColumn(),
        'suppliers_count' => $db->query("SELECT COUNT(*) FROM fournisseurs")->fetchColumn()
    ];
    echo json_encode($stats);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}