<?php
require_once '../config.php';
header('Content-Type: application/json');

try {
    $stmt = $db->query("
        SELECT 
            c.nom as category,
            COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN produits p ON c.id = p.categorie_id
        GROUP BY c.id
        ORDER BY product_count DESC
    ");

    $labels = [];
    $values = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $labels[] = $row['category'];
        $values[] = (int)$row['product_count'];
    }

    $n = count($labels);
    $palette = [
        '#4e73df', '#1cc88a', '#e74a3b', '#f6c23e', '#36b9cc',
        '#9b59b6', '#e67e22', '#2ecc71', '#3498db', '#e91e63',
        '#00bcd4', '#8bc34a', '#ff5722', '#607d8b', '#795548'
    ];
    $colors = [];
    for ($i = 0; $i < $n; $i++) {
        if ($i < count($palette)) {
            $colors[] = $palette[$i];
        } else {
            $h = (int)round(fmod($i * 137.508, 360));
            $colors[] = "hsl({$h}, 70%, 48%)";
        }
    }

    echo json_encode([
        'labels' => $labels,
        'values' => $values,
        'colors' => $colors,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
