<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = getDB();
$page_title = 'Supplier Ledger Report';

$supplier_id = (int)($_GET['supplier_id'] ?? 0);
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();

$purchases = [];
$supplier_name = '';
$total_purchases = 0;
$total_weight = 0;

if ($supplier_id) {
    $s = $pdo->prepare("SELECT name FROM suppliers WHERE id = ?");
    $s->execute([$supplier_id]);
    $sup = $s->fetch();
    if ($sup) {
        $supplier_name = $sup['name'];
        $stmt = $pdo->prepare("SELECT * FROM purchases WHERE supplier_id = ? ORDER BY purchase_date DESC, id DESC");
        $stmt->execute([$supplier_id]);
        $purchases = $stmt->fetchAll();
        $total_purchases = array_sum(array_column($purchases, 'total_cost'));
        $total_weight = array_sum(array_column($purchases, 'total_weight'));
    }
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
    .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate {
        display: none !important;
    }
}
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck me-1"></i> Supplier Ledger Report</h1>
    <div>
        <button class="btn btn-sm btn-outline-success no-print" onclick="window.print()">
            <i class="fas fa-file-pdf me-1"></i> PDF
        </button>
    </div>
</div>

<div class="card mb-4 border-start-info no-print">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-auto">
                <select name="supplier_id" class="form-select" required>
                    <option value="">-- Select Supplier --</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $supplier_id === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i> View</button></div>
        </form>
    </div>
</div>

<?php if ($supplier_id && $supplier_name): ?>
<div class="row mb-4 no-print">
    <div class="col-md-6">
        <div class="card border-start-primary">
            <div class="card-body">
                <h5><?= htmlspecialchars($supplier_name) ?></h5>
                <span class="badge bg-primary fs-6">Total Purchases: Rs. <?= money($total_purchases) ?></span>
                <span class="badge bg-info fs-6">Total Weight: <?= number_format($total_weight, 2) ?> KG</span>
            </div>
        </div>
    </div>
</div>

<div class="card border-start-primary">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 small">
                <thead class="table-light">
                    <tr><th>Date</th><th>Invoice</th><th>Birds</th><th class="text-end">Weight</th><th class="text-end">Rate</th><th class="text-end">Total</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($p['purchase_date'])) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($p['invoice_no'] ?? '-') ?></td>
                        <td><?= $p['total_birds'] ?></td>
                        <td class="text-end"><?= number_format($p['total_weight'], 2) ?> KG</td>
                        <td class="text-end">Rs. <?= money($p['purchase_rate']) ?></td>
                        <td class="text-end fw-bold">Rs. <?= money($p['total_cost']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
