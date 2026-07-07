<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Cash Book';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

// Cash receipts (payments via cash only – sales are already included via payments)
$receipts = $pdo->prepare("
    SELECT p.payment_date AS date, c.name AS party, p.amount, 
           COALESCE(p.notes, 'Payment') AS description, 'Payment' AS source
    FROM payments p
    JOIN customers c ON c.id = p.customer_id
    WHERE p.payment_method = 'cash' AND p.payment_date BETWEEN ? AND ?
    ORDER BY date
");
$receipts->execute([$from, $to]);
$receipts = $receipts->fetchAll();

// Cash payments (expenses + supplier payments)
$expenses = $pdo->prepare("
    SELECT expense_date AS date, expense_category AS party, amount, description, 'Expense' AS source
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    UNION ALL
    SELECT sp.payment_date AS date, s.name AS party, sp.amount, COALESCE(sp.notes, 'Supplier payment') AS description, 'Supplier Payment' AS source
    FROM supplier_payments sp
    JOIN suppliers s ON s.id = sp.supplier_id
    WHERE sp.payment_method = 'cash' AND sp.payment_date BETWEEN ? AND ?
    ORDER BY date
");
$expenses->execute([$from, $to, $from, $to]);
$expenses = $expenses->fetchAll();

$total_receipts = array_sum(array_column($receipts, 'amount'));
$total_payments = array_sum(array_column($expenses, 'amount'));
$closing_balance = $total_receipts - $total_payments;

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ---------- Print Styles ---------- */
@media print {
    body * {
        visibility: visible !important;
        box-shadow: none !important;
        background: #fff !important;
        color: #000 !important;
    }
    .no-print, .no-print * {
        display: none !important;
    }
    .card {
        border: 1px solid #ccc !important;
        border-radius: 0 !important;
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
    .text-success, .text-danger, .text-primary {
        color: #000 !important;
    }
    .fw-bold {
        font-weight: 700 !important;
    }
    .badge {
        border: 1px solid #000 !important;
        background: #fff !important;
        color: #000 !important;
    }
    .row .card {
        display: block !important;
        margin-bottom: 10px !important;
        border: 1px solid #ddd !important;
    }
    .border-start-success, .border-start-danger, .border-start-primary {
        border-left: 4px solid #000 !important;
    }
    .card-body {
        padding: 10px 15px !important;
    }
    .h4 {
        font-size: 14px !important;
    }
    .small {
        font-size: 9px !important;
    }
    .table-responsive {
        overflow: visible !important;
    }
}

/* ---------- UI refinements ---------- */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.04);
}
.card-header {
    background: transparent;
    border-bottom: 2px solid #f0f2f7;
    font-weight: 700;
    color: #2d3748;
}
.card-body {
    padding: 20px 24px;
}
.table th {
    font-weight: 700;
    color: #4a5568;
    border-top: none;
}
.table td {
    vertical-align: middle;
}
.border-start-success { border-left: 4px solid #1cc88a; }
.border-start-danger { border-left: 4px solid #e74a3b; }
.border-start-primary { border-left: 4px solid #4e73df; }
.border-start-info { border-left: 4px solid #36b9cc; }
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-money-bill-wave me-2" style="color:#4e73df;"></i> Cash Book</h1>
    <div class="no-print">
        <button class="btn btn-outline-success btn-sm me-1" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print
        </button>
    </div>
</div>

<!-- Filter Card -->
<div class="card mb-4 border-start-info no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-bold">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($from) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small fw-bold">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($to) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i> Filter</button>
            </div>
            <?php if ($from !== date('Y-m-01') || $to !== date('Y-m-d')): ?>
            <div class="col-auto">
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4 no-print">
    <div class="col-md-4">
        <div class="card border-start-success h-100">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-success text-uppercase">Total Receipts</div>
                <div class="h4 mb-0 fw-bold text-success">Rs. <?= money($total_receipts) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-start-danger h-100">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-danger text-uppercase">Total Payments</div>
                <div class="h4 mb-0 fw-bold text-danger">Rs. <?= money($total_payments) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-start-primary h-100">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-primary text-uppercase">Closing Balance</div>
                <div class="h4 mb-0 fw-bold text-primary">Rs. <?= money($closing_balance) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Receipts Table -->
<div class="card mb-4 border-start-success">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-arrow-down me-1" style="color:#1cc88a;"></i> Receipts (Cash In)</span>
        <?php if ($from || $to): ?>
        <span class="badge bg-secondary"><?= date('d M Y', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Party</th>
                        <th>Description</th>
                        <th class="text-end">Amount (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($receipts)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No cash receipts found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($receipts as $r): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($r['date'])) ?></td>
                        <td><?= htmlspecialchars($r['party']) ?></td>
                        <td><?= htmlspecialchars($r['description']) ?> <span class="badge bg-success"><?= $r['source'] ?></span></td>
                        <td class="text-end fw-bold text-success">Rs. <?= money($r['amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end">Total Receipts</th>
                        <th class="text-end fw-bold text-success">Rs. <?= money($total_receipts) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Payments Table -->
<div class="card border-start-danger">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-arrow-up me-1" style="color:#e74a3b;"></i> Payments (Cash Out)</span>
        <?php if ($from || $to): ?>
        <span class="badge bg-secondary"><?= date('d M Y', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th class="text-end">Amount (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No cash payments found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($expenses as $e): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($e['date'])) ?></td>
                        <td><?= ucfirst(htmlspecialchars($e['party'])) ?></td>
                        <td><?= htmlspecialchars($e['description']) ?> <span class="badge bg-secondary"><?= $e['source'] ?></span></td>
                        <td class="text-end fw-bold text-danger">Rs. <?= money($e['amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end">Total Payments</th>
                        <th class="text-end fw-bold text-danger">Rs. <?= money($total_payments) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>