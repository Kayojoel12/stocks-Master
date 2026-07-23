<?php
/** Assets icônes partagés — version épinglée (plus fiable que @latest) */
if (!defined('STOCK_ICONS_LOADED')) {
    define('STOCK_ICONS_LOADED', true);
    ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" /><style>
/* Navbar profile + icônes */
.navbar .nav-profile-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    max-width: 220px;
}
.navbar .nav-profile-btn .nav-profile-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 120px;
}
.navbar .dropdown-menu {
    z-index: 1080;
    min-width: 12rem;
}
body[data-bs-theme="dark"] .navbar .nav-profile-btn {
    background: transparent !important;
    border-color: #ffc107 !important;
    color: #ffc107 !important;
}
body[data-bs-theme="dark"] .navbar .nav-profile-btn:hover,
body[data-bs-theme="dark"] .navbar .nav-profile-btn:focus,
body[data-bs-theme="dark"] .navbar .nav-profile-btn.show {
    background: #ffc107 !important;
    color: #1a1a1a !important;
}
body[data-bs-theme="dark"] .navbar .dropdown-menu {
    background: #1e1e1e !important;
    border: 1px solid #ffc107;
}
body[data-bs-theme="dark"] .navbar .dropdown-item {
    color: #f5f5f5 !important;
}
body[data-bs-theme="dark"] .navbar .dropdown-item:hover {
    background: #2a2a2a !important;
    color: #ffc107 !important;
}
.sidebar-icon.fa, .sidebar-icon.fas, .sidebar-icon.far {
    width: 1.1rem;
    text-align: center;
    font-size: 1rem;
}
</style>
    <?php
}
?>
<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-light <?= $theme == 'dark' ? 'bg-dark navbar-dark' : 'bg-light' ?>">
    <div class="container-fluid">
        <button type="button" id="sidebarCollapse" class="btn <?= $theme == 'dark' ? 'btn-outline-warning' : 'btn-dark' ?>" title="Menu" aria-label="Menu">
            <i class="fas fa-bars"></i>
        </button>

        <form class="d-none d-md-flex ms-3" id="globalSearchForm" action="#" method="GET" onsubmit="return false;">
            <div class="input-group">
                <input type="text" class="form-control js-live-search" id="globalSearch" placeholder="<?= t('search') ?>..." name="q" autocomplete="off">
                <button class="btn <?= $theme == 'dark' ? 'btn-outline-warning' : 'btn-primary' ?>" type="button" tabindex="-1" aria-label="Search">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>

        <div class="d-flex align-items-center ms-auto gap-1 flex-wrap justify-content-end">
            <?php
            $notifUnread = 0;
            $showNotif = function_exists('can_notify') ? can_notify() : false;
            if ($showNotif) {
                try {
                    if (isset($db) && function_exists('count_unread_notifications')) {
                        ensure_notifications_table($db);
                        $notifUnread = count_unread_notifications($db);
                    }
                } catch (Exception $e) {
                    $notifUnread = 0;
                }
            }
            $curLang = $_SESSION['lang'] ?? 'fr';
            $redirectUri = $_SERVER['REQUEST_URI'] ?? 'index.php';
            ?>

            <?php if ($showNotif): ?>
            <a href="notifications.php" class="btn position-relative <?= $theme == 'dark' ? 'btn-outline-warning' : 'btn-outline-secondary' ?>" title="<?= t('notifications') ?>">
                <i class="fas fa-bell"></i>
                <?php if ($notifUnread > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $notifUnread > 99 ? '99+' : $notifUnread ?>
                    </span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <button type="button" id="themeToggle" class="btn <?= $theme == 'dark' ? 'btn-outline-warning' : 'btn-outline-dark' ?>"
                onclick="toggleTheme()" title="<?= $theme == 'dark' ? t('light_mode') : t('dark_mode') ?>">
                <i class="fas <?= $theme == 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i>
                <span class="d-none d-md-inline ms-1 small"><?= $theme == 'dark' ? t('light_mode') : t('dark_mode') ?></span>
            </button>

            <div class="btn-group" role="group" aria-label="Language">
                <a href="set_language.php?lang=fr&redirect=<?= urlencode($redirectUri) ?>"
                   class="btn btn-sm <?= $curLang === 'fr' ? 'btn-warning' : ($theme == 'dark' ? 'btn-outline-warning' : 'btn-outline-secondary') ?>">
                    FR
                </a>
                <a href="set_language.php?lang=en&redirect=<?= urlencode($redirectUri) ?>"
                   class="btn btn-sm <?= $curLang === 'en' ? 'btn-warning' : ($theme == 'dark' ? 'btn-outline-warning' : 'btn-outline-secondary') ?>">
                    EN
                </a>
            </div>

            <!-- Profil -->
            <div class="dropdown">
                <button class="btn nav-profile-btn <?= $theme == 'dark' ? 'btn-outline-warning' : 'btn-outline-secondary' ?> dropdown-toggle"
                    type="button"
                    id="userProfileDropdown"
                    data-bs-toggle="dropdown"
                    data-bs-auto-close="true"
                    aria-expanded="false"
                    aria-haspopup="true">
                    <i class="fas fa-user-circle"></i>
                    <span class="d-none d-lg-inline nav-profile-name"><?= htmlspecialchars($_SESSION['nom'] ?? t('user')) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userProfileDropdown">
                    <li class="px-3 py-2">
                        <div class="fw-semibold text-truncate"><?= htmlspecialchars($_SESSION['nom'] ?? '') ?></div>
                        <div class="small text-muted text-truncate"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></div>
                        <span class="badge <?= role_badge_class() ?> mt-1"><?= htmlspecialchars(role_label()) ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="logout.php" data-spa-ignore="1" data-full-reload="1">
                            <i class="fas fa-sign-out-alt me-2"></i><?= t('logout') ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<script>
(function () {
    if (typeof window.toggleTheme !== 'function') {
        window.__themeBusy = false;
        window.toggleTheme = function () {
            if (window.__themeBusy) return;
            window.__themeBusy = true;
            var currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            var newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            fetch('toggle_theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'theme=' + encodeURIComponent(newTheme),
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data && data.ok && window.StockMasterNavigate) {
                    document.documentElement.setAttribute('data-bs-theme', data.theme || newTheme);
                    window.StockMasterNavigate(location.pathname + location.search, false);
                } else {
                    location.reload();
                }
            }).finally(function () { window.__themeBusy = false; });
        };
    }

    function bindSidebarToggle() {
        var btn = document.getElementById('sidebarCollapse');
        if (!btn || btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', function () {
            var sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.toggle('active');
            document.body.classList.toggle('sidebar-collapsed');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindSidebarToggle);
    } else {
        bindSidebarToggle();
    }
})();
</script>
