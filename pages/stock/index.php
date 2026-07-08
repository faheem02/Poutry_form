<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Stock Management';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
$typeFilter = $_GET['type'] ?? '';

$types = $pdo->query("SELECT id, name FROM chicken_types ORDER BY name")->fetchAll();

// Stock summary per type
$stock_data = [];
$grand = ['in_weight' => 0, 'out_weight' => 0, 'in_amount' => 0, 'out_amount' => 0];
foreach ($types as $t) {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN transaction_type IN ('opening','purchase','adjustment') THEN weight_kg ELSE 0 END), 0) AS in_weight,
            COALESCE(SUM(CASE WHEN transaction_type = 'sale' THEN weight_kg ELSE 0 END), 0) AS out_weight,
            COALESCE(SUM(CASE WHEN transaction_type IN ('opening','purchase','adjustment') THEN amount ELSE 0 END), 0) AS in_amount,
            COALESCE(SUM(CASE WHEN transaction_type = 'sale' THEN amount ELSE 0 END), 0) AS out_amount
        FROM stock_ledger WHERE chicken_type_id = ?
    ");
    $stmt->execute([$t['id']]);
    $d = $stmt->fetch();
    $d['name'] = $t['name'];
    $d['avail_weight'] = max(0, $d['in_weight'] - $d['out_weight']);
    $d['stock_value'] = $d['avail_weight'] > 0 && $d['in_weight'] > 0
        ? ($d['in_amount'] / $d['in_weight']) * $d['avail_weight']
        : 0;
    $stock_data[] = $d;
    foreach (['in_weight','out_weight','in_amount','out_amount'] as $k) {
        $grand[$k] += $d[$k];
    }
}
$grand['avail_weight'] = max(0, $grand['in_weight'] - $grand['out_weight']);
$grand['stock_value'] = $grand['avail_weight'] > 0 && $grand['in_weight'] > 0
    ? ($grand['in_amount'] / $grand['in_weight']) * $grand['avail_weight']
    : 0;

// Stock ledger with running balance
$where = "WHERE 1=1";
$params = [];
if ($typeFilter) {
    $where .= " AND sl.chicken_type_id = ?";
    $params[] = $typeFilter;
}
if ($from) {
    $where .= " AND sl.transaction_date >= ?";
    $params[] = $from;
}
if ($to) {
    $where .= " AND sl.transaction_date <= ?";
    $params[] = $to;
}
$stmt = $pdo->prepare("
    SELECT sl.*, ct.name AS chicken_type_name
    FROM stock_ledger sl
    JOIN chicken_types ct ON ct.id = sl.chicken_type_id
    $where
    ORDER BY sl.transaction_date DESC, sl.id DESC
    LIMIT 200
");
$stmt->execute($params);
$ledger = $stmt->fetchAll();

// Running balance — start from stock before filter period, then track each entry
$bal_weight = 0;
if ($from) {
    $balWhere = "WHERE sl.transaction_date < ?";
    $balParams = [$from];
    if ($typeFilter) {
        $balWhere .= " AND sl.chicken_type_id = ?";
        $balParams[] = $typeFilter;
    }
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN transaction_type IN ('opening','purchase','adjustment') THEN weight_kg ELSE 0 END), 0)
            - COALESCE(SUM(CASE WHEN transaction_type = 'sale' THEN weight_kg ELSE 0 END), 0) AS weight
        FROM stock_ledger sl $balWhere
    ");
    $stmt->execute($balParams);
    $bal = $stmt->fetch();
    $bal_weight = (float)$bal['weight'];
}
$ledger_asc = array_reverse($ledger);
$running = [];
foreach ($ledger_asc as $l) {
    if (in_array($l['transaction_type'], ['opening','purchase','adjustment'])) {
        $bal_weight += $l['weight_kg'];
    } elseif ($l['transaction_type'] === 'sale') {
        $bal_weight -= $l['weight_kg'];
    }
    $running[$l['id']] = ['weight' => $bal_weight];
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
    .text-success, .text-danger, .text-primary, .text-warning, .text-info {
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
    .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate {
        display: none !important;
    }
}
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-warehouse me-1"></i> Stock Management
    </h1>
    <div>
        <button class="btn btn-sm btn-outline-success" onclick="window.print()">
            <i class="fas fa-file-pdf me-1"></i> PDF
        </button>
    </div>
</div>

<!-- Top Stats Row -->
<div class="row mb-4 no-print">
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card border-start-primary dashboard-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-3 text-center">
                        <i class="fas fa-boxes fa-2x text-primary"></i>
                    </div>
                    <div class="col-9">
                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Stock Value</div>
                        <div class="h4 mb-0 fw-bold">Rs. <?= money($grand['stock_value']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card border-start-success dashboard-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-3 text-center">
                        <i class="fas fa-weight fa-2x text-success"></i>
                    </div>
                    <div class="col-9">
                        <div class="text-xs fw-bold text-success text-uppercase mb-1">Available Weight</div>
                        <div class="h4 mb-0 fw-bold"><?= number_format($grand['avail_weight'], 2) ?> KG</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card border-start-warning dashboard-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-3 text-center">
                        <i class="fas fa-shopping-cart fa-2x text-warning"></i>
                    </div>
                    <div class="col-9">
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Total Sold</div>
                        <div class="h4 mb-0 fw-bold"><?= number_format($grand['out_weight'], 2) ?> KG</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Per-Type Stock Cards -->
<div class="row mb-4 no-print">
    <?php foreach ($stock_data as $sd):
        $pct = $sd['in_weight'] > 0 ? min(100, ($sd['avail_weight'] / $sd['in_weight']) * 100) : 0;
        $isLow = $sd['avail_weight'] < 10;
        $isOut = $sd['avail_weight'] <= 0;
        $cardBorder = $isOut ? 'danger' : ($isLow ? 'warning' : 'primary');
        $barColor = $isOut ? 'danger' : ($isLow ? 'warning' : 'success');
    ?>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-start-<?= $cardBorder ?> dashboard-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="text-xs fw-bold text-uppercase text-<?= $cardBorder ?>"><?= htmlspecialchars($sd['name']) ?></div>
                </div>
                <div class="h4 mb-0 fw-bold text-<?= $isOut ? 'danger' : 'success' ?>">
                    <?= number_format($sd['avail_weight'], 2) ?> KG
                </div>
                <div class="small text-muted mb-2">Value: Rs. <?= money($sd['stock_value']) ?></div>
                <div class="progress" style="height:6px;">
                    <div class="progress-bar bg-<?= $barColor ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="row small text-muted mt-2 g-0">
                    <div class="col-6 text-success">+<?= number_format($sd['in_weight'], 1) ?> KG in</div>
                    <div class="col-6 text-danger text-end">-<?= number_format($sd['out_weight'], 1) ?> KG out</div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Stock Ledger -->
<div class="card">
    <div class="card-header">
        <div class="row align-items-center">
            <div class="col"><i class="fas fa-list me-1"></i> Stock Ledger</div>
            <div class="col-auto no-print">
                <form method="GET" class="row g-1">
                    <div class="col-auto">
                        <select name="type" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $typeFilter == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= $from ?>"></div>
                    <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= $to ?>"></div>
                    <div class="col-auto"><button class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i> Filter</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small" id="stockLedgerTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Chicken</th>
                        <th class="text-end">Weight (KG)</th>
                        <th class="text-end">Rate/KG</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Balance KG</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledger)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No stock entries found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($ledger as $l):
                        $isSale = $l['transaction_type'] === 'sale';
                        $isPurchase = $l['transaction_type'] === 'purchase';
                        $isOpening = $l['transaction_type'] === 'opening';
                        $isAdj = $l['transaction_type'] === 'adjustment';
                        $bal = $running[$l['id']] ?? ['weight' => 0];
                        $rowClass = $isSale ? 'table-danger' : ($isPurchase ? 'table-success' : ($isOpening ? 'table-info' : ''));
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="fw-bold"><?= date('d M Y', strtotime($l['transaction_date'])) ?></td>
                        <td>
                            <span class="badge bg-<?= $isSale ? 'danger' : ($isPurchase || $isOpening ? 'success' : 'secondary') ?>">
                                <?= $isOpening ? 'Opening' : ucfirst($l['transaction_type']) ?>
                            </span>
                        </td>
                        <td><span class="fw-bold"><?= htmlspecialchars($l['chicken_type_name']) ?></span></td>
                        <td class="text-end <?= $isSale ? 'text-danger' : 'text-success' ?> fw-bold">
                            <?= $isSale ? '-' : '+' ?><?= number_format($l['weight_kg'], 2) ?>
                        </td>
                        <td class="text-end"><?= $l['rate_per_kg'] ? 'Rs. ' . money($l['rate_per_kg']) : '-' ?></td>
                        <td class="text-end fw-bold">Rs. <?= money($l['amount']) ?></td>
                        <td class="text-end fw-bold"><?= number_format($bal['weight'], 2) ?></td>
                        <td class="text-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($l['notes'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (count($ledger) >= 200): ?>
    <div class="card-footer text-muted small text-center">Showing last 200 entries. Refine filters for older records.</div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function () {
    if ($.fn.dataTable) {
        $('#stockLedgerTable').DataTable({
            pageLength: 25,
            order: [],
            language: {
                search: '<i class="fas fa-search me-1"></i>',
                searchPlaceholder: 'Search...',
                lengthMenu: '_MENU_ per page',
                info: 'Showing _START_ to _END_ of _TOTAL_',
            },
            dom: '<"row align-items-center mb-3"<"col-sm-6"l><"col-sm-6"f>>tip',
            stateSave: true,
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
