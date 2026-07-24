<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'gestion_stock');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('EMAIL_FROM', getenv('EMAIL_FROM') ?: 'StockMaster <no-reply@votre-domaine.com>');
define('EMAIL_REPLY_TO', getenv('EMAIL_REPLY_TO') ?: 'commandes@votre-domaine.com');
define('PHONE_NUMBER', getenv('PHONE_NUMBER') ?: '+237 XXX XXX XXX');
define('ADMIN_WHATSAPP', getenv('ADMIN_WHATSAPP') ?: '237670000000');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@stock.com');
define('COMPANY_NAME', getenv('COMPANY_NAME') ?: 'StockMaster');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $db = new PDO("mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("SET NAMES 'utf8'");
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

function whatsapp_phone($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return '';
    if (strpos($digits, '237') === 0) return $digits;
    if (strlen($digits) === 9) return '237' . $digits;
    return $digits;
}
function whatsapp_link($phone, $message) {
    $num = whatsapp_phone($phone);
    if ($num === '') $num = whatsapp_phone(ADMIN_WHATSAPP);
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($message);
}
function ensure_notifications_table(PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT(11) NOT NULL AUTO_INCREMENT,
        type VARCHAR(50) NOT NULL DEFAULT 'alerte_stock',
        titre VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        produit_id INT(11) DEFAULT NULL,
        site_id INT(11) DEFAULT NULL,
        niveau ENUM('info','warning','danger') NOT NULL DEFAULT 'warning',
        lu TINYINT(1) NOT NULL DEFAULT 0,
        destinataire_role VARCHAR(50) NOT NULL DEFAULT 'admin',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_lu (lu),
        KEY idx_produit (produit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function sync_stock_alert_notifications(PDO $db) {
    ensure_notifications_table($db);
    $alerts = $db->query("
        SELECT p.id, p.nom, p.reference, p.seuil_alerte, COALESCE(s.quantite, 0) AS quantite
        FROM produits p
        LEFT JOIN stock s ON s.produit_id = p.id
        WHERE COALESCE(s.quantite, 0) <= p.seuil_alerte
    ")->fetchAll(PDO::FETCH_ASSOC);
    $check = $db->prepare("
        SELECT id FROM notifications
        WHERE type = 'alerte_stock' AND produit_id = ? AND lu = 0
        LIMIT 1
    ");
    $insert = $db->prepare("
        INSERT INTO notifications (type, titre, message, produit_id, niveau, destinataire_role)
        VALUES ('alerte_stock', ?, ?, ?, ?, 'admin')
    ");
    foreach ($alerts as $a) {
        $qty = (int)$a['quantite'];
        $seuil = (int)$a['seuil_alerte'];
        $isOut = $qty <= 0;
        $niveau = $isOut ? 'danger' : 'warning';
        $titre = $isOut ? ('Rupture: ' . $a['nom']) : ('Stock faible: ' . $a['nom']);
        $message = sprintf("Produit %s [%s] — stock actuel: %d (seuil: %d). %s",
            $a['nom'], $a['reference'], $qty, $seuil,
            $isOut ? 'Rupture de stock. Commande urgente recommandée.' : 'Réapprovisionnement recommandé.'
        );
        $check->execute([(int)$a['id']]);
        if (!$check->fetchColumn()) {
            $insert->execute([$titre, $message, (int)$a['id'], $niveau]);
        }
    }
    try {
        $siteAlerts = $db->query("
            SELECT si.site_id, s.nom AS site_nom, p.id AS produit_id, p.nom, p.reference,
                   si.quantite, p.seuil_alerte
            FROM site_inventaire si
            JOIN sites s ON s.id = si.site_id
            JOIN produits p ON p.id = si.produit_id
            WHERE si.quantite > 0 AND si.quantite <= p.seuil_alerte
        ")->fetchAll(PDO::FETCH_ASSOC);
        $checkSite = $db->prepare("
            SELECT id FROM notifications
            WHERE type = 'alerte_site' AND produit_id = ? AND site_id = ? AND lu = 0
            LIMIT 1
        ");
        $insertSite = $db->prepare("
            INSERT INTO notifications (type, titre, message, produit_id, site_id, niveau, destinataire_role)
            VALUES ('alerte_site', ?, ?, ?, ?, 'warning', 'admin')
        ");
        foreach ($siteAlerts as $a) {
            $checkSite->execute([(int)$a['produit_id'], (int)$a['site_id']]);
            if ($checkSite->fetchColumn()) continue;
            $titre = 'Alerte entrepôt ' . $a['site_nom'] . ': ' . $a['nom'];
            $message = sprintf("Entrepôt %s — produit %s [%s] stock: %d (seuil %d).",
                $a['site_nom'], $a['nom'], $a['reference'],
                (int)$a['quantite'], (int)$a['seuil_alerte']
            );
            $insertSite->execute([$titre, $message, (int)$a['produit_id'], (int)$a['site_id']]);
        }
    } catch (Exception $e) {}
}
function count_unread_notifications(PDO $db) {
    try {
        ensure_notifications_table($db);
        return (int)$db->query("SELECT COUNT(*) FROM notifications WHERE lu = 0")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}
function migrate_schema(PDO $db) {
    static $done = false;
    if ($done) return;
    $done = true;
    try { $db->exec("ALTER TABLE utilisateurs MODIFY id INT(11) NOT NULL AUTO_INCREMENT"); } catch (Exception $e) {}
    try {
        $cols = $db->query("SHOW COLUMNS FROM utilisateurs LIKE 'telephone'")->fetch();
        if (!$cols) $db->exec("ALTER TABLE utilisateurs ADD COLUMN telephone VARCHAR(30) DEFAULT NULL AFTER email");
    } catch (Exception $e) {}
    try {
        $db->exec("ALTER TABLE utilisateurs MODIFY role ENUM('admin','superviseur','caissier','gestionnaire','utilisateur','fournisseur') DEFAULT 'utilisateur'");
    } catch (Exception $e) {}
    try {
        $cols = $db->query("SHOW COLUMNS FROM utilisateurs LIKE 'fournisseur_id'")->fetch();
        if (!$cols) $db->exec("ALTER TABLE utilisateurs ADD COLUMN fournisseur_id INT(11) DEFAULT NULL AFTER telephone");
    } catch (Exception $e) {}
    try {
        $cols = $db->query("SHOW COLUMNS FROM utilisateurs LIKE 'site_id'")->fetch();
        if (!$cols) $db->exec("ALTER TABLE utilisateurs ADD COLUMN site_id INT(11) DEFAULT NULL AFTER fournisseur_id");
    } catch (Exception $e) {}
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS fournisseur_cargaisons (
            id INT(11) NOT NULL AUTO_INCREMENT,
            fournisseur_id INT(11) NOT NULL,
            reference VARCHAR(50) NOT NULL,
            description TEXT DEFAULT NULL,
            montant_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            montant_paye DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            date_cargaison DATE NOT NULL,
            statut ENUM('ouverte','partielle','soldee','retard') NOT NULL DEFAULT 'ouverte',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_fournisseur (fournisseur_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS fournisseur_reglements (
            id INT(11) NOT NULL AUTO_INCREMENT,
            cargaison_id INT(11) NOT NULL,
            fournisseur_id INT(11) NOT NULL,
            montant DECIMAL(12,2) NOT NULL,
            type_reglement ENUM('paiement','avance','avoir') NOT NULL DEFAULT 'paiement',
            date_reglement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            notes TEXT DEFAULT NULL,
            utilisateur_id INT(11) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_cargaison (cargaison_id),
            KEY idx_fournisseur (fournisseur_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS fournisseur_demandes (
            id INT(11) NOT NULL AUTO_INCREMENT,
            fournisseur_id INT(11) NOT NULL,
            titre VARCHAR(150) NOT NULL,
            description TEXT DEFAULT NULL,
            produit_id INT(11) DEFAULT NULL,
            quantite INT(11) NOT NULL DEFAULT 1,
            prix_unitaire DECIMAL(12,2) DEFAULT NULL,
            statut ENUM('brouillon','soumise','acceptee','refusee','livree') NOT NULL DEFAULT 'brouillon',
            notes_admin TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            soumise_at DATETIME DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_fourn_dem (fournisseur_id),
            KEY idx_statut (statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS fournisseur_rappels (
            id INT(11) NOT NULL AUTO_INCREMENT,
            fournisseur_id INT(11) NOT NULL,
            cargaison_id INT(11) NOT NULL,
            message TEXT NOT NULL,
            canal ENUM('interne','email','whatsapp') NOT NULL DEFAULT 'interne',
            created_by INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            lu TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_fourn_rap (fournisseur_id),
            KEY idx_cargo_rap (cargaison_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS fournisseur_photos (
            id INT(11) NOT NULL AUTO_INCREMENT,
            fournisseur_id INT(11) NOT NULL,
            titre VARCHAR(150) NOT NULL,
            description TEXT DEFAULT NULL,
            image_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_fourn_photo (fournisseur_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS fournisseur_demandes_paiement (
            id INT(11) NOT NULL AUTO_INCREMENT,
            fournisseur_id INT(11) NOT NULL,
            cargaison_id INT(11) DEFAULT NULL,
            montant DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            tranche VARCHAR(80) NOT NULL DEFAULT 'Prochaine tranche',
            message TEXT DEFAULT NULL,
            statut ENUM('soumise','en_cours','payee','refusee') NOT NULL DEFAULT 'soumise',
            created_by INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            traite_par INT(11) DEFAULT NULL,
            traite_at DATETIME DEFAULT NULL,
            notes_traitement TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_fourn_dp (fournisseur_id),
            KEY idx_statut_dp (statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    try {
        $cols = $db->query("SHOW COLUMNS FROM factures LIKE 'paiement_recu'")->fetch();
        if (!$cols) {
            $db->exec("ALTER TABLE factures ADD COLUMN paiement_recu TINYINT(1) NOT NULL DEFAULT 0 AFTER mode_paiement");
            $db->exec("ALTER TABLE factures ADD COLUMN paiement_confirme_par INT(11) DEFAULT NULL AFTER paiement_recu");
            $db->exec("ALTER TABLE factures ADD COLUMN paiement_confirme_at DATETIME DEFAULT NULL AFTER paiement_confirme_par");
        }
    } catch (Exception $e) {}
    try {
        $ai = $db->query("SHOW COLUMNS FROM paiements LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
        if ($ai && stripos((string)($ai['Extra'] ?? ''), 'auto_increment') === false) {
            $db->exec("ALTER TABLE paiements MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
        }
    } catch (Exception $e) {}
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS regions_cameroun (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nom VARCHAR(80) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY nom (nom)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS villes_cameroun (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nom VARCHAR(80) NOT NULL,
            region_id INT(11) DEFAULT NULL,
            lat DECIMAL(10,8) DEFAULT NULL,
            lng DECIMAL(11,8) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY nom (nom)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $cnt = (int)$db->query("SELECT COUNT(*) FROM villes_cameroun")->fetchColumn();
        if ($cnt === 0) {
            $db->exec("INSERT INTO regions_cameroun (id, nom) VALUES
                (1,'Adamaoua'),(2,'Centre'),(3,'Est'),(4,'Extrême-Nord'),(5,'Littoral'),
                (6,'Nord'),(7,'Nord-Ouest'),(8,'Ouest'),(9,'Sud'),(10,'Sud-Ouest')");
            $db->exec("INSERT INTO villes_cameroun (nom, region_id, lat, lng) VALUES
                ('Douala',5,4.0511,9.7679),('Yaoundé',2,3.8480,11.5021),('Bafoussam',8,5.4778,10.4176),
                ('Garoua',6,9.3015,13.3927),('Bamenda',7,5.9597,10.1460),('Maroua',4,10.5910,14.3158),
                ('Ngaoundéré',1,7.3167,13.5833),('Kribi',9,2.9370,9.9077),('Limbé',10,4.0225,9.2080),
                ('Bertoua',3,4.5773,13.6846),('Ebolowa',9,2.9000,11.1500),('Buea',10,4.1550,9.2410),
                ('Edéa',5,3.8000,10.1333),('Kumba',10,4.6363,9.4465),('Dschang',8,5.4500,10.0667),
                ('Foumban',8,5.7293,10.9000),('Nkongsamba',5,4.9547,9.9404),('Sangmélima',9,2.9333,11.9833),
                ('Loum',5,4.7183,9.7351),('Mbalmayo',2,3.5167,11.5000)");
        }
    } catch (Exception $e) {}
    try {
        $db->exec("UPDATE utilisateurs SET role = 'utilisateur' WHERE role IS NULL OR role = '' OR role = 'user'");
    } catch (Exception $e) {}
}
migrate_schema($db);

function cameroon_cities(PDO $db = null) {
    static $cache = null;
    if ($cache !== null) return $cache;
    if ($db === null) { global $db; }
    $fallback = [
        ['nom' => 'Douala', 'region' => 'Littoral', 'lat' => '4.0511', 'lng' => '9.7679'],
        ['nom' => 'Yaoundé', 'region' => 'Centre', 'lat' => '3.8480', 'lng' => '11.5021'],
        ['nom' => 'Bafoussam', 'region' => 'Ouest', 'lat' => '5.4778', 'lng' => '10.4176'],
        ['nom' => 'Garoua', 'region' => 'Nord', 'lat' => '9.3015', 'lng' => '13.3927'],
        ['nom' => 'Bamenda', 'region' => 'Nord-Ouest', 'lat' => '5.9597', 'lng' => '10.1460'],
        ['nom' => 'Maroua', 'region' => 'Extrême-Nord', 'lat' => '10.5910', 'lng' => '14.3158'],
        ['nom' => 'Ngaoundéré', 'region' => 'Adamaoua', 'lat' => '7.3167', 'lng' => '13.5833'],
        ['nom' => 'Kribi', 'region' => 'Sud', 'lat' => '2.9370', 'lng' => '9.9077'],
        ['nom' => 'Limbé', 'region' => 'Sud-Ouest', 'lat' => '4.0225', 'lng' => '9.2080'],
        ['nom' => 'Bertoua', 'region' => 'Est', 'lat' => '4.5773', 'lng' => '13.6846'],
        ['nom' => 'Ebolowa', 'region' => 'Sud', 'lat' => '2.9000', 'lng' => '11.1500'],
        ['nom' => 'Buea', 'region' => 'Sud-Ouest', 'lat' => '4.1550', 'lng' => '9.2410'],
        ['nom' => 'Edéa', 'region' => 'Littoral', 'lat' => '3.8000', 'lng' => '10.1333'],
        ['nom' => 'Kumba', 'region' => 'Sud-Ouest', 'lat' => '4.6363', 'lng' => '9.4465'],
        ['nom' => 'Dschang', 'region' => 'Ouest', 'lat' => '5.4500', 'lng' => '10.0667'],
        ['nom' => 'Foumban', 'region' => 'Ouest', 'lat' => '5.7293', 'lng' => '10.9000'],
        ['nom' => 'Nkongsamba', 'region' => 'Littoral', 'lat' => '4.9547', 'lng' => '9.9404'],
        ['nom' => 'Sangmélima', 'region' => 'Sud', 'lat' => '2.9333', 'lng' => '11.9833'],
        ['nom' => 'Loum', 'region' => 'Littoral', 'lat' => '4.7183', 'lng' => '9.7351'],
        ['nom' => 'Mbalmayo', 'region' => 'Centre', 'lat' => '3.5167', 'lng' => '11.5000']
    ];
    try {
        migrate_schema($db);
        $rows = $db->query("SELECT v.nom, r.nom AS region, v.lat, v.lng
            FROM villes_cameroun v
            LEFT JOIN regions_cameroun r ON r.id = v.region_id
            ORDER BY v.nom")->fetchAll(PDO::FETCH_ASSOC);
        $cache = $rows ?: $fallback;
    } catch (Exception $e) {
        $cache = $fallback;
    }
    return $cache;
}

function current_role() { return $_SESSION['role'] ?? 'utilisateur'; }
function role_label($role = null) {
    $role = $role ?? current_role();
    $labels = [
        'admin' => function_exists('t') ? t('admin') : 'Administrateur',
        'superviseur' => function_exists('t') ? t('supervisor') : 'Superviseur',
        'caissier' => function_exists('t') ? t('cashier') : 'Caissier',
        'gestionnaire' => function_exists('t') ? t('warehouse_manager') : "Gestionnaire d'entrepôt",
        'utilisateur' => function_exists('t') ? t('user') : 'Utilisateur',
        'fournisseur' => function_exists('t') ? t('supplier') : 'Fournisseur',
    ];
    return $labels[$role] ?? $role;
}
function role_badge_class($role = null) {
    $role = $role ?? current_role();
    $map = [
        'admin' => 'role-badge-admin',
        'superviseur' => 'role-badge-supervisor',
        'caissier' => 'role-badge-cashier',
        'gestionnaire' => 'role-badge-warehouse',
        'utilisateur' => 'role-badge-user',
        'fournisseur' => 'role-badge-supplier'
    ];
    return $map[$role] ?? 'role-badge-user';
}
function has_role($roles) { $roles = (array)$roles; return in_array(current_role(), $roles, true); }
function is_admin() { return has_role('admin'); }
function is_superviseur() { return has_role(['admin','superviseur']); }
function is_caissier() { return has_role('caissier'); }
function is_warehouse_manager() { return has_role('gestionnaire'); }
function can_delete() { return is_admin(); }
function can_manage_users() { return is_admin(); }
function can_notify() { return has_role(['admin','superviseur']); }
function can_edit_content() { return has_role(['admin','superviseur','gestionnaire']); }
function can_cashier_ops() { return has_role(['admin','caissier']); }
function can_manage_stock() { return has_role(['admin','superviseur','gestionnaire']); }
function can_manage_products() { return has_role(['admin','superviseur','gestionnaire']); }
function can_set_prices() { return has_role(['admin','superviseur']); }
function can_view_reports() { return has_role(['admin','superviseur']); }
function can_manage_suppliers() { return has_role(['admin','superviseur']); }
function can_manage_sites() { return has_role(['admin','superviseur','gestionnaire']); }
function is_fournisseur() { return has_role('fournisseur'); }
function can_order_without_whatsapp() { return is_superviseur(); }

function role_home_page() {
    if (is_admin()) return 'index.php';
    if (is_fournisseur()) return 'supplier_portal.php';
    if (is_warehouse_manager()) return 'warehouse_dashboard.php';
    if (is_caissier()) return 'create_invoice.php';
    if (has_role('superviseur')) return 'reports.php';
    return 'index.php';
}

function product_image_url($path) {
    $path = trim((string)$path);
    if ($path !== '' && is_file(__DIR__ . '/' . ltrim($path, '/'))) return $path;
    return 'data:image/svg+xml,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="160" viewBox="0 0 200 160">'
        . '<rect fill="#e9ecef" width="200" height="160"/>'
        . '<rect x="60" y="40" width="80" height="60" rx="6" fill="#adb5bd"/>'
        . '<circle cx="85" cy="58" r="8" fill="#ced4da"/>'
        . '<text x="100" y="130" text-anchor="middle" fill="#6c757d" font-size="12" font-family="sans-serif">Aucune photo</text>'
        . '</svg>'
    );
}

function notify_roles(PDO $db, array $roles, $type, $titre, $message, $niveau = 'warning') {
    ensure_notifications_table($db);
    $ins = $db->prepare("INSERT INTO notifications (type, titre, message, niveau, destinataire_role) VALUES (?, ?, ?, ?, ?)");
    foreach (array_unique($roles) as $role) {
        $ins->execute([$type, $titre, $message, $niveau, $role]);
    }
}

function save_supplier_photo_upload($fournisseurId, $fileField = 'photo') {
    if (empty($_FILES[$fileField]) || ($_FILES[$fileField]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, 'Aucun fichier sélectionné.'];
    }
    $f = $_FILES[$fileField];
    if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [null, 'Erreur upload (code ' . (int)$f['error'] . ').'];
    }
    if (($f['size'] ?? 0) > 5 * 1024 * 1024) {
        return [null, 'Image trop lourde (max 5 Mo).'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($f['tmp_name']);
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif'
    ];
    if (!isset($map[$mime])) {
        return [null, 'Format non autorisé (JPG, PNG, WEBP, GIF).'];
    }
    $dir = __DIR__ . '/uploads/suppliers/' . (int)$fournisseurId;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return [null, 'Impossible de créer le dossier upload.'];
    }
    $name = 'photo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $map[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        return [null, 'Échec de l\'enregistrement du fichier.'];
    }
    return ['uploads/suppliers/' . (int)$fournisseurId . '/' . $name, null];
}

function is_valid_email($email) {
    $email = trim((string)$email);
    if ($email === '' || strlen($email) > 100) return false;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $parts = explode('@', $email);
    if (count($parts) !== 2) return false;
    return str_contains($parts[1], '.');
}

function normalize_email($email) { return strtolower(trim((string)$email)); }

function email_taken_by_user($db, $email, $excludeUserId = 0) {
    $email = normalize_email($email);
    if ($email === '') return false;
    if ($excludeUserId > 0) {
        $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE LOWER(TRIM(email)) = ? AND id <> ? LIMIT 1");
        $stmt->execute([$email, (int)$excludeUserId]);
    } else {
        $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE LOWER(TRIM(email)) = ? LIMIT 1");
        $stmt->execute([$email]);
    }
    return (bool)$stmt->fetchColumn();
}

function suggest_unique_user_email($db, $baseName, $domain = 'stockmaster.cm') {
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '.', (string)$baseName), '.'));
    if ($slug === '') $slug = 'compte';
    $candidate = $slug . '@' . $domain;
    $n = 1;
    while (email_taken_by_user($db, $candidate) && $n < 200) {
        $candidate = $slug . $n . '@' . $domain;
        $n++;
    }
    return $candidate;
}

function get_user_site_id($db = null, $userId = null) {
    if ($db === null) { global $db; }
    $userId = $userId ?: ($_SESSION['user_id'] ?? 0);
    if (!$userId) return 0;
    if (isset($_SESSION['site_id']) && $_SESSION['site_id'] !== null && $_SESSION['site_id'] !== '') {
        return (int)$_SESSION['site_id'];
    }
    try {
        $stmt = $db->prepare("SELECT site_id FROM utilisateurs WHERE id = ?");
        $stmt->execute([(int)$userId]);
        $sid = (int)$stmt->fetchColumn();
        $_SESSION['site_id'] = $sid;
        return $sid;
    } catch (Exception $e) {
        return 0;
    }
}

function require_roles($roles) {
    checkAuth();
    if (!has_role($roles)) {
        flash_set(function_exists('t') ? t('access_denied') : 'Accès refusé pour votre rôle.', 'error', 'index.php');
        header('Location: index.php');
        exit;
    }
}

function flash_set($message, $type = 'success', $page = null) {
    $page = $page ?: basename($_SERVER['PHP_SELF'] ?? 'index.php');
    $_SESSION['flash'] = [
        'message' => (string)$message,
        'type' => in_array($type, ['success','error','danger','warning','info'], true) ? $type : 'success',
        'page' => $page,
    ];
    unset($_SESSION['success'], $_SESSION['error'], $_SESSION['alert'], $_SESSION['flash_page']);
}

function flash_render($page = null) {
    $page = $page ?: basename($_SERVER['PHP_SELF'] ?? 'index.php');
    if (empty($_SESSION['flash'])) {
        if (!empty($_SESSION['flash_page']) && $_SESSION['flash_page'] !== $page) {
            return '';
        }
        if (!empty($_SESSION['success'])) {
            if (!empty($_SESSION['flash_page']) && $_SESSION['flash_page'] === $page) {
                $_SESSION['flash'] = ['message' => $_SESSION['success'], 'type' => 'success', 'page' => $page];
            } elseif (empty($_SESSION['flash_page'])) {
                unset($_SESSION['success']);
            }
        }
        if (!empty($_SESSION['error'])) {
            if (!empty($_SESSION['flash_page']) && $_SESSION['flash_page'] === $page) {
                $_SESSION['flash'] = ['message' => $_SESSION['error'], 'type' => 'danger', 'page' => $page];
            } elseif (empty($_SESSION['flash_page'])) {
                unset($_SESSION['error']);
            }
        }
        if (!empty($_SESSION['alert']) && is_array($_SESSION['alert'])) {
            $ap = $_SESSION['flash_page'] ?? $page;
            if ($ap === $page) {
                $_SESSION['flash'] = [
                    'message' => $_SESSION['alert']['message'] ?? '',
                    'type' => $_SESSION['alert']['type'] ?? 'info',
                    'page' => $page,
                ];
            }
            if ($ap === $page || empty($_SESSION['flash_page'])) {
                unset($_SESSION['alert']);
            }
        }
        unset($_SESSION['success'], $_SESSION['error']);
        if (!empty($_SESSION['flash_page']) && $_SESSION['flash_page'] === $page) {
            unset($_SESSION['flash_page']);
        }
    }
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    if (!empty($f['page']) && $f['page'] !== $page) {
        return '';
    }
    unset($_SESSION['flash'], $_SESSION['flash_page'], $_SESSION['success'], $_SESSION['error'], $_SESSION['alert']);
    $type = $f['type'] ?? 'success';
    if ($type === 'error') $type = 'danger';
    $msg = htmlspecialchars($f['message'] ?? '', ENT_QUOTES, 'UTF-8');
    if ($msg === '') return '';
    return '<div class="alert alert-' . htmlspecialchars($type, ENT_QUOTES) . ' alert-dismissible fade show flash-message" role="alert">'
        . $msg
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
        . '</div>';
}

function get_super_admin_phone(PDO $db) {
    try {
        $phone = $db->query("SELECT telephone FROM utilisateurs WHERE role = 'admin' AND telephone IS NOT NULL AND telephone != '' ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($phone) return $phone;
    } catch (Exception $e) {}
    return ADMIN_WHATSAPP;
}

function get_super_admins(PDO $db) {
    try {
        return $db->query("SELECT id, nom, email, telephone, role FROM utilisateurs WHERE role IN ('admin','superviseur') ORDER BY role, nom")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    if (empty($_SESSION['role'])) {
        global $db;
        try {
            $stmt = $db->prepare("SELECT role, telephone, nom FROM utilisateurs WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                $_SESSION['role'] = $u['role'] ?: 'utilisateur';
                $_SESSION['telephone'] = $u['telephone'] ?? '';
                $_SESSION['nom'] = $u['nom'] ?? ($_SESSION['nom'] ?? '');
            }
        } catch (Exception $e) {}
    }
}

function setTheme($theme) {
    if (!in_array($theme, ['light','dark'])) return false;
    $_SESSION['theme'] = $theme;
    if (isset($_SESSION['user_id'])) {
        global $db;
        $stmt = $db->prepare("UPDATE utilisateurs SET theme_pref = ? WHERE id = ?");
        return $stmt->execute([$theme, $_SESSION['user_id']]);
    }
    return true;
}

function getCurrentTheme() {
    if (isset($_SESSION['user_id'])) {
        global $db;
        $stmt = $db->prepare("SELECT theme_pref FROM utilisateurs WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user !== false && isset($user['theme_pref'])) {
            return $user['theme_pref'];
        }
    }
    return $_SESSION['theme'] ?? 'light';
}

$lang = $_SESSION['lang'] ?? 'fr';
$langFile = __DIR__ . '/lang/' . $lang . '.php';
if (file_exists($langFile)) {
    $tr = require $langFile;
} else {
    $tr = [
        'admin' => 'Administrateur',
        'cashier' => 'Caissier',
        'supplier' => 'Fournisseur',
        'supervisor' => 'Superviseur',
        'warehouse_manager' => 'Gestionnaire entrepôt',
        'login' => 'Connexion',
        'login_as' => 'Se connecter en tant que',
        'password' => 'Mot de passe',
        'invalid_credentials' => 'Email ou mot de passe incorrect.',
        'role_mismatch' => 'Rôle incompatible avec ce portail.',
        'access_denied' => 'Accès refusé',
        'accounts_by_admin_only' => 'Les comptes sont créés par l\'administrateur.',
        'user' => 'Utilisateur',
    ];
}
function t($key) {
    global $tr;
    return $tr[$key] ?? $key;
}
// NE PAS AJOUTER DE ?> 
