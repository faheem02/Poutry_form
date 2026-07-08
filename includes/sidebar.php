<?php
$expCust = isSectionActive('customers') ? 'show' : '';
$expSupp = isSectionActive('suppliers') ? 'show' : '';
$expFin  = isSectionActive('finance') ? 'show' : '';
$expReports = isSectionActive('reports') ? 'show' : '';
$expStock = isSectionActive('stock') ? 'show' : '';
$collapsed = function($exp) { return $exp ? '' : 'collapsed'; };
$expanded  = function($exp) { return $exp ? 'true' : 'false'; };
?>
    <!-- Sidebar Backdrop (mobile) -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <!-- Sidebar -->
    <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar"><div class="sidebar-inner">
        <div class="sidebar-close d-md-none" style="display:none;">
            <button type="button" class="btn-close" id="sidebarCloseBtn" aria-label="Close"></button>
        </div>
        <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= BASE_URL ?>/pages/dashboard/index.php">
            <div class="sidebar-brand-icon">
                <i class="fas fa-drumstick-bite"></i>
            </div>
            <div class="sidebar-brand-text mx-2">ISMAIL'S POULTRY</div>
        </a>

        <hr class="sidebar-divider my-0">

        <li class="nav-item <?= navActive('index.php') && strpos($_SERVER['REQUEST_URI'], '/dashboard/') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/pages/dashboard/index.php">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <hr class="sidebar-divider">
        <div class="sidebar-heading">POS</div>

        <li class="nav-item <?= navActiveDir('pos') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/pages/pos/index.php">
                <i class="fas fa-fw fa-cash-register"></i>
                <span>POS Sale</span>
            </a>
        </li>

        <hr class="sidebar-divider">
        <div class="sidebar-heading">Management</div>

        <li class="nav-item <?= navActiveDir('chicken_types') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/pages/chicken_types/index.php">
                <i class="fas fa-fw fa-drumstick-bite"></i>
                <span>Chicken Types</span>
            </a>
        </li>
        <li class="nav-item <?= navActiveDir('chicken_rates') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/pages/chicken_rates/index.php">
                <i class="fas fa-fw fa-tag"></i>
                <span>Chicken Rates</span>
            </a>
        </li>

        <!-- Customers Accordion -->
        <li class="nav-item">
            <a class="nav-link <?= $collapsed($expCust) ?>" href="#" data-bs-toggle="collapse" data-bs-target="#collapseCustomers" aria-expanded="<?= $expanded($expCust) ?>">
                <i class="fas fa-fw fa-users"></i>
                <span>Customers</span>
            </a>
            <div id="collapseCustomers" class="collapse <?= $expCust ?>">
                <div class="bg-white py-2 collapse-inner rounded">
                    <a style="color:#000;" class="collapse-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/customers/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/customers/index.php">View Customers</a>
                    <a style="color:#000;" class="collapse-item <?= basename($_SERVER['PHP_SELF']) === 'ledger.php' && strpos($_SERVER['REQUEST_URI'], '/customers/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/customers/ledger.php">Customer Ledger</a>
                    <a style="color:#000;" class="collapse-item <?= basename($_SERVER['PHP_SELF']) === 'create.php' && strpos($_SERVER['REQUEST_URI'], '/customers/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/customers/create.php">Create Customer</a>
                    <a style="color:#000;" class="collapse-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/payments/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/payments/index.php">Payments</a>
                </div>
            </div>
        </li>

        <!-- Suppliers Accordion -->
        <li class="nav-item">
            <a class="nav-link <?= $collapsed($expSupp) ?>" href="#" data-bs-toggle="collapse" data-bs-target="#collapseSuppliers" aria-expanded="<?= $expanded($expSupp) ?>">
                <i class="fas fa-fw fa-truck"></i>
                <span>Suppliers</span>
            </a>
            <div id="collapseSuppliers" class="collapse <?= $expSupp ?>">
                <div class="bg-white py-2 collapse-inner rounded">
                    <a style="color:#000;" class="collapse-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/suppliers/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/suppliers/index.php">View Suppliers</a>
                    <a style="color:#000;" class="collapse-item <?= basename($_SERVER['PHP_SELF']) === 'ledger.php' && strpos($_SERVER['REQUEST_URI'], '/suppliers/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/suppliers/ledger.php">Supplier Ledger</a>
                    <a style="color:#000;" class="collapse-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/supplier_payments/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/supplier_payments/index.php">Supplier Payments</a>
                    <a  style="color:#000;" class="collapse-item <?= basename($_SERVER['PHP_SELF']) === 'create.php' && strpos($_SERVER['REQUEST_URI'], '/suppliers/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/suppliers/create.php">Create Supplier</a>
                </div>
            </div>
        </li>

        <li class="nav-item <?= navActiveDir('purchases') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/pages/purchases/index.php">
                <i class="fas fa-fw fa-shopping-cart"></i>
                <span>Purchases</span>
            </a>
        </li>
        <li class="nav-item <?= navActiveDir('sales') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/pages/sales/index.php">
                <i class="fas fa-fw fa-file-invoice"></i>
                <span>Sales</span>
            </a>
        </li>

        <li class="nav-item <?= navActiveDir('expenses') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/pages/expenses/index.php">
                <i class="fas fa-fw fa-coins"></i>
                <span>Expenses</span>
            </a>
        </li>
        <!-- Stock Accordion -->
        <li class="nav-item">
            <a class="nav-link <?= $collapsed($expStock) ?>" href="#" data-bs-toggle="collapse" data-bs-target="#collapseStock" aria-expanded="<?= $expanded($expStock) ?>">
                <i class="fas fa-fw fa-warehouse"></i>
                <span>Stock</span>
            </a>
            <div id="collapseStock" class="collapse <?= $expStock ?>">
                <div class="bg-white py-2 collapse-inner rounded">
                    <a style="color:#000;" class="collapse-item <?= basename($_SERVER['PHP_SELF']) === 'summary.php' && strpos($_SERVER['REQUEST_URI'], '/stock/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/stock/summary.php">Stock Summary</a>
                    <a style="color:#000;" class="collapse-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/stock/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/stock/index.php">Stock Ledger</a>
                    <a style="color:#000;" class="collapse-item <?= basename($_SERVER['PHP_SELF']) === 'manage.php' && strpos($_SERVER['REQUEST_URI'], '/stock/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/stock/manage.php">Management</a>
                    <a style="color:#000;" class="collapse-item <?= basename($_SERVER['PHP_SELF']) === 'adjustment_history.php' && strpos($_SERVER['REQUEST_URI'], '/stock/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/stock/adjustment_history.php">Adjustment History</a>
                </div>
            </div>
        </li>

        <hr class="sidebar-divider">
        <div class="sidebar-heading">Finance</div>

        <!-- Finance Accordion -->
        <li class="nav-item">
            <a class="nav-link <?= $collapsed($expFin) ?>" href="#" data-bs-toggle="collapse" data-bs-target="#collapseFinance" aria-expanded="<?= $expanded($expFin) ?>">
                <i class="fas fa-fw fa-book"></i>
                <span>Cash & Bank</span>
            </a>
            <div id="collapseFinance" class="collapse <?= $expFin ?>">
                <div class="bg-white py-2 collapse-inner rounded">
                    <a style="color:#000;" class="collapse-item <?= strpos($_SERVER['REQUEST_URI'], '/cash_book') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/reports/cash_book.php">Cash Book</a>
                    <a style="color:#000;" class="collapse-item <?= strpos($_SERVER['REQUEST_URI'], '/bank_book') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/reports/bank_book.php">Bank Book</a>
                </div>
            </div>
        </li>

        <hr class="sidebar-divider">

        <hr class="sidebar-divider d-none d-md-block">

        <div class="d-none d-md-block" style="text-align: center; margin-top: auto; padding: 1rem 0; width: 100%;">
            <button class="rounded-circle border-0" id="sidebarToggle" style="display: inline-block; margin: 0 auto;"></button>
        </div>
        </div><!-- .sidebar-inner -->
    </ul>
    <!-- End Sidebar -->