<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = getDB();
$page_title = 'Daily Sales Report';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT s.sale_date,
           COUNT(*) AS orders,
           SUM(s.net_total) AS total,
           SUM(s.discount) AS discount_total,
           SUM(s.paid_amount) AS paid,
           SUM(s.balance) AS pending
    FROM sales s
    WHERE s.sale_date BETWEEN ? AND ?
    GROUP BY s.sale_date ORDER BY s.sale_date DESC
");
$stmt->execute([$from, $to]);
$daily = $stmt->fetchAll();

$grand = [
    'orders' => array_sum(array_column($daily, 'orders')),
    'total'  => array_sum(array_column($daily, 'total')),
    'paid'   => array_sum(array_column($daily, 'paid')),
    'pending'=> array_sum(array_column($daily, 'pending')),
];

$chart_labels = [];
$chart_values = [];
foreach (array_reverse($daily) as $d) {
    $chart_labels[] = date('d M', strtotime($d['sale_date']));
    $chart_values[] = (float)$d['total'];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
@media print {
    .no-print, .no-print * {
        display: none !important;
    }
    .card {
        border: 1px solid #ccc !important;
        box-shadow: none !important;
    }
    .card-header {
        background: #f8f9fa !important;
        border-bottom: 2px solid #333 !important;
    }
    .table {
        font-size: 11px !important;
    }
    .table thead th {
        background: #e9ecef !important;
        color: #000 !important;
        border-bottom: 2px solid #333 !important;
    }
    .table tbody tr {
        page-break-inside: avoid;
    }
    .table-responsive {
        overflow: visible !important;
    }
    .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate {
        display: none !important;
    }
}
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-bar me-1"></i> Daily Sales Report</h1>
</div>

<div class="card mb-4 border-start-info no-print">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= $from ?>"></div>
            <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= $to ?>"></div>
            <div class="col-auto"><button class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i> View</button></div>
        </form>
    </div>
</div>

<div class="row mb-4 no-print">
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-start-primary dashboard-card">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-primary text-uppercase">Total Orders</div>
                <div class="h4 mb-0 fw-bold"><?= $grand['orders'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-start-success dashboard-card">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-success text-uppercase">Total Revenue</div>
                <div class="h4 mb-0 fw-bold">Rs. <?= money($grand['total']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-start-info dashboard-card">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-info text-uppercase">Total Paid</div>
                <div class="h4 mb-0 fw-bold">Rs. <?= money($grand['paid']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-start-warning dashboard-card">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-warning text-uppercase">Pending</div>
                <div class="h4 mb-0 fw-bold">Rs. <?= money($grand['pending']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 no-print">
    <div class="col-xl-6 mb-4">
        <div class="card border-start-primary">
            <div class="card-header">Sales Trend</div>
            <div class="card-body"><canvas id="salesChart" height="200"></canvas></div>
        </div>
    </div>
    <div class="col-xl-6 mb-4">
        <div class="card border-start-success">
            <div class="card-header">Summary by Chicken Type</div>
            <div class="card-body">
                <?php
                $stmt = $pdo->prepare("
                    SELECT ct.name, COUNT(*) AS cnt, SUM(s.net_total) AS total
                    FROM sales s JOIN chicken_types ct ON ct.id = s.chicken_type_id
                    WHERE s.sale_date BETWEEN ? AND ?
                    GROUP BY ct.name ORDER BY total DESC
                ");
                $stmt->execute([$from, $to]);
                $type_summary = $stmt->fetchAll();
                ?>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Type</th><th class="text-end">Orders</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($type_summary as $ts): ?>
                        <tr><td class="fw-bold"><?= htmlspecialchars($ts['name']) ?></td><td class="text-end"><?= $ts['cnt'] ?></td><td class="text-end">Rs. <?= money($ts['total']) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card border-start-primary">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 small">
                <thead class="table-light">
                    <tr><th>Date</th><th class="text-end">Orders</th><th class="text-end">Total</th><th class="text-end">Discount</th><th class="text-end">Paid</th><th class="text-end">Pending</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($daily as $d): ?>
                    <tr>
                        <td class="fw-bold"><?= date('d M Y', strtotime($d['sale_date'])) ?></td>
                        <td class="text-end"><?= $d['orders'] ?></td>
                        <td class="text-end">Rs. <?= money($d['total']) ?></td>
                        <td class="text-end">Rs. <?= money($d['discount_total']) ?></td>
                        <td class="text-end">Rs. <?= money($d['paid']) ?></td>
                        <td class="text-end"><?= $d['pending'] > 0 ? '<span class="text-danger">Rs. ' . money($d['pending']) . '</span>' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Sales (Rs.)',
            data: <?= json_encode($chart_values) ?>,
            borderColor: '#059669', backgroundColor: 'rgba(5,150,105,0.1)', tension: 0.3, fill: true
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
