<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$customer_id = (int)($_GET['id'] ?? 0);
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$customers = $pdo->query("SELECT * FROM customers ORDER BY name")->fetchAll();
$customer = null;

if ($customer_id) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
}

$page_title = $customer ? 'Ledger - ' . $customer['name'] : 'Customer Ledger';

// Build ledger entries
$entries = [];
$running = 0;

if ($customer) {
    $running = (float)$customer['opening_balance'];
    $entries[] = [
        'date'       => '0000-00-00',
        'sort_id'    => 0,
        'type'       => 'opening',
        'particular' => 'Opening Balance',
        'chicken_type' => '-',
        'weight'     => null,
        'rate'       => null,
        'debit'      => 0,
        'credit'     => 0,
        'balance'    => $running,
    ];

    $salesSql = "SELECT s.*, ct.name AS chicken_type_name FROM sales s JOIN chicken_types ct ON ct.id = s.chicken_type_id WHERE s.customer_id = ?";
    $salesParams = [$customer_id];
    if ($from) { $salesSql .= " AND s.sale_date >= ?"; $salesParams[] = $from; }
    if ($to)   { $salesSql .= " AND s.sale_date <= ?"; $salesParams[] = $to; }
    $salesSql .= " ORDER BY s.sale_date, s.id";
    $sales = $pdo->prepare($salesSql);
    $sales->execute($salesParams);
    foreach ($sales->fetchAll() as $s) {
        $entries[] = [
            'date'       => $s['sale_date'],
            'sort_id'    => (int)$s['id'],
            'type'       => 'sale',
            'particular' => 'Sale Invoice: ' . $s['invoice_no'],
            'chicken_type' => $s['chicken_type_name'],
            'weight'     => (float)$s['weight'],
            'rate'       => (float)$s['rate_per_kg'],
            'debit'      => (float)$s['net_total'],
            'credit'     => 0,
        ];
    }

    $paySql = "SELECT p.*, s.invoice_no AS against_invoice, s.chicken_type_id, s.weight, s.rate_per_kg, s.birds_count, ct.name AS chicken_type_name
        FROM payments p
        LEFT JOIN sales s ON s.id = p.sale_id
        LEFT JOIN chicken_types ct ON ct.id = s.chicken_type_id
        WHERE p.customer_id = ?";
    $payParams = [$customer_id];
    if ($from) { $paySql .= " AND p.payment_date >= ?"; $payParams[] = $from; }
    if ($to)   { $paySql .= " AND p.payment_date <= ?"; $payParams[] = $to; }
    $paySql .= " ORDER BY p.payment_date, p.id";
    $payments = $pdo->prepare($paySql);
    $payments->execute($payParams);
    foreach ($payments->fetchAll() as $p) {
        $label = $p['notes'] ? htmlspecialchars($p['notes']) : 'Payment (' . ucfirst($p['payment_method']) . ')';
        if ($p['against_invoice']) {
            $label .= ' [Against: ' . $p['against_invoice'] . ']';
        }
        $entries[] = [
            'date'       => $p['payment_date'],
            'sort_id'    => (int)$p['id'],
            'type'       => 'payment',
            'particular' => $label,
            'chicken_type' => $p['chicken_type_name'] ?? '-',
            'weight'     => $p['weight'] !== null ? (float)$p['weight'] : null,
            'rate'       => $p['rate_per_kg'] !== null ? (float)$p['rate_per_kg'] : null,
            'debit'      => 0,
            'credit'     => (float)$p['amount'],
        ];
    }

    // Sort: opening first, then payments before sales on same date
    usort($entries, function($a, $b) {
        if ($a['date'] === '0000-00-00') return -1;
        if ($b['date'] === '0000-00-00') return 1;
        if ($a['date'] !== $b['date']) return strcmp($a['date'], $b['date']);
        // payments before sales on same date
        $order = ['payment' => 0, 'sale' => 1];
        $oa = $order[$a['type']] ?? 2;
        $ob = $order[$b['type']] ?? 2;
        if ($oa !== $ob) return $oa <=> $ob;
        return ($a['sort_id'] ?? 0) <=> ($b['sort_id'] ?? 0);
    });

    // Calculate running balance in chronological order
    $running = 0;
    foreach ($entries as &$e) {
        if ($e['date'] === '0000-00-00') {
            $running = $e['balance']; // seed from opening balance
        } else {
            $running += $e['debit'] - $e['credit'];  // debit = customer owes more, credit = customer paid
            $e['balance'] = $running;
        }
    }
    unset($e);
}

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
    .text-danger, .text-success, .text-primary, .text-warning {
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
    .table-warning, .table-success {
        background: #f8f9fa !important;
    }
    .table-warning td, .table-success td {
        background: #f8f9fa !important;
    }
    .row .card {
        display: block !important;
        margin-bottom: 10px !important;
        border: 1px solid #ddd !important;
    }
    .border-start-primary, .border-start-success, .border-start-warning, .border-start-danger {
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
    .print-header {
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 10px;
        border-bottom: 2px solid #333;
        padding-bottom: 8px;
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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book me-2" style="color:#4e73df;"></i> Customer Ledger</h1>
    <div class="no-print">
        <button class="btn btn-outline-success btn-sm me-1" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print
        </button>
        <a href="<?= BASE_URL ?>/pages/customers/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="card mb-4 border-start-info no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-bold">Select Customer</label>
                <select name="id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Choose Customer --</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] === $customer_id ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small fw-bold">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= $from ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small fw-bold">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= $to ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i> Filter</button>
            </div>
            <?php if ($customer && ($from || $to)): ?>
            <div class="col-auto">
                <a href="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $customer_id ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($customer): ?>
<div class="row mb-4 no-print">
    <div class="col-md-6">
        <div class="card border-start-primary h-100">
            <div class="card-body">
                <h5 class="fw-bold"><?= htmlspecialchars($customer['name']) ?></h5>
                <p class="mb-1 small"><i class="fas fa-phone me-1 text-muted"></i> <?= htmlspecialchars($customer['phone'] ?? '-') ?></p>
                <p class="mb-0 small"><i class="fas fa-envelope me-1 text-muted"></i> <?= htmlspecialchars($customer['email'] ?? '-') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start-<?= $running > 0 ? 'danger' : 'success' ?> h-100">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-uppercase text-<?= $running > 0 ? 'danger' : 'success' ?>">Current Balance</div>
                <div class="h4 mb-0 fw-bold text-<?= $running > 0 ? 'danger' : ($running < 0 ? 'primary' : 'success') ?>">
                    Rs. <?= money(abs($running)) ?>
                </div>
                <small class="fw-bold text-<?= $running > 0 ? 'danger' : ($running < 0 ? 'primary' : 'success') ?>">
                    <?= $running > 0 ? '⚠ Amount Due' : ($running < 0 ? '✓ Advance Paid' : '✓ Account Clear') ?>
                </small>
            </div>
        </div>
    </div>
    <?php if ($from || $to): ?>
    <div class="col-md-3">
        <div class="card border-start-info h-100">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-uppercase text-info">Period</div>
                <div class="h6 mb-0 fw-bold">
                    <?= $from ? date('d M Y', strtotime($from)) : 'All' ?> 
                    — 
                    <?= $to ? date('d M Y', strtotime($to)) : 'All' ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
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
                        <th>Chicken Type</th>
                        <th class="text-end">Weight (KG)</th>
                        <th class="text-end">Rate/KG</th>
                        <th class="text-end">Debit (Rs.)</th>
                        <th class="text-end">Credit (Rs.)</th>
                        <th class="text-end">Balance (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No records found for the selected period.</td></tr>
                    <?php else: ?>
                    <?php foreach ($entries as $e): ?>
                    <tr class="<?= ($e['type'] ?? '') === 'sale' ? 'table-warning' : (($e['type'] ?? '') === 'payment' ? 'table-success' : '') ?>">
                        <td data-order="<?= $e['date'] === '0000-00-00' ? '0000-00-00' : $e['date'] ?>">
                            <?= $e['date'] === '0000-00-00' ? '<span class="text-muted">—</span>' : date('d M Y', strtotime($e['date'])) ?>
                        </td>
                        <td>
                            <?php if (($e['type'] ?? '') === 'sale'): ?>
                                <i class="fas fa-file-invoice text-warning me-1"></i>
                            <?php elseif (($e['type'] ?? '') === 'payment'): ?>
                                <i class="fas fa-money-bill-wave text-success me-1"></i>
                            <?php else: ?>
                                <i class="fas fa-balance-scale text-primary me-1"></i>
                            <?php endif; ?>
                            <?= $e['particular'] ?>
                        </td>
                        <td><?= htmlspecialchars($e['chicken_type'] ?? '-') ?></td>
                        <td class="text-end"><?= $e['weight'] !== null ? number_format($e['weight'], 3) : '-' ?></td>
                        <td class="text-end"><?= $e['rate'] !== null ? 'Rs. ' . money($e['rate']) : '-' ?></td>
                        <td class="text-end text-danger fw-bold"><?= $e['debit'] > 0 ? 'Rs. ' . money($e['debit']) : '-' ?></td>
                        <td class="text-end text-success fw-bold"><?= $e['credit'] > 0 ? 'Rs. ' . money($e['credit']) : '-' ?></td>
                        <td class="text-end fw-bold <?= ($e['balance'] ?? 0) > 0 ? 'text-danger' : (($e['balance'] ?? 0) < 0 ? 'text-primary' : 'text-success') ?>">
                            Rs. <?= money(abs($e['balance'] ?? 0)) ?>
                            <small>
                            <?php if (($e['balance'] ?? 0) > 0): ?>
                                <span class="badge bg-danger">Due</span>
                            <?php elseif (($e['balance'] ?? 0) < 0): ?>
                                <span class="badge bg-primary">Adv</span>
                            <?php else: ?>
                                <span class="badge bg-success">Clr</span>
                            <?php endif; ?>
                            </small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($customer_id): ?>
<div class="alert alert-danger">Customer not found.</div>
<?php else: ?>
<div class="text-center text-muted py-5">
    <i class="fas fa-hand-pointer fa-3x mb-3" style="color:#4e73df;"></i>
    <p>Select a customer above to view their ledger.</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>