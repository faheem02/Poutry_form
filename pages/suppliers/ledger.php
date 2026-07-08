<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Supplier Ledger';

$supplier_id = (int)($_GET['id'] ?? 0);
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$supplier = null;

if ($supplier_id) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch();
}

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();

$entries = [];
$total_purchases = 0;
$total_payments = 0;
$running = 0;

if ($supplier) {
    $running = (float)$supplier['opening_balance'];
    $entries[] = [
        'type'    => 'opening',
        'date'    => '0000-00-00',
        'id'      => 0,
        'ref'     => 'Opening Balance',
        'birds'   => null,
        'weight'  => null,
        'rate'    => null,
        'debit'   => $running,
        'credit'  => 0,
        'balance' => $running,
        'details' => 'Opening balance',
        'notes'   => '',
    ];

    // Fetch purchases with date filter
    $purSql = "SELECT * FROM purchases WHERE supplier_id = ?";
    $purParams = [$supplier_id];
    if ($from) { $purSql .= " AND purchase_date >= ?"; $purParams[] = $from; }
    if ($to)   { $purSql .= " AND purchase_date <= ?"; $purParams[] = $to; }
    $purSql .= " ORDER BY purchase_date, id";
    $purchases = $pdo->prepare($purSql);
    $purchases->execute($purParams);
    foreach ($purchases as $p) {
        $total_purchases += (float)$p['total_cost'];
        $entries[] = [
            'type'    => 'purchase',
            'date'    => $p['purchase_date'],
            'id'      => $p['id'],
            'ref'     => $p['invoice_no'] ?? '-',
            'birds'   => (int)$p['total_birds'],
            'weight'  => (float)$p['total_weight'],
            'rate'    => (float)$p['purchase_rate'],
            'debit'   => (float)$p['total_cost'],
            'credit'  => 0,
            'details' => money($p['total_weight']) . ' kg @ Rs. ' . money($p['purchase_rate']) . '/kg',
            'notes'   => $p['notes'] ?? '',
        ];
    }

    // Fetch payments with date filter
    $paySql = "SELECT sp.*, p.invoice_no AS purchase_invoice, p.total_birds, p.total_weight, p.purchase_rate, p.total_cost
        FROM supplier_payments sp
        LEFT JOIN purchases p ON p.id = sp.purchase_id
        WHERE sp.supplier_id = ?";
    $payParams = [$supplier_id];
    if ($from) { $paySql .= " AND sp.payment_date >= ?"; $payParams[] = $from; }
    if ($to)   { $paySql .= " AND sp.payment_date <= ?"; $payParams[] = $to; }
    $paySql .= " ORDER BY sp.payment_date, sp.id";
    $payments = $pdo->prepare($paySql);
    $payments->execute($payParams);
    foreach ($payments as $p) {
        $total_payments += (float)$p['amount'];
        $label = $p['notes'] ? htmlspecialchars($p['notes']) : 'Payment (' . ucfirst($p['payment_method']) . ')';
        if ($p['purchase_invoice']) {
            $label .= ' [Against: ' . $p['purchase_invoice'] . ']';
        }
        $entries[] = [
            'type'    => 'payment',
            'date'    => $p['payment_date'],
            'id'      => $p['id'],
            'ref'     => $label,
            'birds'   => $p['total_birds'] !== null ? (int)$p['total_birds'] : null,
            'weight'  => $p['total_weight'] !== null ? (float)$p['total_weight'] : null,
            'rate'    => $p['purchase_rate'] !== null ? (float)$p['purchase_rate'] : null,
            'debit'   => 0,
            'credit'  => (float)$p['amount'],
            'details' => 'Paid via ' . ucfirst($p['payment_method']),
            'notes'   => $p['notes'] ?? '',
        ];
    }

    // Sort by date (opening first), then by type (purchases first for same date)
    usort($entries, function ($a, $b) {
        if ($a['date'] === '0000-00-00') return -1;
        if ($b['date'] === '0000-00-00') return 1;
        if ($a['date'] !== $b['date']) return strcmp($a['date'], $b['date']);
        return ($a['type'] === 'purchase' ? 0 : 1) - ($b['type'] === 'purchase' ? 0 : 1);
    });

    // Recalculate running balance after sort
    $running = 0;
    foreach ($entries as &$e) {
        $running += $e['debit'] - $e['credit'];
        $e['balance'] = $running;
    }
    unset($e);
}

$balance = (float)($supplier['opening_balance'] ?? 0) + $total_purchases - $total_payments;

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
    .text-primary, .text-success, .text-danger, .text-warning {
        color: #000 !important;
    }
    .fw-bold {
        font-weight: 700 !important;
    }
    .row .card {
        display: block !important;
        margin-bottom: 10px !important;
        border: 1px solid #ddd !important;
    }
    .border-start-primary, .border-start-success, .border-start-warning {
        border-left: 4px solid #000 !important;
    }
    .card-body {
        padding: 10px 15px !important;
    }
    .h5, .h4 {
        font-size: 14px !important;
    }
    .small {
        font-size: 9px !important;
    }
    .table-responsive {
        overflow: visible !important;
    }
    tfoot {
        border-top: 2px solid #333 !important;
    }
    .bg-transparent {
        background: #fff !important;
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
.border-start-primary { border-left: 4px solid #4e73df; }
.border-start-success { border-left: 4px solid #1cc88a; }
.border-start-warning { border-left: 4px solid #f6c23e; }
.border-start-info { border-left: 4px solid #36b9cc; }
.text-xs {
    font-size: 11px;
    letter-spacing: 0.5px;
}
.btn-print {
    border-radius: 30px;
    padding: 6px 18px;
}
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book me-2" style="color:#4e73df;"></i> Supplier Ledger</h1>
    <div class="no-print">
        <button class="btn btn-outline-success btn-sm me-1" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print
        </button>
        <a href="<?= BASE_URL ?>/pages/suppliers/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<!-- Filter Card with Date Range -->
<div class="card mb-4 border-start-info no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-bold">Select Supplier</label>
                <select name="id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Choose Supplier --</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['id'] === $supplier_id ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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
            <?php if ($supplier && ($from || $to)): ?>
            <div class="col-auto">
                <a href="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $supplier_id ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($supplier): ?>
<div class="row mb-4 no-print">
    <div class="col-md-4">
        <div class="card border-start-primary h-100">
            <div class="card-body">
                <h5 class="fw-bold"><?= htmlspecialchars($supplier['name']) ?></h5>
                <p class="mb-1 small"><i class="fas fa-phone me-1 text-muted"></i> <?= htmlspecialchars($supplier['phone'] ?? '-') ?></p>
                <p class="mb-0 small"><i class="fas fa-envelope me-1 text-muted"></i> <?= htmlspecialchars($supplier['email'] ?? '-') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-start-success h-100">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-success text-uppercase">Total Purchases</div>
                <div class="h5 mb-0 fw-bold text-success">Rs. <?= money($total_purchases) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-start-warning h-100">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-warning text-uppercase">Total Paid</div>
                <div class="h5 mb-0 fw-bold text-warning">Rs. <?= money($total_payments) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-<?= $balance > 0 ? 'danger' : 'success' ?> h-100">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-<?= $balance > 0 ? 'danger' : 'success' ?> text-uppercase">Balance</div>
                <div class="h4 mb-0 fw-bold text-<?= $balance > 0 ? 'danger' : 'success' ?>">Rs. <?= money($balance) ?></div>
                <?php if ($balance > 0): ?>
                <small class="text-muted">Outstanding</small>
                <?php else: ?>
                <small class="text-muted">No dues</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-start-primary">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list-ul me-1"></i> Transaction Details</span>
        <?php if ($from || $to): ?>
        <span class="badge bg-secondary"><?= $from ? 'From ' . date('d M Y', strtotime($from)) : '' ?> <?= $to ? 'To ' . date('d M Y', strtotime($to)) : '' ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Voucher #</th>
                        <th class="text-center">Birds</th>
                        <th class="text-end">Weight (KG)</th>
                        <th class="text-end">Rate/KG</th>
                        <th>Details / Notes</th>
                        <th class="text-end">Debit (Rs.)</th>
                        <th class="text-end">Credit (Rs.)</th>
                        <th class="text-end">Balance (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No records found for the selected period.</td></tr>
                    <?php else: ?>
                    <?php foreach ($entries as $e): ?>
                    <tr class="<?= $e['type'] === 'opening' ? 'table-secondary' : ($e['type'] === 'payment' ? 'table-success' : '') ?>">
                        <td><?= $e['date'] === '0000-00-00' ? '<span class="text-muted">—</span>' : date('d M Y', strtotime($e['date'])) ?></td>
                        <td>
                            <?php if ($e['type'] === 'purchase'): ?>
                            <i class="fas fa-shopping-cart text-primary me-1"></i>
                            <a href="<?= BASE_URL ?>/pages/purchases/view.php?id=<?= $e['id'] ?>" target="_blank"><?= htmlspecialchars($e['ref']) ?></a>
                            <?php elseif ($e['type'] === 'payment'): ?>
                            <i class="fas fa-money-bill-wave text-success me-1"></i>
                            <span><?= htmlspecialchars($e['ref']) ?></span>
                            <?php else: ?>
                            <i class="fas fa-balance-scale text-secondary me-1"></i>
                            <span class="text-muted"><?= htmlspecialchars($e['ref']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-bold"><?= $e['birds'] !== null ? $e['birds'] : '-' ?></td>
                        <td class="text-end"><?= $e['weight'] !== null ? number_format($e['weight'], 3) : '-' ?></td>
                        <td class="text-end"><?= $e['rate'] !== null ? 'Rs. ' . money($e['rate']) : '-' ?></td>
                        <td>
                            <?php if ($e['type'] === 'purchase'): ?>
                                <span class="text-muted small"><?= htmlspecialchars($e['details']) ?></span>
                            <?php elseif ($e['type'] === 'payment'): ?>
                                <span class="text-muted small"><?= htmlspecialchars($e['details']) ?></span>
                            <?php endif; ?>
                            <?php if ($e['notes']): ?>
                            <br><small class="text-muted"><i class="fas fa-comment me-1"></i> <?= htmlspecialchars($e['notes']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-danger fw-bold"><?= $e['debit'] > 0 ? money($e['debit']) : '-' ?></td>
                        <td class="text-end text-success fw-bold"><?= $e['credit'] > 0 ? money($e['credit']) : '-' ?></td>
                        <td class="text-end fw-bold <?= ($e['balance'] ?? 0) > 0 ? 'text-danger' : 'text-success' ?>">Rs. <?= money($e['balance'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="6" class="text-end">Totals</th>
                        <th class="text-end text-danger fw-bold">Rs. <?= money((float)($supplier['opening_balance'] ?? 0) + $total_purchases) ?></th>
                        <th class="text-end text-success fw-bold">Rs. <?= money($total_payments) ?></th>
                        <th class="text-end <?= $balance > 0 ? 'text-danger' : 'text-success' ?> fw-bold">Rs. <?= money($balance) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php elseif ($supplier_id): ?>
<div class="alert alert-danger">Supplier not found.</div>
<?php else: ?>
<div class="text-center text-muted py-5">
    <i class="fas fa-hand-pointer fa-3x mb-3" style="color:#4e73df;"></i>
    <p>Select a supplier above to view their ledger.</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>