<?php
require_once 'config.php';
checkAuth();

$theme = $_REQUEST['theme'] ?? '';
if (!in_array($theme, ['light', 'dark'], true)) {
    // Bascule si aucun thème explicite
    $current = getCurrentTheme();
    $theme = $current === 'dark' ? 'light' : 'dark';
}

if (setTheme($theme)) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'theme' => $theme]);
    exit;
}

http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['ok' => false]);
