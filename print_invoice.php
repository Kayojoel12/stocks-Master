<?php
// Redirection : plus d'impression, téléchargement PDF direct
require_once 'config.php';
checkAuth();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header('Location: invoice_pdf.php?id=' . $id . '&download=1');
exit;
