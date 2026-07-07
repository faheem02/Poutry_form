<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Bank Book';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

// Bank receipts (payments via bank + sales via bank)
$receipts = $pdo->prepare("
    SELECT p.payment_date AS date, c.name AS party, p.amount, p.notes AS description, 'Payment' AS source
    FROM payments p
    JOIN customers c ON c.id = p.customer_id
    WHERE p.payment_method = 'bank' AND p.payment_date BETWEEN ? AND ?
    UNION ALL
    SELECT s.sale_date AS date, c.name AS party, s.paid_amount AS amount, s.invoice_no AS description, 'Sale' AS source
    FROM sales s
    JOIN customers c ON c.id = s.customer_id
    WHERE s.payment_method = 'bank' AND s.sale_date BETWEEN ? AND ?
    ORDER BY date, source
");
$receipts->execute([$from, $to, $from, $to]);
$receipts = $receipts->fetchAll();

// Bank payments (supplier payments via bank)
$payments = $pdo->prepare("
    SELECT sp.payment_date AS date, s.name AS party, sp.amount, COALESCE(sp.notes, 'Supplier payment') AS description, 'Supplier Payment' AS source
    FROM supplier_payments sp
    JOIN suppliers s ON s.id = sp.supplier_id
    WHERE sp.payment_method = 'bank' AND sp.payment_date BETWEEN ? AND ?
    ORDER BY sp.payment_date
");
$payments->execute([$from, $to]);
$payments = $payments->fetchAll();

$total_receipts = array_sum(array_column($receipts, 'amount'));
$total_payments = array_sum(array_column($payments, 'amount'));

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
    .text-success, .text-danger, .text-primary {
        color: #000 !important;
    }
    .badge {
        border: 1px solid #000 !important;
        background: #fff !important;
        color: #000 !important;
    }
    .table-responsive {
        overflow: visible !important;
    }
}
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-university me-1"></i> Bank Book</h1>
    <div>
        <button class="btn btn-sm btn-outline-success no-print" onclick="window.print()">
            <i class="fas fa-file-pdf me-1"></i> PDF
        </button>
    </div>
</div>

<div class="card mb-4 border-start-info no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= $from ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= $to ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="row mb-4 no-print">
    <div class="col-md-4">
        <div class="card border-start-success">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-success text-uppercase">Total Bank Receipts</div>
                <div class="h4 mb-0 fw-bold">Rs. <?= money($total_receipts) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-start-danger">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-danger text-uppercase">Total Bank Payments</div>
                <div class="h4 mb-0 fw-bold">Rs. <?= money($total_payments) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-start-primary">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-primary text-uppercase">Net</div>
                <div class="h4 mb-0 fw-bold">Rs. <?= money($total_receipts - $total_payments) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 border-start-success">
    <div class="card-header">Receipts (Bank In)</div>
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
                    <tr><td colspan="4" class="text-center text-muted py-4">No bank receipts found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($receipts as $r): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($r['date'])) ?></td>
                        <td><?= htmlspecialchars($r['party']) ?></td>
                        <td><?= htmlspecialchars($r['description']) ?> <span class="badge bg-info"><?= $r['source'] ?></span></td>
                        <td class="text-end fw-bold text-success"><?= money($r['amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end">Total Receipts</th>
                        <th class="text-end">Rs. <?= money($total_receipts) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="card border-start-danger">
    <div class="card-header">Payments (Bank Out)</div>
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
                    <?php if (empty($payments)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No bank payments found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($p['date'])) ?></td>
                        <td><?= htmlspecialchars($p['party']) ?></td>
                        <td><?= htmlspecialchars($p['description']) ?> <span class="badge bg-secondary"><?= $p['source'] ?></span></td>
                        <td class="text-end fw-bold text-danger"><?= money($p['amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end">Total Payments</th>
                        <th class="text-end">Rs. <?= money($total_payments) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
