<?php
require_once 'config.php';
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$dateRange = [];
$stockData = ['entrees' => [], 'sorties' => []];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dateRange[] = date('d/m', strtotime("-$i days"));
    if ($product_id) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(quantite), 0) as total FROM mouvements WHERE type = 'entree' AND produit_id = ? AND DATE(created_at) = ?");
        $stmt->execute([$product_id, $date]);
        $entrees = $stmt->fetchColumn();
        $stmt = $db->prepare("SELECT COALESCE(SUM(quantite), 0) as total FROM mouvements WHERE type = 'sortie' AND produit_id = ? AND DATE(created_at) = ?");
        $stmt->execute([$product_id, $date]);
        $sorties = $stmt->fetchColumn();
    } else {
        $stmt = $db->prepare("SELECT COALESCE(SUM(quantite), 0) as total FROM mouvements WHERE type = 'entree' AND DATE(created_at) = ?");
        $stmt->execute([$date]);
        $entrees = $stmt->fetchColumn();
        $stmt = $db->prepare("SELECT COALESCE(SUM(quantite), 0) as total FROM mouvements WHERE type = 'sortie' AND DATE(created_at) = ?");
        $stmt->execute([$date]);
        $sorties = $stmt->fetchColumn();
    }
    $stockData['entrees'][] = (int)$entrees;
    $stockData['sorties'][] = (int)$sorties;
}
header('Content-Type: application/json');
echo json_encode([
    'dates' => $dateRange,
    'entrees' => $stockData['entrees'],
    'sorties' => $stockData['sorties']
]);