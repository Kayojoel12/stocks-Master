<?php
require_once 'config.php';

$lang = $_GET['lang'] ?? 'en';
if (!in_array($lang, ['fr', 'en'], true)) {
    $lang = 'en';
}
$_SESSION['lang'] = $lang;

$redirect = $_GET['redirect'] ?? 'index.php';
$redirect = trim($redirect);

if ($redirect === '' || str_contains($redirect, "\n") || str_contains($redirect, "\r")) {
    $redirect = 'index.php';
}

if (str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://')) {
    $redirect = 'index.php';
}

header('Location: ' . $redirect);
exit;
?>
