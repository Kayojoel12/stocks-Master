<?php
// ============================================================
// Configuration via variables d'environnement (Render) ou valeurs par défaut (local)
// ============================================================

// Base de données
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'gestion_stock');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Autres variables utiles pour Render (email, WhatsApp, etc.) – valeurs par défaut locales
define('EMAIL_FROM', getenv('EMAIL_FROM') ?: 'StockMaster <no-reply@votre-domaine.com>');
define('EMAIL_REPLY_TO', getenv('EMAIL_REPLY_TO') ?: 'commandes@votre-domaine.com');
define('PHONE_NUMBER', getenv('PHONE_NUMBER') ?: '+237 XXX XXX XXX');
define('ADMIN_WHATSAPP', getenv('ADMIN_WHATSAPP') ?: '237670000000');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@stock.com');
define('COMPANY_NAME', getenv('COMPANY_NAME') ?: 'StockMaster');

// Démarrer la session une seule fois
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// Connexion à la base de données (avec le port)
// ============================================================
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("SET NAMES 'utf8'");
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// ============================================================
// Le reste de votre code (fonctions, migrations, etc.) reste IDENTIQUE
// ============================================================

/**
 * Normalise un numéro pour WhatsApp (chiffres uniquement, préfixe 237 si besoin)
 */
function whatsapp_phone($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') {
        return '';
    }
    if (strpos($digits, '237') === 0) {
        return $digits;
    }
    if (strlen($digits) === 9) {
        return '237' . $digits;
    }
    return $digits;
}

function whatsapp_link($phone, $message) {
    $num = whatsapp_phone($phone);
    if ($num === '') {
        $num = whatsapp_phone(ADMIN_WHATSAPP);
    }
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($message);
}

// ... TOUTES VOS AUTRES FONCTIONS (migrate_schema, cameroon_cities, role helpers, etc.) ...
// Reprenez ici tout le code qui suit, à partir de "function ensure_notifications_table(PDO $db) {"
// jusqu'à la fin du fichier, sans aucune modification.
// Je ne le recopie pas pour éviter de surcharger, mais vous devez le garder.
