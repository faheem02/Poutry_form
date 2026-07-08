<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Sales';

// Date filter
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT s.*, c.name AS customer_name, ct.name AS chicken_type, u.username AS created_by_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    JOIN chicken_types ct ON ct.id = s.chicken_type_id
    LEFT JOIN users u ON u.id = s.created_by
    WHERE s.sale_date BETWEEN ? AND ?
    ORDER BY s.id DESC
");
$stmt->execute([$from, $to]);
$sales = $stmt->fetchAll();

$total_net = array_sum(array_column($sales, 'net_total'));
$count     = count($sales);

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
    .badge {
        border: 1px solid #000 !important;
        background: #fff !important;
        color: #000 !important;
    }
    .table-responsive {
        overflow: visible !important;
    }
    .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate {
        display: none !important;
    }
}
</style>

<!-- Page Header with Action Button -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-chart-line text-primary me-2"></i>Sales
        </h1>
        <nav aria-label="breadcrumb" class="no-print">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/dashboard/index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Sales</li>
            </ol>
        </nav>
    </div>
    <a href="<?= BASE_URL ?>/pages/pos/index.php" class="btn btn-primary btn-sm shadow-sm no-print">
        <i class="fas fa-plus me-1"></i> New Sale
    </a>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4 no-print">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-start-primary shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <i class="fas fa-coins text-primary fa-lg"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="small text-gray-500">Total Sales</div>
                        <div class="h5 mb-0 fw-bold">Rs. <?= money($total_net) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card border-start-info shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-info bg-opacity-10 rounded p-3">
                            <i class="fas fa-receipt text-info fa-lg"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="small text-gray-500">Transactions</div>
                        <div class="h5 mb-0 fw-bold"><?= $count ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card border-start-success shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <i class="fas fa-check-circle text-success fa-lg"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="small text-gray-500">Fully Paid</div>
                        <div class="h5 mb-0 fw-bold">
                            <?php
                            $paid_count = array_filter($sales, fn($s) => $s['balance'] == 0);
                            echo count($paid_count);
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card shadow-sm mb-4 border-0 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-sm-auto">
                <label class="form-label small fw-semibold text-muted"><i class="far fa-calendar-alt me-1"></i>From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= $from ?>">
            </div>
            <div class="col-12 col-sm-auto">
                <label class="form-label small fw-semibold text-muted"><i class="far fa-calendar-alt me-1"></i>To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= $to ?>">
            </div>
            <div class="col-12 col-sm-auto">
                <button type="submit" class="btn btn-primary btn-sm w-100 w-sm-auto">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
            </div>
            <div class="col-12 col-sm-auto">
                <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm w-100 w-sm-auto">
                    <i class="fas fa-undo-alt me-1"></i> Reset
                </a>
            </div>
            <div class="col-12 col-sm-auto ms-sm-auto">
                <span class="badge bg-primary fs-6">Total: Rs. <?= money($total_net) ?></span>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-transparent py-3 d-flex flex-wrap align-items-center justify-content-between">
        <h6 class="m-0 fw-bold text-primary">
            <i class="fas fa-list-ul me-2"></i>Sales Records
        </h6>
        <div>
            <button class="btn btn-sm btn-outline-success me-1 no-print" onclick="window.print()">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </button>
            
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Chicken Type</th>
                        <th>Weight (Man)</th>
                        <th>Weight (kg)</th>
                        <th>Rate (Rs.)</th>
                        <th>Total (Rs.)</th>
                        <th>Paid (Rs.)</th>
                        <th>Balance</th>
                        <th>Payment</th>
                        <th class="text-center no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $s): ?>
                    <tr>
                        <td class="fw-bold text-primary"><?= htmlspecialchars($s['invoice_no']) ?></td>
                        <td><?= date('d M Y', strtotime($s['sale_date'])) ?></td>
                        <td><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?></td>
                        <td><span class="badge bg-info bg-opacity-10 text-info"><?= htmlspecialchars($s['chicken_type']) ?></span></td>
                        <td class="fw-semibold text-center"><?= number_format($s['weight'] / 40, 3) ?></td>
                        <td><?= number_format($s['weight'], 3) ?></td>
                        <td><?= money($s['rate_per_kg']) ?></td>
                        <td class="fw-semibold"><?= money($s['net_total']) ?></td>
                        <td><?= money($s['paid_amount']) ?></td>
                        <td>
                            <?php if ($s['balance'] > 0): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger fw-bold">Rs. <?= money($s['balance']) ?></span>
                            <?php else: ?>
                                <span class="badge bg-success bg-opacity-10 text-success"><i class="fas fa-check-circle me-1"></i>Paid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $method = $s['payment_method'];
                            $badgeClass = match($method) {
                                'cash' => 'bg-success',
                                'credit' => 'bg-warning',
                                'bank' => 'bg-info',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= ucfirst($method) ?></span>
                        </td>
                        <td class="text-center no-print">
                            <div class="d-flex justify-content-center gap-1">
                                <a href="view.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Edit Sale">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="invoice.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank" data-bs-toggle="tooltip" title="Print Invoice">
                                    <i class="fas fa-print"></i>
                                </a>
                                <a href="delete.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete" data-bs-toggle="tooltip" title="Delete Sale" data-text="Delete sale <?= htmlspecialchars($s['invoice_no']) ?>? This will also remove its stock and payment entries.">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JavaScript Enhancements -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enable tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Export CSV (basic implementation)
    // document.getElementById('exportCsv')?.addEventListener('click', function() {
    //     let table = document.querySelector('.datatable');
    //     let rows = table.querySelectorAll('tr');
    //     let csv = [];
    //     for (let row of rows) {
    //         let cols = row.querySelectorAll('th, td');
    //         let rowData = [];
    //         for (let col of cols) {
    //             let text = col.innerText.trim();
    //             // Remove icons or extra symbols if needed
    //             rowData.push('"' + text.replace(/"/g, '""') + '"');
    //         }
    //         csv.push(rowData.join(','));
    //     }
    //     let blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    //     let link = document.createElement('a');
    //     link.href = URL.createObjectURL(blob);
    //     link.download = 'sales_report.csv';
    //     link.click();
    // });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>