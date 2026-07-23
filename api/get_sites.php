<?php
// api/get_sites.php
require_once '../config.php';
checkAuth();

header('Content-Type: application/json');

try {
    $stmt = $db->query("SELECT id, nom AS name, adresse AS address, ville AS city, pays AS country,
                               lat, lng, responsable, telephone
                        FROM sites
                        ORDER BY nom");
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normaliser lat/lng en nombres pour le JS
    foreach ($sites as &$site) {
        $site['id'] = (int)$site['id'];
        $site['lat'] = isset($site['lat']) ? (float)$site['lat'] : null;
        $site['lng'] = isset($site['lng']) ? (float)$site['lng'] : null;
        $site['name'] = (string)($site['name'] ?? '');
        $site['address'] = (string)($site['address'] ?? '');
        $site['responsable'] = (string)($site['responsable'] ?? '');
        $site['telephone'] = (string)($site['telephone'] ?? '');
    }
    unset($site);

    echo json_encode($sites, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}