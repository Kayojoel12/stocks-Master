<?php
require_once '../config.php';
header('Content-Type: application/json');

try {
    $days = 30;
    $dates = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = new DateTime("-{$i} days");
        $dates[$date->format('Y-m-d')] = $date->format('d/m');
    }

    $stmt = $db->prepare("SELECT DATE(created_at) as date, SUM(CASE WHEN type = 'entree' THEN quantite ELSE 0 END) as entrees, SUM(CASE WHEN type = 'sortie' THEN quantite ELSE 0 END) as sorties FROM mouvements WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at)");
    $stmt->execute([$days - 1]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dataMap = [];
    foreach ($rows as $row) {
        $dataMap[$row['date']] = [
            'entrees' => (int)$row['entrees'],
            'sorties' => (int)$row['sorties'],
        ];
    }

    $labels = [];
    $entrees = [];
    $sorties = [];
    foreach ($dates as $dateKey => $label) {
        $labels[] = $label;
        $entrees[] = $dataMap[$dateKey]['entrees'] ?? 0;
        $sorties[] = $dataMap[$dateKey]['sorties'] ?? 0;
    }

    echo json_encode([
        'labels' => $labels,
        'entrees' => $entrees,
        'sorties' => $sorties,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
