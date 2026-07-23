<!-- FA loaded via navbar; fallback -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<!-- Sidebar -->
<nav id="sidebar" style="background-color: #1a1a2e !important;">
    <div class="sidebar-header text-center py-4" style="background-color: #16213e !important;">
        <h4 class="text-white mb-0">StockMaster</h4>
        <small class="text-white-50 d-inline-block mt-1"><span class="badge <?= role_badge_class() ?>"><?= htmlspecialchars(role_label()) ?></span></small>
    </div>

    <?php
    $page = basename($_SERVER['PHP_SELF']);
    $isAdmin = is_admin();
    $isSuper = has_role('superviseur') || $isAdmin; // superviseur + admin
    $isCash = can_cashier_ops();
    $isCashierOnly = is_caissier();
    $isWm = is_warehouse_manager();
    $isSupplier = is_fournisseur();
    $canStock = can_manage_stock();
    $canProducts = can_manage_products();
    $canSuppliers = can_manage_suppliers();
    $canSites = can_manage_sites();
    $canReports = can_view_reports();
    ?>

    <ul class="list-unstyled components ps-0">
        <?php if ($isSupplier): ?>
        <li>
            <a href="supplier_portal.php" class="text-white <?= $page == 'supplier_portal.php' ? 'active' : '' ?>">
                <i class="fas fa-store sidebar-icon"></i> <?= t('supplier_portal') ?>
            </a>
        </li>

        <?php elseif ($isWm): ?>
        <li>
            <a href="warehouse_dashboard.php" class="text-white <?= $page == 'warehouse_dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-warehouse sidebar-icon"></i> <?= t('warehouse_dashboard') ?>
            </a>
        </li>
        <li>
            <a href="stock.php" class="text-white <?= $page == 'stock.php' ? 'active' : '' ?>">
                <i class="fas fa-boxes sidebar-icon"></i> <?= t('stock') ?>
            </a>
        </li>
        <li>
            <a href="site_inventory.php" class="text-white <?= $page == 'site_inventory.php' ? 'active' : '' ?>">
                <i class="fas fa-map-marker-alt sidebar-icon"></i> <?= t('site_inventory') ?>
            </a>
        </li>
        <li>
            <a href="products.php" class="text-white <?= $page == 'products.php' ? 'active' : '' ?>">
                <i class="fas fa-box sidebar-icon"></i> <?= t('products') ?>
            </a>
        </li>

        <?php elseif ($isCashierOnly): ?>
        <li>
            <a href="create_invoice.php" class="text-white <?= $page == 'create_invoice.php' ? 'active' : '' ?>">
                <i class="fas fa-plus-circle sidebar-icon"></i> <?= t('create_invoice') ?>
            </a>
        </li>
        <li>
            <a href="invoices.php" class="text-white <?= $page == 'invoices.php' ? 'active' : '' ?>">
                <i class="fas fa-receipt sidebar-icon"></i> <?= t('invoice_list') ?>
            </a>
        </li>
        <li>
            <a href="products.php" class="text-white <?= $page == 'products.php' ? 'active' : '' ?>">
                <i class="fas fa-box sidebar-icon"></i> <?= t('product_list') ?>
            </a>
        </li>

        <?php else: ?>
            <?php /* Admin + Superviseur */ ?>
            <?php if ($isAdmin): ?>
            <li>
                <a href="index.php" class="text-white <?= $page == 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt sidebar-icon"></i> <?= t('dashboard') ?>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($canProducts): ?>
            <li>
                <a href="#productsSubmenu" data-bs-toggle="collapse" aria-expanded="<?= in_array($page, ['products.php', 'add_product.php', 'categories.php']) ? 'true' : 'false' ?>"
                   class="dropdown-toggle text-white <?= in_array($page, ['products.php', 'add_product.php', 'categories.php']) ? 'active' : '' ?>">
                    <i class="fas fa-box sidebar-icon"></i> <?= t('products') ?>
                </a>
                <ul class="collapse list-unstyled <?= in_array($page, ['products.php', 'add_product.php', 'categories.php']) ? 'show' : '' ?>" id="productsSubmenu" style="background-color: #0f3460 !important;">
                    <li><a href="products.php" class="text-white ps-4 <?= $page == 'products.php' ? 'active-sub' : '' ?>"><i class="fas fa-list sidebar-icon"></i> <?= t('product_list') ?></a></li>
                    <?php if ($isAdmin || has_role('superviseur')): ?>
                    <li><a href="add_product.php" class="text-white ps-4 <?= $page == 'add_product.php' ? 'active-sub' : '' ?>"><i class="fas fa-plus-circle sidebar-icon"></i> <?= t('add_product') ?></a></li>
                    <li><a href="categories.php" class="text-white ps-4 <?= $page == 'categories.php' ? 'active-sub' : '' ?>"><i class="fas fa-tags sidebar-icon"></i> <?= t('categories') ?></a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($canStock): ?>
            <li>
                <a href="stock.php" class="text-white <?= $page == 'stock.php' ? 'active' : '' ?>">
                    <i class="fas fa-warehouse sidebar-icon"></i> <?= t('stock') ?>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($isCash): ?>
            <li>
                <a href="#invoiceSubmenu" data-bs-toggle="collapse" aria-expanded="<?= in_array($page, ['invoices.php', 'create_invoice.php']) ? 'true' : 'false' ?>"
                   class="dropdown-toggle text-white <?= in_array($page, ['invoices.php', 'create_invoice.php']) ? 'active' : '' ?>">
                    <i class="fas fa-receipt sidebar-icon"></i> <?= t('invoicing') ?>
                </a>
                <ul class="collapse list-unstyled <?= in_array($page, ['invoices.php', 'create_invoice.php']) ? 'show' : '' ?>" id="invoiceSubmenu" style="background-color: #0f3460 !important;">
                    <li><a href="invoices.php" class="text-white ps-4 <?= $page == 'invoices.php' ? 'active-sub' : '' ?>"><i class="fas fa-list sidebar-icon"></i> <?= t('invoice_list') ?></a></li>
                    <li><a href="create_invoice.php" class="text-white ps-4 <?= $page == 'create_invoice.php' ? 'active-sub' : '' ?>"><i class="fas fa-plus-circle sidebar-icon"></i> <?= t('create_invoice') ?></a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($canSuppliers): ?>
            <li>
                <a href="suppliers.php" class="text-white <?= $page == 'suppliers.php' ? 'active' : '' ?>">
                    <i class="fas fa-truck sidebar-icon"></i> <?= t('suppliers') ?>
                </a>
            </li>
            <li>
                <a href="supplier_finance.php" class="text-white <?= $page == 'supplier_finance.php' ? 'active' : '' ?>">
                    <i class="fas fa-wallet sidebar-icon"></i> <?= t('supplier_finance') ?>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($canSites): ?>
            <li>
                <a href="#sitesSubmenu" data-bs-toggle="collapse" aria-expanded="<?= in_array($page, ['sites.php', 'add_site.php', 'site_map.php', 'site_inventory.php', 'view_site.php']) ? 'true' : 'false' ?>"
                   class="dropdown-toggle text-white <?= in_array($page, ['sites.php', 'add_site.php', 'site_map.php', 'site_inventory.php', 'view_site.php']) ? 'active' : '' ?>">
                    <i class="fas fa-map-marker-alt sidebar-icon"></i> <?= t('site_management') ?>
                </a>
                <ul class="collapse list-unstyled <?= in_array($page, ['sites.php', 'add_site.php', 'site_map.php', 'site_inventory.php', 'view_site.php']) ? 'show' : '' ?>" id="sitesSubmenu" style="background-color: #0f3460 !important;">
                    <li><a href="sites.php" class="text-white ps-4 <?= $page == 'sites.php' ? 'active-sub' : '' ?>"><i class="fas fa-list sidebar-icon"></i> <?= t('site_list') ?></a></li>
                    <?php if ($isAdmin || has_role('superviseur')): ?>
                    <li><a href="add_site.php" class="text-white ps-4 <?= $page == 'add_site.php' ? 'active-sub' : '' ?>"><i class="fas fa-plus-circle sidebar-icon"></i> <?= t('add_site') ?></a></li>
                    <?php endif; ?>
                    <li><a href="site_map.php" class="text-white ps-4 <?= $page == 'site_map.php' ? 'active-sub' : '' ?>"><i class="fas fa-map sidebar-icon"></i> <?= t('interactive_map') ?></a></li>
                    <li><a href="site_inventory.php" class="text-white ps-4 <?= $page == 'site_inventory.php' ? 'active-sub' : '' ?>"><i class="fas fa-boxes sidebar-icon"></i> <?= t('site_inventory') ?></a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($canReports): ?>
            <li>
                <a href="notifications.php" class="text-white <?= $page == 'notifications.php' ? 'active' : '' ?>">
                    <i class="fas fa-bell sidebar-icon"></i> <?= t('notifications') ?>
                </a>
            </li>
            <li>
                <a href="reports.php" class="text-white <?= $page == 'reports.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar sidebar-icon"></i> <?= t('reports') ?>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <li>
                <a href="users.php" class="text-white <?= $page == 'users.php' ? 'active' : '' ?>">
                    <i class="fas fa-users sidebar-icon"></i> <?= t('users') ?>
                </a>
            </li>
            <?php endif; ?>
        <?php endif; ?>
    </ul>
</nav>

<style>
    #sidebar { min-height: 100vh; width: 250px; transition: all 0.3s; }
    #sidebar.active { margin-left: -250px; }
    .sidebar-header { border-bottom: 1px solid rgba(255,255,255,0.1); }
    #sidebar a { padding: 12px 20px; display: flex; align-items: center; gap: 10px; transition: all 0.3s; text-decoration: none; }
    .sidebar-icon { width: 1.15rem; min-width: 1.15rem; text-align: center; font-size: 0.95rem; flex-shrink: 0; line-height: 1; }
    .active { color: #4cc9f0 !important; font-weight: 600; border-left: 4px solid #4cc9f0; background-color: rgba(76,201,240,0.15) !important; }
    .active-sub { color: #4cc9f0 !important; font-weight: 500; background-color: rgba(76,201,240,0.1) !important; }
    #sidebar a:not(.active):hover { color: #4cc9f0 !important; background-color: rgba(76,201,240,0.08) !important; padding-left: 25px; }
    .dropdown-toggle:after { border-top-color: #fff; float: right; margin-top: 8px; margin-left: auto; }
</style>
<script src="js/spa.js" defer></script>
