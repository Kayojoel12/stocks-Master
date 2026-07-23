<?php
require_once 'config.php';
require_roles(['admin', 'superviseur']);
migrate_schema($db);

$theme = getCurrentTheme();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $pays = trim($_POST['pays'] ?? 'Cameroun');
    $lat = trim($_POST['lat'] ?? '');
    $lng = trim($_POST['lng'] ?? '');
    $responsable = trim($_POST['responsable'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $manager_email = trim($_POST['manager_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $create_manager = !empty($_POST['create_manager']);

    if ($lat === '') {
        $lat = '3.8480';
    }
    if ($lng === '') {
        $lng = '11.5021';
    }

    if ($nom === '' || $adresse === '' || $ville === '' || $responsable === '') {
        $_SESSION['error'] = function_exists('t') ? t('all_fields_required') : 'Tous les champs sont requis.';
    } elseif ($create_manager && $manager_email === '') {
        $_SESSION['error'] = 'Email / login du gestionnaire requis.';
    } elseif ($create_manager && !is_valid_email($manager_email)) {
        $_SESSION['error'] = function_exists('t') ? t('invalid_email') : 'Email invalide.';
    } elseif ($create_manager && ($password === '' || $password !== $confirm_password)) {
        $_SESSION['error'] = function_exists('t') ? t('passwords_mismatch') : 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO sites (nom, adresse, ville, pays, lat, lng, responsable, telephone, date_creation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nom, $adresse, $ville, $pays, $lat, $lng, $responsable, $telephone ?: null]);
            $siteId = (int)$db->lastInsertId();

            if ($create_manager) {
                $manager_email = normalize_email($manager_email);
                if (email_taken_by_user($db, $manager_email)) {
                    // Auto-générer un login unique plutôt que bloquer
                    $manager_email = suggest_unique_user_email($db, $nom ?: $responsable, 'stockmaster.cm');
                }

                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $ins = $db->prepare("INSERT INTO utilisateurs (nom, email, telephone, password, role, theme_pref, site_id) VALUES (?, ?, ?, ?, 'gestionnaire', 'light', ?)");
                $ins->execute([$responsable, $manager_email, $telephone ?: null, $hashed, $siteId]);
            }

            $db->commit();
            $_SESSION['success'] = $create_manager
                ? ("Site créé. Compte gestionnaire : " . ($manager_email ?? '') . " — pensez à noter le mot de passe.")
                : "Site ajouté avec succès.";
            header('Location: sites.php');
            exit;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = "Erreur lors de l'ajout du site : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un site - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <link rel="stylesheet" href="css/style.css">
    <style>
        #map {
            height: 300px;
            width: 100%;
            border-radius: 8px;
        }
        .leaflet-container {
            background: <?= $theme == 'dark' ? '#343a40' : '#fff' ?>;
        }
    </style>
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
    <div class="wrapper">
        <?php include_once('includes/sidebar.php'); ?>

        <div id="content">
            <?php include_once('includes/navbar.php'); ?>

            <div class="m-4">
                <h2 class="mb-4">Ajouter un Nouveau Site</h2>

                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" id="siteForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nom du Site / Entrepôt</label>
                                        <input type="text" class="form-control" name="nom" id="site_nom" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Adresse</label>
                                        <input type="text" class="form-control" name="adresse" id="adresse" required value="<?= htmlspecialchars($_POST['adresse'] ?? '') ?>">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Ville</label>
                                                <select class="form-select" name="ville" id="ville" required>
                                                    <option value=""><?= function_exists('t') ? t('choose') : '-- Choisir --' ?></option>
                                                    <?php
                                                    $cmCities = function_exists('cameroon_cities') ? cameroon_cities($db) : [
                                                        ['nom' => 'Douala', 'region' => 'Littoral'],
                                                        ['nom' => 'Yaoundé', 'region' => 'Centre'],
                                                        ['nom' => 'Bafoussam', 'region' => 'Ouest'],
                                                        ['nom' => 'Garoua', 'region' => 'Nord'],
                                                        ['nom' => 'Bamenda', 'region' => 'Nord-Ouest'],
                                                        ['nom' => 'Maroua', 'region' => 'Extrême-Nord'],
                                                        ['nom' => 'Ngaoundéré', 'region' => 'Adamaoua'],
                                                        ['nom' => 'Kribi', 'region' => 'Sud'],
                                                        ['nom' => 'Limbé', 'region' => 'Sud-Ouest'],
                                                        ['nom' => 'Bertoua', 'region' => 'Est'],
                                                        ['nom' => 'Ebolowa', 'region' => 'Sud'],
                                                        ['nom' => 'Buea', 'region' => 'Sud-Ouest'],
                                                    ];
                                                    $curVille = $_POST['ville'] ?? '';
                                                    foreach ($cmCities as $city):
                                                    ?>
                                                        <option value="<?= htmlspecialchars($city['nom']) ?>"
                                                                data-lat="<?= htmlspecialchars($city['lat'] ?? '') ?>"
                                                                data-lng="<?= htmlspecialchars($city['lng'] ?? '') ?>"
                                                                <?= $curVille === $city['nom'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($city['nom']) ?><?= !empty($city['region']) ? ' (' . htmlspecialchars($city['region']) . ')' : '' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Pays</label>
                                                <input type="text" class="form-control" name="pays" id="pays" value="<?= htmlspecialchars($_POST['pays'] ?? 'Cameroun') ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>
                                    <h5 class="mb-3"><i class="fas fa-user-tie me-2"></i>Gestionnaire d'entrepôt</h5>

                                    <div class="mb-3">
                                        <label class="form-label">Nom du responsable</label>
                                        <input type="text" class="form-control" name="responsable" id="responsable" required value="<?= htmlspecialchars($_POST['responsable'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Téléphone (WhatsApp)</label>
                                        <input type="tel" class="form-control" name="telephone" id="telephone" placeholder="2376XXXXXXXX" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                                    </div>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="create_manager" value="1" id="create_manager" checked>
                                        <label class="form-check-label" for="create_manager">Créer le compte gestionnaire maintenant</label>
                                    </div>

                                    <div id="managerAccountBox">
                                        <div class="mb-3">
                                            <label class="form-label">Email / Login gestionnaire</label>
                                            <input type="email" class="form-control" name="manager_email" id="manager_email" placeholder="ex: entrepot.a@stockmaster.cm" pattern="[^\s@]+@[^\s@]+\.[^\s@]+" value="<?= htmlspecialchars($_POST['manager_email'] ?? '') ?>">
                                            <small class="text-muted">Identifiant de connexion (format email standard). S'il est déjà pris, un login unique sera généré.</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><?= function_exists('t') ? t('password') : 'Mot de passe' ?></label>
                                            <input type="password" class="form-control" name="password" id="manager_password">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><?= function_exists('t') ? t('confirm_password') : 'Confirmer le mot de passe' ?></label>
                                            <input type="password" class="form-control" name="confirm_password" id="manager_confirm_password">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Localisation</label>
                                        <div id="map"></div>
                                        <small class="text-muted">Cliquez sur la carte ou recherchez une adresse</small>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Latitude</label>
                                                <input type="text" class="form-control bg-light" name="lat" id="lat" readonly title="Rempli automatiquement" value="<?= htmlspecialchars($_POST['lat'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Longitude</label>
                                                <input type="text" class="form-control bg-light" name="lng" id="lng" readonly title="Rempli automatiquement" value="<?= htmlspecialchars($_POST['lng'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text-muted">Latitude et Longitude sont remplies automatiquement (non modifiables).</small>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                            <a href="sites.php" class="btn btn-secondary">Annuler</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const createManager = document.getElementById('create_manager');
        const managerBox = document.getElementById('managerAccountBox');
        const emailInput = document.getElementById('manager_email');
        const passInput = document.getElementById('manager_password');
        const confirmInput = document.getElementById('manager_confirm_password');

        function syncManagerBox() {
            const on = createManager.checked;
            managerBox.style.display = on ? '' : 'none';
            emailInput.required = on;
            passInput.required = on;
            confirmInput.required = on;
        }
        createManager.addEventListener('change', syncManagerBox);
        syncManagerBox();

        // Suggestion login à partir du nom du site
        document.getElementById('site_nom').addEventListener('blur', function() {
            if (emailInput.value.trim()) return;
            var slug = this.value.trim().toLowerCase()
                .replace(/[^a-z0-9]+/g, '.')
                .replace(/^\.+|\.+$/g, '');
            if (slug) emailInput.value = slug + '@stockmaster.cm';
        });

        const defaultLat = 3.8480;
        const defaultLng = 11.5021;
        const adresseInput = document.getElementById('adresse');
        const villeInput = document.getElementById('ville');
        const paysInput = document.getElementById('pays');
        const latInput = document.getElementById('lat');
        const lngInput = document.getElementById('lng');

        latInput.readOnly = true;
        lngInput.readOnly = true;
        latInput.setAttribute('tabindex', '-1');
        lngInput.setAttribute('tabindex', '-1');
        if (!latInput.value) latInput.value = defaultLat;
        if (!lngInput.value) lngInput.value = defaultLng;

        const map = L.map('map').setView([parseFloat(latInput.value) || defaultLat, parseFloat(lngInput.value) || defaultLng], 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        let marker;
        let geocodeTimer = null;
        let skipForward = false;

        function extractCity(address) {
            if (!address) return '';
            return address.city
                || address.town
                || address.village
                || address.municipality
                || address.city_district
                || address.suburb
                || address.county
                || address.state
                || '';
        }

        function extractAdresse(address, displayName) {
            if (!address) return displayName || '';
            const parts = [];
            if (address.house_number) parts.push(address.house_number);
            if (address.road) parts.push(address.road);
            if (address.neighbourhood) parts.push(address.neighbourhood);
            if (address.suburb && !parts.length) parts.push(address.suburb);
            if (address.quarter && !parts.length) parts.push(address.quarter);
            if (parts.length) return parts.join(', ');
            if (displayName) {
                return displayName.split(',').slice(0, 2).join(',').trim();
            }
            return '';
        }

        function setVilleSelect(cityName) {
            if (!cityName || !villeInput) return false;
            const name = String(cityName).trim().toLowerCase();
            let matched = false;
            Array.from(villeInput.options).forEach(function(opt) {
                if (!opt.value) return;
                const v = opt.value.toLowerCase();
                if (v === name || name.indexOf(v) !== -1 || v.indexOf(name) !== -1) {
                    villeInput.value = opt.value;
                    matched = true;
                }
            });
            return matched;
        }

        function applyCityCoords() {
            const opt = villeInput.options[villeInput.selectedIndex];
            if (!opt) return;
            const lat = parseFloat(opt.getAttribute('data-lat'));
            const lng = parseFloat(opt.getAttribute('data-lng'));
            if (!isNaN(lat) && !isNaN(lng)) {
                setMarker(L.latLng(lat, lng), 12);
            }
        }

        function fillAddressFields(address, displayName) {
            skipForward = true;
            adresseInput.value = extractAdresse(address, displayName);
            setVilleSelect(extractCity(address));
            paysInput.value = (address && address.country) ? address.country : 'Cameroun';
            setTimeout(function() { skipForward = false; }, 400);
        }

        function setMarker(latlng, zoom) {
            if (marker) {
                map.removeLayer(marker);
            }
            marker = L.marker(latlng).addTo(map);
            latInput.value = Number(latlng.lat).toFixed(8);
            lngInput.value = Number(latlng.lng).toFixed(8);
            if (zoom) {
                map.setView(latlng, zoom);
            } else {
                map.panTo(latlng);
            }
        }

        function reverseGeocode(latlng) {
            const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat='
                + encodeURIComponent(latlng.lat)
                + '&lon='
                + encodeURIComponent(latlng.lng)
                + '&accept-language=fr';

            fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function(response) {
                if (!response.ok) throw new Error('Geocoding failed');
                return response.json();
            })
            .then(function(data) {
                fillAddressFields(data.address || {}, data.display_name || '');
            })
            .catch(function() {});
        }

        function forwardGeocode() {
            if (skipForward) return;
            const parts = [adresseInput.value.trim(), villeInput.value.trim(), paysInput.value.trim()].filter(Boolean);
            if (parts.length === 0) return;
            const query = parts.join(', ');
            if (query.length < 3) return;

            const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=1'
                + '&accept-language=fr'
                + '&countrycodes=cm'
                + '&q=' + encodeURIComponent(query);

            fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function(response) {
                if (!response.ok) throw new Error('Search failed');
                return response.json();
            })
            .then(function(results) {
                if (!results || !results.length) return;
                const place = results[0];
                const latlng = L.latLng(parseFloat(place.lat), parseFloat(place.lon));
                setMarker(latlng, 14);
                if (place.address && place.address.country) {
                    paysInput.value = place.address.country;
                }
                if (!villeInput.value.trim() && place.address) {
                    setVilleSelect(extractCity(place.address));
                }
            })
            .catch(function() {});
        }

        function scheduleForwardGeocode() {
            if (skipForward) return;
            clearTimeout(geocodeTimer);
            geocodeTimer = setTimeout(forwardGeocode, 700);
        }

        adresseInput.addEventListener('change', forwardGeocode);
        villeInput.addEventListener('change', function() {
            applyCityCoords();
            forwardGeocode();
        });
        adresseInput.addEventListener('blur', forwardGeocode);
        adresseInput.addEventListener('input', scheduleForwardGeocode);

        const geocoder = L.Control.Geocoder.nominatim({
            geocodingQueryParams: {
                countrycodes: 'cm',
                'accept-language': 'fr'
            }
        });
        const control = L.Control.geocoder({
            position: 'topright',
            placeholder: 'Rechercher une adresse...',
            defaultMarkGeocode: false,
            geocoder: geocoder
        }).addTo(map);

        control.on('markgeocode', function(e) {
            const { center, name, properties } = e.geocode;
            setMarker(center, 15);
            const address = (properties && properties.address) ? properties.address : {};
            fillAddressFields(address, name);
        });

        map.on('click', function(e) {
            setMarker(e.latlng, Math.max(map.getZoom(), 14));
            reverseGeocode(e.latlng);
        });

        setMarker(L.latLng(parseFloat(latInput.value) || defaultLat, parseFloat(lngInput.value) || defaultLng));
    });
    </script>
</body>
</html>
