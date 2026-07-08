<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Purchases';

// Get filter values
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('CSRF failed');

    if ($_POST['action'] === 'create') {
        $supplier_id    = (int)$_POST['supplier_id'];
        $invoice_no     = sanitize($_POST['invoice_no']);
        $total_weight   = (float)$_POST['total_weight'];
        $purchase_rate  = (float)$_POST['purchase_rate'];
        $purchase_date  = $_POST['purchase_date'] ?: date('Y-m-d');
        $notes          = sanitize($_POST['notes'] ?? '');

        $total_cost = $total_weight * $purchase_rate;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO purchases (supplier_id, invoice_no, total_weight, purchase_rate, total_cost, purchase_date, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$supplier_id, $invoice_no, $total_weight, $purchase_rate, $total_cost, $purchase_date, $notes, $_SESSION['user_id']]);
            $purchase_id = $pdo->lastInsertId();

            // Stock ledger entry
            $chicken_type_id = (int)$_POST['chicken_type_id'];
            $stmt = $pdo->prepare("
                INSERT INTO stock_ledger (transaction_date, transaction_type, chicken_type_id, weight_kg, rate_per_kg, amount, reference_id, notes)
                VALUES (?, 'purchase', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$purchase_date, $chicken_type_id, $total_weight, $purchase_rate, $total_cost, $purchase_id, 'Purchase: ' . ($invoice_no ?: 'N/A')]);

            $pdo->commit();
            setFlash('Purchase recorded successfully. Stock updated.');
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('Error: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . '/pages/purchases/index.php');
    exit;
}

$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();
$types     = $pdo->query("SELECT id, name FROM chicken_types ORDER BY name")->fetchAll();

// Build query with date filter
$sql = "
    SELECT p.*, s.name AS supplier_name, u.username AS created_by_name,
           ct.name AS chicken_type_name
    FROM purchases p
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    LEFT JOIN users u ON u.id = p.created_by
    LEFT JOIN stock_ledger sl ON sl.reference_id = p.id AND sl.transaction_type = 'purchase'
    LEFT JOIN chicken_types ct ON ct.id = sl.chicken_type_id
    WHERE 1=1
";
$params = [];
if ($from) {
    $sql .= " AND p.purchase_date >= ?";
    $params[] = $from;
}
if ($to) {
    $sql .= " AND p.purchase_date <= ?";
    $params[] = $to;
}
$sql .= " ORDER BY p.purchase_date DESC, p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$purchases = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ---------- Print Styles ---------- */
@media print {
    .no-print, .no-print * {
        display: none !important;
    }
    .print-header {
        display: block !important;
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
        border-bottom: 2px solid #333 !important;
    }
    .table tbody tr {
        page-break-inside: avoid;
    }
    .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate {
        display: none !important;
    }
}
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck me-2" style="color:#4e73df;"></i> Purchases</h1>
    <div class="no-print">
        <button class="btn btn-outline-success btn-sm me-1" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print
        </button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#purchaseModal">
            <i class="fas fa-plus me-1"></i> New Purchase
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
            <?php if ($from || $to): ?>
            <div class="col-auto">
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card border-start-primary">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Chicken</th>
                        <th>Invoice</th>
                        <th>Weight (KG)</th>
                        <th>Rate/KG</th>
                        <th>Total Cost</th>
                        <th>Added By</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                    <!-- DataTables requires matching column count even in empty rows -->
                    <tr>
                        <td class="text-center text-muted py-4">No purchases found for the selected period.</td>
                        <td></td><td></td><td></td><td></td>
                        <td></td><td></td><td></td>
                        <td class="no-print"></td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($p['purchase_date'])) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($p['supplier_name']) ?></td>
                        <td><span class="badge bg-info"><?= htmlspecialchars($p['chicken_type_name'] ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($p['invoice_no'] ?? '-') ?></td>
                        <td><?= number_format($p['total_weight'], 2) ?></td>
                        <td>Rs. <?= money($p['purchase_rate']) ?></td>
                        <td>Rs. <?= money($p['total_cost']) ?></td>
                        <td><?= htmlspecialchars($p['created_by_name'] ?? '-') ?></td>
                        <td class="no-print">
                            <div class="d-flex gap-1">
                                <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Edit Purchase">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete" data-bs-toggle="tooltip" title="Delete Purchase" data-text="Delete <?= $p['invoice_no'] ? htmlspecialchars($p['invoice_no']) : 'Purchase #'.$p['id'] ?>? This will also remove its stock ledger entry.">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Purchase Modal (unchanged) -->
<div class="modal fade" id="purchaseModal">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header">
                <h5 class="modal-title">New Purchase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Supplier *</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Chicken Type *</label>
                        <select name="chicken_type_id" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Invoice No.</label>
                        <input type="text" name="invoice_no" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Total Weight (KG) *</label>
                        <input type="number" name="total_weight" class="form-control" step="0.001" min="0" required id="p_weight">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Purchase Rate/KG (Rs.) *</label>
                        <input type="number" name="purchase_rate" class="form-control" step="0.01" min="0" required id="p_rate">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Total Cost (Auto)</label>
                        <input type="text" class="form-control" id="p_total" readonly placeholder="Auto-calculated">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Purchase</button>
            </div>
        </form>
    </div>
</div>

<script>
$('#p_weight, #p_rate').on('input', function () {
    const w = parseFloat($('#p_weight').val()) || 0;
    const r = parseFloat($('#p_rate').val()) || 0;
    $('#p_total').val((w * r).toFixed(2));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>