<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = getDB();
$page_title = 'Stock Report';

$types = $pdo->query("SELECT id, name FROM chicken_types ORDER BY name")->fetchAll();

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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-warehouse me-1"></i> Stock Report</h1>
</div>

<div class="row mb-4 no-print">
    <?php foreach ($types as $t):
        $data = availableStock($t['id']);
    ?>
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-start-primary dashboard-card">
            <div class="card-body text-center">
                <div class="text-xs fw-bold text-primary text-uppercase"><?= htmlspecialchars($t['name']) ?></div>
                <div class="h4 mb-0 fw-bold"><?= number_format($data['weight'], 2) ?> KG</div>
                <small class="text-muted"><?= $data['birds'] ?> birds</small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card border-start-primary">
    <div class="card-header">Detailed Stock Ledger</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 small">
                <thead class="table-light">
                    <tr><th>Date</th><th>Type</th><th>Chicken</th><th class="text-end">Birds</th><th class="text-end">Weight</th><th class="text-end">Rate</th><th class="text-end">Amount</th><th>Notes</th></tr>
                </thead>
                <tbody>
                    <?php
                    $ledger = $pdo->query("
                        SELECT sl.*, ct.name AS chicken_type_name
                        FROM stock_ledger sl
                        JOIN chicken_types ct ON ct.id = sl.chicken_type_id
                        ORDER BY sl.transaction_date DESC, sl.id DESC
                    ")->fetchAll();
                    foreach ($ledger as $l):
                    ?>
                    <tr class="<?= $l['transaction_type'] === 'sale' ? 'table-danger' : ($l['transaction_type'] === 'purchase' ? 'table-success' : '') ?>">
                        <td><?= date('d M Y', strtotime($l['transaction_date'])) ?></td>
                        <td><span class="badge bg-<?= $l['transaction_type'] === 'sale' ? 'danger' : ($l['transaction_type'] === 'purchase' ? 'success' : 'info') ?>"><?= ucfirst($l['transaction_type']) ?></span></td>
                        <td><?= htmlspecialchars($l['chicken_type_name']) ?></td>
                        <td class="text-end"><?= $l['birds_count'] ?: '-' ?></td>
                        <td class="text-end"><?= number_format($l['weight_kg'], 2) ?></td>
                        <td class="text-end"><?= $l['rate_per_kg'] ? 'Rs. ' . money($l['rate_per_kg']) : '-' ?></td>
                        <td class="text-end fw-bold">Rs. <?= money($l['amount']) ?></td>
                        <td><?= htmlspecialchars($l['notes'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
