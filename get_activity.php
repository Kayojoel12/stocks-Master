<?php
require_once 'config.php';
$stmt = $db->query("SELECT m.created_at, m.type, m.quantite, p.nom as produit, u.nom as utilisateur FROM mouvements m JOIN produits p ON m.produit_id = p.id JOIN utilisateurs u ON m.utilisateur_id = u.id ORDER BY m.created_at DESC LIMIT 5");
while ($row = $stmt->fetch()) {
    echo '<tr>';
    echo '<td>' . date('d/m/Y H:i', strtotime($row['created_at'])) . '</td>';
    echo '<td>' . ($row['type'] == 'entree' ? t('entry') : t('exit')) . '</td>';
    echo '<td>' . htmlspecialchars($row['produit']) . '</td>';
    echo '<td>' . ($row['type'] == 'entree' ? '+' : '-') . $row['quantite'] . '</td>';
    echo '</tr>';
}
