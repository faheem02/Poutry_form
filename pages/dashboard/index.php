<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();

$page_title = 'Dashboard';

// Stats
$today_sales   = todaySalesTotal();
$today_profit  = todayProfit();

$pending_recovery = $pdo->query("
    SELECT COALESCE(SUM(balance), 0) 
    FROM sales 
    WHERE balance > 0
")->fetchColumn();

$today_orders = $pdo->query("
    SELECT COUNT(*) AS cnt FROM sales WHERE sale_date = CURDATE()
")->fetch()['cnt'] ?? 0;

$low_stock = $pdo->query("
    SELECT COUNT(*) AS cnt FROM stock_ledger sl
    JOIN chicken_types ct ON ct.id = sl.chicken_type_id
    GROUP BY sl.chicken_type_id
    HAVING SUM(CASE WHEN transaction_type IN ('opening','purchase','adjustment') THEN weight_kg ELSE 0 END)
         - SUM(CASE WHEN transaction_type = 'sale' THEN weight_kg ELSE 0 END) < 10
    LIMIT 1
")->fetch()['cnt'] ?? 0;

// Recent sales
$recent_sales = $pdo->query("
    SELECT s.id, s.invoice_no, s.net_total, s.balance, s.sale_date,
           c.name AS customer_name, ct.name AS chicken_type
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    JOIN chicken_types ct ON ct.id = s.chicken_type_id
    ORDER BY s.id DESC LIMIT 10
")->fetchAll();

// Stock summary
$stock_summary = $pdo->query("
    SELECT ct.name,
           COALESCE(SUM(CASE WHEN sl.transaction_type IN ('opening','purchase','adjustment') THEN sl.weight_kg ELSE 0 END), 0)
         - COALESCE(SUM(CASE WHEN sl.transaction_type = 'sale' THEN sl.weight_kg ELSE 0 END), 0) AS available_kg
    FROM stock_ledger sl
    JOIN chicken_types ct ON ct.id = sl.chicken_type_id
    GROUP BY sl.chicken_type_id, ct.name
")->fetchAll();

// Chart data: last 7 days sales
$chart_data = $pdo->query("
    SELECT sale_date, SUM(net_total) AS total
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY sale_date ORDER BY sale_date
")->fetchAll();

$chart_labels = [];
$chart_values = [];
foreach ($chart_data as $row) {
    $chart_labels[] = date('d M', strtotime($row['sale_date']));
    $chart_values[] = (float)$row['total'];
}
$chart_labels_json = json_encode($chart_labels);
$chart_values_json = json_encode($chart_values);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
    <div>
        <a href="<?= BASE_URL ?>/pages/pos/index.php" class="btn btn-primary btn-sm">
            <i class="fas fa-cash-register me-1"></i> New Sale
        </a>
        <a href="<?= BASE_URL ?>/pages/chicken_rates/index.php" class="btn btn-success btn-sm">
            <i class="fas fa-tags me-1"></i> Update Rate
        </a>
    </div>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start-primary dashboard-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Today Sales</div>
                        <div class="h5 mb-0 fw-bold text-gray-800">Rs. <?= money($today_sales) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start-success dashboard-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs fw-bold text-success text-uppercase mb-1">Today Profit</div>
                        <div class="h5 mb-0 fw-bold text-gray-800">Rs. <?= money($today_profit) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-chart-line fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start-info dashboard-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs fw-bold text-info text-uppercase mb-1">Today Orders</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?= $today_orders ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-shopping-bag fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start-warning dashboard-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Recovery</div>
                        <div class="h5 mb-0 fw-bold text-gray-800">Rs. <?= money($pending_recovery) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-area me-1"></i> Sales Overview (Last 7 Days)
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-warehouse me-1"></i> Stock Summary (KG)</div>
            <div class="card-body">
                <?php if (count($stock_summary)): ?>
                    <?php foreach ($stock_summary as $s): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small"><?= htmlspecialchars($s['name']) ?></span>
                            <span class="badge bg-<?= $s['available_kg'] < 10 ? 'danger' : 'success' ?> bg-<?= $s['available_kg'] < 20 ? 'warning' : '' ?>">
                                <?= number_format($s['available_kg'], 2) ?> KG
                            </span>
                        </div>
                        <div class="progress mb-3" style="height:8px;">
                            <div class="progress-bar bg-success" style="width: <?= min(100, $s['available_kg']) ?>%"></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted small mb-0">No stock data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-clock me-1"></i> Recent Sales</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Total</th>
                        <th>Balance</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_sales)): ?>
                        <?php foreach ($recent_sales as $sale): ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>/pages/sales/view.php?id=<?= $sale['id'] ?>"><?= htmlspecialchars($sale['invoice_no']) ?></a></td>
                            <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                            <td><?= htmlspecialchars($sale['chicken_type']) ?></td>
                            <td>Rs. <?= money($sale['net_total']) ?></td>
                            <td><?= $sale['balance'] > 0 ? '<span class="text-danger fw-bold">Rs. ' . money($sale['balance']) . '</span>' : '<span class="text-success">Paid</span>' ?></td>
                            <td><?= date('d M Y', strtotime($sale['sale_date'])) ?></td>
                            <td><a href="<?= BASE_URL ?>/pages/sales/invoice.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-print"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No sales yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= $chart_labels_json ?>,
        datasets: [{
            label: 'Sales (Rs.)',
            data: <?= $chart_values_json ?>,
            backgroundColor: 'rgba(5, 150, 105, 0.2)',
            borderColor: '#059669',
            borderWidth: 2,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'Rs. ' + v.toLocaleString() } }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
