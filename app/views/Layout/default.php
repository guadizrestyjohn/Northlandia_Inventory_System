<?php

$title = $title ?? 'Dashboard';
$user = \Core\Auth::user();
$userName = $user['full_name'] ?? 'Northlandia User';
$userRole = $user['role'] ?? 'User';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> | Northlandia</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/northlandia-theme.css?v=<?= (int) filemtime(dirname(__DIR__, 3) . '/public/css/northlandia-theme.css') ?>">
</head>

<body class="northlandia-app">
    <div class="north-shell">
        <aside class="north-sidebar">
            <div class="north-brand">
                <div class="north-brand-icon">
                    <i class="fas fa-warehouse"></i>
                </div>
                <div class="north-brand-copy">
                    <span class="north-brand-title">NORTHLANDIA</span>
                    <span class="north-brand-subtitle">Inventory System</span>
                </div>
            </div>

            <nav class="north-nav" aria-label="Main navigation">
                <div class="north-nav-section">
                    <?php if (\Core\Auth::can('dashboard.view')): ?>
                        <a href="<?= BASE_URL ?>/" class="north-nav-link <?= $title === 'Dashboard' ? 'active' : '' ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (\Core\Auth::can('customers.view') || \Core\Auth::can('orders.view') || \Core\Auth::can('job_orders.view')): ?>
                    <div class="north-nav-section">
                        <div class="north-nav-label">Sales &amp; Operations</div>
                        <?php if (\Core\Auth::can('customers.view')): ?>
                            <a href="<?= BASE_URL ?>/Customers" class="north-nav-link <?= $title === 'Customers' ? 'active' : '' ?>">
                                <i class="fas fa-users"></i>
                                <span>Customers</span>
                            </a>
                        <?php endif; ?>
                        <?php if (\Core\Auth::can('orders.view')): ?>
                            <a href="<?= BASE_URL ?>/Orders" class="north-nav-link <?= $title === 'Orders' ? 'active' : '' ?>">
                                <i class="fas fa-shopping-cart"></i>
                                <span>Orders</span>
                            </a>
                        <?php endif; ?>
                        <?php if (\Core\Auth::can('job_orders.view')): ?>
                            <a href="<?= BASE_URL ?>/JobOrders" class="north-nav-link <?= $title === 'Job Orders' ? 'active' : '' ?>">
                                <i class="fas fa-clipboard-list"></i>
                                <span>Job Orders</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (\Core\Auth::can('inventory.view') || \Core\Auth::can('stock.view')): ?>
                    <div class="north-nav-section">
                        <div class="north-nav-label">Inventory</div>
                        <?php if (\Core\Auth::can('inventory.view')): ?>
                            <a href="<?= BASE_URL ?>/Inventory" class="north-nav-link <?= $title === 'Inventory' ? 'active' : '' ?>">
                                <i class="fas fa-boxes"></i>
                                <span>Inventory</span>
                            </a>
                        <?php endif; ?>
                        <?php if (\Core\Auth::can('stock.view')): ?>
                            <a href="<?= BASE_URL ?>/Stock" class="north-nav-link <?= $title === 'Stock' ? 'active' : '' ?>">
                                <i class="fas fa-exchange-alt"></i>
                                <span>Stock</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (\Core\Auth::canAccessRecords() || \Core\Auth::can('reports.view')): ?>
                    <div class="north-nav-section">
                        <div class="north-nav-label">Records &amp; Reports</div>
                        <?php if (\Core\Auth::canAccessRecords()): ?>
                            <a href="<?= BASE_URL ?>/Records" class="north-nav-link <?= $title === 'Records' && ($_GET['type'] ?? '') !== 'Receipt' ? 'active' : '' ?>">
                                <i class="fas fa-archive"></i>
                                <span>Records</span>
                            </a>
                        <?php endif; ?>
                        <?php if (\Core\Auth::can('reports.view')): ?>
                            <a href="<?= BASE_URL ?>/Reports" class="north-nav-link <?= $title === 'Reports' ? 'active' : '' ?>">
                                <i class="fas fa-chart-bar"></i>
                                <span>Reports</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (\Core\Auth::can('users.manage')): ?>
                    <div class="north-nav-section">
                        <div class="north-nav-label">System</div>
                        <a href="<?= BASE_URL ?>/Users" class="north-nav-link <?= $title === 'User Management' ? 'active' : '' ?>">
                            <i class="fas fa-user-cog"></i>
                            <span>User Management</span>
                        </a>
                    </div>
                <?php endif; ?>
            </nav>
        </aside>

        <div class="north-main">
            <header class="north-topbar">
                <div class="north-topbar-left">
                    <button class="north-sidebar-toggle" type="button" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="north-page-tag">
                        <i class="fas fa-warehouse"></i>
                        <span>Inventory Control</span>
                    </div>
                </div>

                <div class="north-topbar-right">
                    <div class="north-user-pill">
                        <span class="north-user-avatar"><?= htmlspecialchars(substr($userName, 0, 1)) ?></span>
                        <div>
                            <div class="north-user-name"><?= htmlspecialchars($userName) ?></div>
                            <div class="north-user-role"><?= htmlspecialchars($userRole) ?></div>
                        </div>
                    </div>

                    <form action="<?= BASE_URL ?>/Auth/logout" method="POST" class="north-logout-form">
                        <button type="submit" class="btn btn-north-outline">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </header>

            <main class="north-content">
                <div class="north-content-inner">
                    <?= $content ?>
                </div>
            </main>

            <footer class="north-footer">
                <span>Northlandia Inventory System</span>
            </footer>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?= BASE_URL ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/plugins/datatables/jquery.dataTables.min.js"></script>
    <script src="<?= BASE_URL ?>/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
    <script src="<?= BASE_URL ?>/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="<?= BASE_URL ?>/js/app.js?v=<?= (int) filemtime(dirname(__DIR__, 3) . '/public/js/app.js') ?>"></script>

    <?php
    $moduleScripts = [
        'Dashboard' => ['Dashboard.js'],
        'Customers' => ['Customers.js'],
        'Orders' => ['Orders.js'],
        'Job Orders' => ['JobOrders.js'],
        'Inventory' => ['Inventory.js', 'Stock.js'],
        'Stock' => ['Stock.js'],
        'Records' => ['Records.js'],
        'Reports' => ['Reports.js'],
        'User Management' => ['Users.js'],
    ];
    if (isset($moduleScripts[$title])):
        foreach ($moduleScripts[$title] as $moduleScript):
    ?>
        <script src="<?= BASE_URL ?>/js/<?= htmlspecialchars($moduleScript, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int) filemtime(dirname(__DIR__, 3) . '/public/js/' . $moduleScript) ?>"></script>
    <?php
        endforeach;
    endif;
    ?>
</body>
</html>
