<?php
require_once 'config.php';
checkAuth();

$theme = getCurrentTheme();
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    <!-- Leaflet MarkerCluster -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css">
    <!-- Leaflet Geocoder -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <link rel="stylesheet" href="css/style.css">
    <style>
        #map {
            height: 500px;
            width: 100%;
            border-radius: 8px;
        }
        .leaflet-container {
            background: <?= $theme == 'dark' ? '#343a40' : '#fff' ?>;
        }
        .search-container-static {
            width: 350px;
        }
        .route-controls {
            position: absolute;
            bottom: 30px;
            left: 10px;
            z-index: 1000;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        .custom-icon {
            background: <?= $theme == 'dark' ? '#343a40' : '#fff' ?>;
            border-radius: 50%;
            border: 2px solid #3388ff;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
        }
    </style>
</head>
<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include_once('includes/sidebar.php'); ?>
        
        <!-- Page Content -->
        <div id="content">
            <!-- Top Navigation -->
            <?php include_once('includes/navbar.php'); ?> 
            
            <!-- Dashboard Content -->
            <div class="m-4">
                <h1 class="mt-4">Carte Interactive des Sites</h1>
                
                <!-- Message d'erreur -->
                <div id="map-error" class="alert alert-danger my-4" style="display:none;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span id="error-message"></span>
                    <button id="retry-btn" class="btn btn-sm btn-outline-danger ms-3">Réessayer</button>
                </div>
                
                <!-- Chargement de la carte -->
                <div id="map-loading" class="my-4 shadow">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-3">Chargement de la carte...</p>
                    </div>
                </div>
                
                <!-- Conteneur de la carte -->
                <div id="map-container">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h4 class="mb-0">Carte Interactive des Sites</h4>
                        <div class="search-container-static">
                            <div class="input-group mb-0">
                                <input type="text" id="site-search" class="form-control" placeholder="Rechercher un site...">
                                <button class="btn btn-outline-secondary" type="button" id="search-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                                <button class="btn btn-outline-secondary" type="button" id="clear-search">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div id="search-results" class="list-group" style="display: none;"></div>
                        </div>
                    </div>
                    <div id="map" class="shadow"></div>
                    <!-- Contrôles d'itinéraire -->
                    <div id="route-controls" class="route-controls" style="display: none;">
                        <button id="clear-routes" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash"></i> Effacer itinéraires
                        </button>
                    </div>
                </div>
                
                <!-- Liste des Sites -->
                <div class="card my-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="mb-0">Sites enregistrés</h5>
                            <div>
                                <button id="draw-routes" class="btn btn-sm btn-info me-2" type="button" data-bs-toggle="modal" data-bs-target="#routePlannerModal">
                                    <i class="fas fa-route me-1"></i> Tracer les itinéraires
                                </button>
                                <a href="add_site.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus me-1"></i> Ajouter un site
                                </a>
                            </div>
                        </div>
                        <div id="site-legend" class="d-flex flex-wrap gap-2 mb-3"></div>
                        <ul id="site-list" class="list-group">
                            <!-- Rempli dynamiquement via AJAX/JS -->
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal choix des locaux / itinéraire -->
    <div class="modal fade" id="routePlannerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-route me-2"></i>Planifier un itinéraire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Sélectionnez au moins 2 locaux, puis choisissez le mode de calcul.</p>
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="select-all-sites">Tout cocher</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-all-sites">Tout décocher</button>
                    </div>
                    <div id="route-site-checks" class="row g-2 mb-3"></div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mode</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="route_mode" id="mode_shortest" value="shortest" checked>
                            <label class="form-check-label" for="mode_shortest">Chemin le plus court (ordre optimal)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="route_mode" id="mode_order" value="order">
                            <label class="form-check-label" for="mode_order">Ordre de sélection (dans l’ordre coché)</label>
                        </div>
                    </div>
                    <div id="route-result-info" class="alert alert-info d-none mb-0"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary" id="confirm-draw-route">
                        <i class="fas fa-map-marked-alt me-1"></i> Tracer sur la carte
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
    <!-- Leaflet MarkerCluster -->
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <!-- Leaflet Geocoder -->
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    <!-- Leaflet Routing Machine -->
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    let map;
    let markers = [];
    let markerCluster;
    let allSites = [];
    let routingControls = [];
    let routeLayers = [];
    let currentPositionMarker = null;
    let siteColorMap = {};

    const SITE_COLORS = [
        '#e74c3c', '#3498db', '#2ecc71', '#9b59b6', '#f39c12',
        '#1abc9c', '#e91e63', '#00bcd4', '#8bc34a', '#ff5722',
        '#673ab7', '#009688', '#c0392b', '#2980b9', '#27ae60'
    ];

    function siteColor(index) {
        return SITE_COLORS[index % SITE_COLORS.length];
    }

    function showError(message) {
        document.getElementById('map-loading').style.display = 'none';
        document.getElementById('map-container').style.display = 'none';
        document.getElementById('map-error').style.display = 'block';
        document.getElementById('error-message').textContent = message;
    }

    function initMap() {
        try {
            if (map) {
                map.remove();
                map = null;
            }
            map = L.map('map').setView([5.6919, 12.7289], 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19
            }).addTo(map);
            markerCluster = L.markerClusterGroup();
            map.addLayer(markerCluster);
            locateUser();
            loadSites();
            document.getElementById('map-loading').style.display = 'none';
            document.getElementById('map-container').style.display = 'block';
            setTimeout(function() { map.invalidateSize(); }, 200);
        } catch (error) {
            showError("Erreur lors du chargement de la carte: " + error.message);
        }
    }

    function locateUser() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(function(position) {
            const userLatLng = [position.coords.latitude, position.coords.longitude];
            if (currentPositionMarker) map.removeLayer(currentPositionMarker);
            currentPositionMarker = L.marker(userLatLng, {
                icon: L.divIcon({
                    className: 'custom-icon',
                    html: '<i class="fas fa-user"></i>',
                    iconSize: [30, 30]
                }),
                title: "Votre position"
            }).addTo(map);
        }, function() {});
    }

    function isValidCoord(lat, lng) {
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return false;
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return false;
        if (Math.abs(lat) < 0.0001 && Math.abs(lng) < 0.0001) return false;
        return true;
    }

    function coloredIcon(color, label) {
        return L.divIcon({
            className: '',
            html: '<div style="background:' + color + ';color:#fff;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4);border-radius:50%;width:28px;height:28px;line-height:24px;text-align:center;font-size:11px;font-weight:700;">' +
                (label || '') + '</div>',
            iconSize: [28, 28],
            iconAnchor: [14, 14],
            popupAnchor: [0, -14]
        });
    }

    function loadSites() {
        fetch('api/get_sites.php')
            .then(function(response) {
                if (!response.ok) throw new Error('Erreur HTTP: ' + response.status);
                return response.json();
            })
            .then(function(sites) {
                if (sites && sites.error) throw new Error(sites.error);
                if (!Array.isArray(sites)) throw new Error('Réponse API invalide');

                allSites = sites;
                markers = [];
                siteColorMap = {};
                if (markerCluster) markerCluster.clearLayers();

                const siteList = document.getElementById('site-list');
                const legend = document.getElementById('site-legend');
                const checks = document.getElementById('route-site-checks');
                siteList.innerHTML = '';
                legend.innerHTML = '';
                checks.innerHTML = '';

                if (sites.length === 0) {
                    siteList.innerHTML = '<li class="list-group-item text-muted">Aucun site enregistré.</li>';
                    initSearch();
                    return;
                }

                const bounds = [];
                let colorIdx = 0;

                sites.forEach(function(site) {
                    const lat = parseFloat(site.lat);
                    const lng = parseFloat(site.lng);
                    const hasGps = isValidCoord(lat, lng);
                    const color = siteColor(colorIdx++);
                    siteColorMap[site.id] = color;
                    site._color = color;
                    site._hasGps = hasGps;
                    site._lat = lat;
                    site._lng = lng;

                    legend.innerHTML +=
                        '<span class="badge rounded-pill" style="background:' + color + '">' +
                        escapeHtml(site.name || 'Site') + '</span>';

                    const li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-start flex-wrap gap-2';
                    li.innerHTML =
                        '<div class="d-flex gap-2">' +
                            '<span style="width:14px;height:14px;border-radius:50%;background:' + color + ';margin-top:4px;flex-shrink:0"></span>' +
                            '<div>' +
                                '<strong>' + escapeHtml(site.name || '') + '</strong><br>' +
                                '<small class="text-muted">' + escapeHtml(site.address || '') + '</small>' +
                                (site.responsable ? '<br><small>Resp. : ' + escapeHtml(site.responsable) + '</small>' : '') +
                                (!hasGps ? '<br><span class="badge bg-warning text-dark">GPS invalide / manquant</span>' : '') +
                            '</div>' +
                        '</div>' +
                        '<div class="btn-group btn-group-sm">' +
                            (hasGps
                                ? '<button type="button" class="btn btn-outline-primary btn-focus-site"><i class="fas fa-map-marker-alt"></i></button>' +
                                  '<button type="button" class="btn btn-outline-info btn-route-site"><i class="fas fa-route"></i></button>'
                                : '') +
                            '<a class="btn btn-outline-secondary" href="view_site.php?id=' + encodeURIComponent(site.id) + '"><i class="fas fa-eye"></i></a>' +
                        '</div>';

                    if (hasGps) {
                        li.querySelector('.btn-focus-site').addEventListener('click', function() {
                            focusOnSite(lat, lng);
                        });
                        li.querySelector('.btn-route-site').addEventListener('click', function() {
                            showRouteToSite(lat, lng, site.name || 'Site');
                        });

                        const checkCol = document.createElement('div');
                        checkCol.className = 'col-md-6';
                        checkCol.innerHTML =
                            '<div class="form-check border rounded p-2 h-100">' +
                                '<input class="form-check-input route-site-cb" type="checkbox" value="' + site.id + '" id="rs_' + site.id + '" checked>' +
                                '<label class="form-check-label" for="rs_' + site.id + '">' +
                                    '<span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:' + color + '"></span>' +
                                    escapeHtml(site.name || '') +
                                    '<br><small class="text-muted">' + escapeHtml(site.address || '') + '</small>' +
                                '</label>' +
                            '</div>';
                        checks.appendChild(checkCol);
                    }
                    siteList.appendChild(li);

                    if (!hasGps) return;

                    const marker = L.marker([lat, lng], {
                        title: site.name || 'Site',
                        icon: coloredIcon(color, String(markers.length + 1))
                    });
                    marker.bindPopup(
                        '<strong style="color:' + color + '">' + escapeHtml(site.name || '') + '</strong><br>' +
                        escapeHtml(site.address || '') + '<br>' +
                        (site.responsable ? 'Resp. : ' + escapeHtml(site.responsable) + '<br>' : '') +
                        (site.telephone ? 'Tél. : ' + escapeHtml(site.telephone) + '<br>' : '') +
                        '<a href="view_site.php?id=' + encodeURIComponent(site.id) + '">Voir le site</a>'
                    );
                    marker._siteId = site.id;
                    markerCluster.addLayer(marker);
                    markers.push(marker);
                    bounds.push([lat, lng]);
                });

                if (bounds.length === 1) map.setView(bounds[0], 12);
                else if (bounds.length > 1) map.fitBounds(bounds, { padding: [40, 40] });

                initSearch();
            })
            .catch(function(error) {
                console.error(error);
                showError('Erreur lors du chargement des sites: ' + error.message);
            });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function focusOnSite(lat, lng, zoom) {
        map.setView([lat, lng], zoom || 15);
        markers.forEach(function(marker) {
            const ll = marker.getLatLng();
            if (Math.abs(ll.lat - lat) < 0.00001 && Math.abs(ll.lng - lng) < 0.00001) {
                marker.openPopup();
            }
        });
    }

    function initSearch() {
        const searchInput = document.getElementById('site-search');
        const searchResults = document.getElementById('search-results');
        if (!searchInput || searchInput.dataset.bound === '1') return;
        searchInput.dataset.bound = '1';

        function runSearch(query) {
            query = String(query || '').toLowerCase();
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            const results = allSites.filter(function(site) {
                return String(site.name || '').toLowerCase().includes(query) ||
                    String(site.address || '').toLowerCase().includes(query) ||
                    String(site.responsable || '').toLowerCase().includes(query) ||
                    String(site.telephone || '').includes(query);
            });
            displaySearchResults(results);
        }

        searchInput.addEventListener('input', function() { runSearch(this.value); });
        document.getElementById('search-btn').addEventListener('click', function() {
            runSearch(searchInput.value);
        });
        document.getElementById('clear-search').addEventListener('click', function() {
            searchInput.value = '';
            searchResults.style.display = 'none';
            clearRoutes();
        });
    }

    function displaySearchResults(results) {
        const searchResults = document.getElementById('search-results');
        searchResults.innerHTML = '';
        if (results.length === 0) {
            searchResults.innerHTML = '<div class="list-group-item">Aucun résultat trouvé</div>';
            searchResults.style.display = 'block';
            return;
        }
        results.forEach(function(site, index) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action';
            item.innerHTML =
                '<div class="d-flex justify-content-between">' +
                    '<div><strong style="color:' + (site._color || '#333') + '">' + escapeHtml(site.name) + '</strong><br><small>' + escapeHtml(site.address || '') + '</small></div>' +
                    '<span class="badge" style="background:' + (site._color || '#3388ff') + '">' + (index + 1) + '</span>' +
                '</div>';
            item.addEventListener('click', function() {
                if (site._hasGps) focusOnSite(site._lat, site._lng);
                document.getElementById('site-search').value = site.name;
                searchResults.style.display = 'none';
            });
            searchResults.appendChild(item);
        });
        searchResults.style.display = 'block';
    }

    function clearRoutes() {
        routingControls.forEach(function(control) {
            try { map.removeControl(control); } catch (e) {}
        });
        routingControls = [];
        routeLayers.forEach(function(layer) {
            try { map.removeLayer(layer); } catch (e) {}
        });
        routeLayers = [];
        document.getElementById('route-controls').style.display = 'none';
        const info = document.getElementById('route-result-info');
        if (info) {
            info.classList.add('d-none');
            info.textContent = '';
        }
    }

    function getSelectedSites() {
        const ids = Array.from(document.querySelectorAll('.route-site-cb:checked')).map(function(cb) {
            return parseInt(cb.value, 10);
        });
        return allSites.filter(function(s) {
            return s._hasGps && ids.indexOf(parseInt(s.id, 10)) !== -1;
        });
    }

    function formatKm(meters) {
        return (meters / 1000).toFixed(1) + ' km';
    }

    function formatDuration(seconds) {
        const m = Math.round(seconds / 60);
        if (m < 60) return m + ' min';
        return Math.floor(m / 60) + ' h ' + (m % 60) + ' min';
    }

    function drawOsrmGeometry(coords, color) {
        // OSRM geojson: [lng, lat]
        const latlngs = coords.map(function(c) { return [c[1], c[0]]; });
        const layer = L.polyline(latlngs, { color: color || '#e74c3c', weight: 5, opacity: 0.85 }).addTo(map);
        routeLayers.push(layer);
        return layer;
    }

    function openRoutePlanner() {
        // peuplé déjà dans loadSites
    }

    async function confirmDrawRoute() {
        const selected = getSelectedSites();
        if (selected.length < 2) {
            alert('Sélectionnez au moins 2 locaux avec GPS.');
            return;
        }
        clearRoutes();
        document.getElementById('route-controls').style.display = 'block';

        const mode = (document.querySelector('input[name="route_mode"]:checked') || {}).value || 'shortest';
        const coords = selected.map(function(s) {
            return s._lng.toFixed(6) + ',' + s._lat.toFixed(6);
        }).join(';');

        const info = document.getElementById('route-result-info');
        info.classList.remove('d-none');
        info.textContent = 'Calcul de l’itinéraire en cours…';

        try {
            let url;
            if (mode === 'shortest') {
                url = 'https://router.project-osrm.org/trip/v1/driving/' + coords +
                    '?overview=full&geometries=geojson&source=first&destination=last&roundtrip=false';
            } else {
                url = 'https://router.project-osrm.org/route/v1/driving/' + coords +
                    '?overview=full&geometries=geojson';
            }

            const res = await fetch(url);
            if (!res.ok) throw new Error('OSRM HTTP ' + res.status);
            const data = await res.json();
            if (data.code && data.code !== 'Ok') throw new Error(data.message || data.code);

            let geometry, distance, duration, orderNames;
            if (mode === 'shortest' && data.trips && data.trips[0]) {
                const trip = data.trips[0];
                geometry = trip.geometry;
                distance = trip.distance;
                duration = trip.duration;
                orderNames = (data.waypoints || [])
                    .map(function(wp, inputIndex) { return { wp: wp, inputIndex: inputIndex }; })
                    .sort(function(a, b) { return (a.wp.waypoint_index || 0) - (b.wp.waypoint_index || 0); })
                    .map(function(x) {
                        return selected[x.inputIndex] ? selected[x.inputIndex].name : '?';
                    });
            } else if (data.routes && data.routes[0]) {
                const route = data.routes[0];
                geometry = route.geometry;
                distance = route.distance;
                duration = route.duration;
                orderNames = selected.map(function(s) { return s.name; });
            } else {
                throw new Error('Aucun itinéraire trouvé');
            }

            if (geometry && geometry.coordinates) {
                drawOsrmGeometry(geometry.coordinates, '#e74c3c');
                const layer = routeLayers[routeLayers.length - 1];
                if (layer) map.fitBounds(layer.getBounds().pad(0.2));
            }

            // Marqueurs numérotés dans l'ordre
            (orderNames || []).forEach(function(name, i) {
                const site = selected.find(function(s) { return s.name === name; }) || selected[i];
                if (!site) return;
                const m = L.circleMarker([site._lat, site._lng], {
                    radius: 10,
                    color: '#fff',
                    weight: 2,
                    fillColor: site._color || '#e74c3c',
                    fillOpacity: 1
                }).addTo(map);
                m.bindTooltip((i + 1) + '. ' + (site.name || ''), { permanent: true, direction: 'top', offset: [0, -8] });
                routeLayers.push(m);
            });

            info.className = 'alert alert-success mb-0';
            info.innerHTML =
                '<strong>Itinéraire tracé</strong><br>' +
                'Distance : <b>' + formatKm(distance) + '</b> — Durée approx. : <b>' + formatDuration(duration) + '</b><br>' +
                'Ordre : ' + (orderNames || []).map(function(n, i) { return (i + 1) + '. ' + escapeHtml(n); }).join(' → ');

            const modalEl = document.getElementById('routePlannerModal');
            if (modalEl && window.bootstrap) {
                const inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
            }
        } catch (err) {
            console.error(err);
            // Fallback nearest-neighbor + segments LRM
            info.className = 'alert alert-warning mb-0';
            info.textContent = 'OSRM indisponible (' + err.message + '). Calcul local du chemin le plus court…';
            drawNearestNeighborFallback(selected);
        }
    }

    function haversine(a, b) {
        const R = 6371e3;
        const toRad = Math.PI / 180;
        const dLat = (b._lat - a._lat) * toRad;
        const dLng = (b._lng - a._lng) * toRad;
        const lat1 = a._lat * toRad, lat2 = b._lat * toRad;
        const x = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.sqrt(x));
    }

    function drawNearestNeighborFallback(selected) {
        clearRoutes();
        document.getElementById('route-controls').style.display = 'block';
        const remaining = selected.slice();
        const ordered = [remaining.shift()];
        while (remaining.length) {
            let bestI = 0, bestD = Infinity;
            for (let i = 0; i < remaining.length; i++) {
                const d = haversine(ordered[ordered.length - 1], remaining[i]);
                if (d < bestD) { bestD = d; bestI = i; }
            }
            ordered.push(remaining.splice(bestI, 1)[0]);
        }

        let total = 0;
        for (let i = 0; i < ordered.length - 1; i++) {
            total += haversine(ordered[i], ordered[i + 1]);
            const line = L.polyline(
                [[ordered[i]._lat, ordered[i]._lng], [ordered[i + 1]._lat, ordered[i + 1]._lng]],
                { color: ordered[i]._color || '#3388ff', weight: 4, dashArray: '6 8' }
            ).addTo(map);
            routeLayers.push(line);
            if (typeof L.Routing !== 'undefined') {
                try {
                    const control = L.Routing.control({
                        waypoints: [
                            L.latLng(ordered[i]._lat, ordered[i]._lng),
                            L.latLng(ordered[i + 1]._lat, ordered[i + 1]._lng)
                        ],
                        routeWhileDragging: false,
                        show: false,
                        addWaypoints: false,
                        draggableWaypoints: false,
                        fitSelectedRoutes: false,
                        createMarker: function() { return null; },
                        lineOptions: { styles: [{ color: ordered[i]._color || '#3388ff', opacity: 0.75, weight: 5 }] }
                    }).addTo(map);
                    routingControls.push(control);
                } catch (e) {}
            }
        }

        const group = L.featureGroup(ordered.map(function(s) {
            return L.marker([s._lat, s._lng]);
        }));
        map.fitBounds(group.getBounds().pad(0.2));

        const info = document.getElementById('route-result-info');
        info.className = 'alert alert-success mb-0';
        info.innerHTML =
            '<strong>Chemin le plus court (approx. à vol d’oiseau)</strong><br>' +
            'Distance : <b>' + formatKm(total) + '</b><br>' +
            'Ordre : ' + ordered.map(function(s, i) { return (i + 1) + '. ' + escapeHtml(s.name); }).join(' → ');
    }

    function showRouteToSite(lat, lng, siteName) {
        clearRoutes();
        document.getElementById('route-controls').style.display = 'block';
        const start = currentPositionMarker ? currentPositionMarker.getLatLng() : map.getCenter();
        if (typeof L.Routing === 'undefined') {
            const line = L.polyline([[start.lat, start.lng], [lat, lng]], { color: '#3388ff', weight: 5 }).addTo(map);
            routeLayers.push(line);
            return;
        }
        const control = L.Routing.control({
            waypoints: [start, L.latLng(lat, lng)],
            routeWhileDragging: false,
            showAlternatives: false,
            addWaypoints: false,
            draggableWaypoints: false,
            fitSelectedRoutes: true,
            lineOptions: { styles: [{ color: '#3388ff', opacity: 0.75, weight: 5 }] }
        }).addTo(map);
        routingControls.push(control);
    }

    document.getElementById('retry-btn').addEventListener('click', function() {
        document.getElementById('map-loading').style.display = 'flex';
        document.getElementById('map-error').style.display = 'none';
        initMap();
    });

    document.getElementById('clear-routes').addEventListener('click', clearRoutes);
    document.getElementById('confirm-draw-route').addEventListener('click', confirmDrawRoute);
    document.getElementById('select-all-sites').addEventListener('click', function() {
        document.querySelectorAll('.route-site-cb').forEach(function(cb) { cb.checked = true; });
    });
    document.getElementById('clear-all-sites').addEventListener('click', function() {
        document.querySelectorAll('.route-site-cb').forEach(function(cb) { cb.checked = false; });
    });

    // Ancien bouton : ouvre le modal (data-bs-toggle déjà présent)
    document.getElementById('draw-routes').addEventListener('click', openRoutePlanner);

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof L === 'undefined') {
            showError("Leaflet n'a pas pu charger. Vérifiez votre connexion.");
        } else {
            initMap();
        }
    });
    </script>
</body>
</html>