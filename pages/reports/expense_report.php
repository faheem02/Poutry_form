<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = getDB();
$page_title = 'Expense Report';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$categories = ['labour', 'transport', 'electricity', 'misc'];

$totals = [];
foreach ($categories as $cat) {
    $s = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS t FROM expenses WHERE expense_category = ? AND expense_date BETWEEN ? AND ?");
    $s->execute([$cat, $from, $to]);
    $totals[$cat] = (float)$s->fetch()['t'];
}
$grand = array_sum($totals);

$stmt = $pdo->prepare("
    SELECT e.*, u.username AS created_by_name
    FROM expenses e
    LEFT JOIN users u ON u.id = e.created_by
    WHERE e.expense_date BETWEEN ? AND ?
    ORDER BY e.expense_date DESC, e.id DESC
");
$stmt->execute([$from, $to]);
$expenses = $stmt->fetchAll();

$chart_labels = json_encode($categories);
$chart_values = json_encode(array_values($totals));

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
}
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-coins me-1"></i> Expense Report</h1>
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
    <?php foreach ($totals as $cat => $total): ?>
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-start-warning dashboard-card">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-warning text-uppercase"><?= ucfirst($cat) ?></div>
                <div class="h4 mb-0 fw-bold">Rs. <?= money($total) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-start-danger dashboard-card">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-danger text-uppercase">Total</div>
                <div class="h4 mb-0 fw-bold">Rs. <?= money($grand) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-xl-6 mb-4">
        <div class="card border-start-warning">
            <div class="card-header">Expense Breakdown</div>
            <div class="card-body"><canvas id="expenseChart" height="250"></canvas></div>
        </div>
    </div>
    <div class="col-xl-6 mb-4">
        <div class="card border-start-primary">
            <div class="card-header">Expense List</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr><th>Date</th><th>Category</th><th class="text-end">Amount</th><th>Description</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $e): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($e['expense_date'])) ?></td>
                                <td><span class="badge bg-warning text-dark"><?= ucfirst($e['expense_category']) ?></span></td>
                                <td class="text-end text-danger fw-bold">Rs. <?= money($e['amount']) ?></td>
                                <td><?= htmlspecialchars($e['description'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('expenseChart'), {
    type: 'doughnut',
    data: {
        labels: <?= $chart_labels ?>,
        datasets: [{
            data: <?= $chart_values ?>,
            backgroundColor: ['#f6c23e', '#36b9cc', '#1cc88a', '#e74a3b']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
