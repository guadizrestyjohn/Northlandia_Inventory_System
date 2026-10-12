<?php
$dashboard = $dashboard_data ?? [];
$trendData = $dashboard['trend'] ?? [];
$orderStatus = $dashboard['orderStatus'] ?? [];
$inventoryStatus = $dashboard['inventoryStatus'] ?? [];
$recentOrders = $dashboard['recentOrders'] ?? [];
$recentTransactions = $dashboard['recentTransactions'] ?? [];
?>

<div class="north-page-header">
    <div>
        <div class="north-kicker">Overview</div>
        <h2>Welcome to Northlandia Inventory System</h2>
        <p class="north-page-description">A clear snapshot of orders, inventory, and recent activity.</p>
    </div>
    <div class="north-header-actions">
        <?php if (\Core\Auth::can('orders.view')): ?>
            <a href="<?= BASE_URL ?>/Orders" class="btn btn-north-primary">
                <i class="fas fa-plus"></i>
                <span>New Order</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="north-stats-grid">
    <?php
    $cards = [
        ['customers.view', 'Customers', 'customers', 'fas fa-users', 'primary', '/Customers'],
        ['orders.view', 'Orders', 'orders', 'fas fa-shopping-cart', 'success', '/Orders'],
        ['inventory.view', 'Inventory Items', 'inventory', 'fas fa-boxes', 'warning', '/Inventory'],
        ['job_orders.view', 'Job Orders', 'jobOrders', 'fas fa-clipboard-list', 'danger', '/JobOrders'],
    ];
    foreach ($cards as [$permission, $label, $key, $icon, $variant, $url]):
        if (!\Core\Auth::can($permission)) continue;
    ?>
        <a href="<?= BASE_URL . $url ?>" class="north-stat-card north-stat-<?= htmlspecialchars($variant) ?>">
            <div class="north-stat-header">
                <span class="north-stat-icon"><i class="<?= htmlspecialchars($icon) ?>"></i></span>
                <span class="north-stat-trend"><i class="fas fa-arrow-right"></i></span>
            </div>
            <div class="north-stat-value"><?= (int) ($counts[$key] ?? 0) ?></div>
            <div class="north-stat-label"><?= htmlspecialchars($label) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<section class="north-dashboard-section" aria-labelledby="dashboard-charts-title">
    <div class="north-section-heading">
        <div>
            <h3 id="dashboard-charts-title">Performance &amp; Status</h3>
            <p>Order activity over the last seven days and current order and inventory status.</p>
        </div>
    </div>
    <div class="row north-dashboard-row">
    <div class="col-xl-6">
        <div class="north-card">
            <div class="north-card-header">
                <h3>Orders Trend</h3>
            </div>
            <div class="north-card-body">
                <?php if (!empty($trendData)): ?>
                    <div class="north-chart-wrap">
                        <canvas id="ordersTrendChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="north-empty-state">No data available for this period.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="north-card">
            <div class="north-card-header">
                <h3>Order Status</h3>
            </div>
            <div class="north-card-body">
                <?php if (!empty($orderStatus)): ?>
                    <div class="north-chart-wrap north-chart-wrap-small north-chart-wrap-donut">
                        <canvas id="orderStatusChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="north-empty-state">No data available for this period.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="north-card">
            <div class="north-card-header">
                <h3>Inventory Status</h3>
            </div>
            <div class="north-card-body">
                <?php if (!empty($inventoryStatus)): ?>
                    <div class="north-chart-wrap north-chart-wrap-small north-chart-wrap-donut">
                        <canvas id="inventoryStatusChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="north-empty-state">No data available for this period.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
</section>

<section class="north-dashboard-section" aria-labelledby="dashboard-quick-actions-title">
    <div class="north-section-heading">
        <div>
            <h3 id="dashboard-quick-actions-title">Quick Actions</h3>
            <p>Go directly to common tasks.</p>
        </div>
    </div>
    <div class="north-card north-quick-actions-card">
        <div class="north-card-body">
            <div class="north-action-list">
                <?php if (\Core\Auth::can('customers.view')): ?><a class="btn btn-north-primary" href="<?= BASE_URL ?>/Customers"><i class="fas fa-users"></i> Customers</a><?php endif; ?>
                <?php if (\Core\Auth::can('orders.view')): ?><a class="btn btn-north-success" href="<?= BASE_URL ?>/Orders"><i class="fas fa-shopping-cart"></i> Orders</a><?php endif; ?>
                <?php if (\Core\Auth::can('stock.in')): ?><a class="btn btn-north-secondary" href="<?= BASE_URL ?>/Stock"><i class="fas fa-arrow-down"></i> Stock In</a><?php endif; ?>
                <?php if (\Core\Auth::can('stock.out')): ?><a class="btn btn-north-warning" href="<?= BASE_URL ?>/Stock"><i class="fas fa-arrow-up"></i> Stock Out</a><?php endif; ?>
                <?php if (\Core\Auth::can('records.view')): ?><a class="btn btn-north-ghost" href="<?= BASE_URL ?>/Records"><i class="fas fa-archive"></i> Records</a><?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="north-dashboard-section" aria-labelledby="dashboard-activity-title">
    <div class="north-section-heading">
        <div>
            <h3 id="dashboard-activity-title">Recent Activity</h3>
            <p>The latest orders and inventory transactions recorded in the system.</p>
        </div>
    </div>
    <div class="row north-dashboard-row">
    <div class="col-xl-8">
        <div class="north-card">
            <div class="north-card-header">
                <h3>Recent Orders</h3>
            </div>
            <div class="north-card-body">
                <div class="table-responsive">
                    <table class="north-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="5" class="text-muted text-center">No recent orders found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <?php
                                    $orderId = (string) ($order['order_id'] ?? '');
                                    $orderNumber = ctype_digit($orderId)
                                        ? 'ORD-' . str_pad($orderId, 4, '0', STR_PAD_LEFT)
                                        : '';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($order['customer_name'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) ($order['product_name'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) ($order['order_date'] ?? '')) ?></td>
                                        <td><span class="north-badge north-badge-<?= htmlspecialchars(strtolower(str_replace(' ', '-', (string) ($order['status'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($order['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="north-card">
            <div class="north-card-header">
                <h3>Recent Inventory Transactions</h3>
            </div>
            <div class="north-card-body">
                <div class="north-feed">
                    <?php if (empty($recentTransactions)): ?>
                        <div class="north-empty-state">No data available for this period.</div>
                    <?php else: ?>
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <div class="north-feed-item">
                                <div class="north-feed-icon north-feed-icon-<?= strtolower(str_replace(' ', '-', (string) ($transaction['transaction_type'] ?? ''))) ?>">
                                    <i class="fas <?= (string) ($transaction['transaction_type'] ?? '') === 'Stock In' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i>
                                </div>
                                <div class="north-feed-copy">
                                    <strong><?= htmlspecialchars((string) ($transaction['item_name'] ?? '')) ?></strong>
                                    <span><?= htmlspecialchars((string) ($transaction['transaction_type'] ?? '')) ?> · <?= htmlspecialchars((string) ($transaction['quantity'] ?? '')) ?></span>
                                    <small><?= htmlspecialchars((string) ($transaction['transaction_date'] ?? '')) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</section>

<script>
window.dashboardData = <?= json_encode($dashboard, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
