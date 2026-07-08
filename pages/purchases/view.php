<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, s.name AS supplier_name, u.username AS created_by_name,
           ct.name AS chicken_type_name
    FROM purchases p
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    LEFT JOIN users u ON u.id = p.created_by
    LEFT JOIN stock_ledger sl ON sl.reference_id = p.id AND sl.transaction_type = 'purchase'
    LEFT JOIN chicken_types ct ON ct.id = sl.chicken_type_id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    header('Location: index.php');
    exit;
}

$page_title = 'Purchase #' . $purchase['id'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Purchase Details</h1>
    <div>
        <a href="edit.php?id=<?= $purchase['id'] ?>" class="btn btn-warning btn-sm">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
        <a href="<?= BASE_URL ?>/pages/purchases/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card border-start-primary">
            <div class="card-header">Purchase Info</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="fw-bold">Date</td><td><?= date('d M Y', strtotime($purchase['purchase_date'])) ?></td></tr>
                    <tr><td class="fw-bold">Supplier</td><td><?= htmlspecialchars($purchase['supplier_name']) ?></td></tr>
                    <tr><td class="fw-bold">Chicken Type</td><td><span class="badge bg-info"><?= htmlspecialchars($purchase['chicken_type_name'] ?? '-') ?></span></td></tr>
                    <tr><td class="fw-bold">Invoice No.</td><td><?= htmlspecialchars($purchase['invoice_no'] ?? '-') ?></td></tr>
                    <tr><td class="fw-bold">Notes</td><td><?= htmlspecialchars($purchase['notes'] ?? '-') ?></td></tr>
                    <tr><td class="fw-bold">Recorded By</td><td><?= htmlspecialchars($purchase['created_by_name'] ?? '-') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card border-start-success">
            <div class="card-header">Purchase Details</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="fw-bold">Total Weight</td><td><?= number_format($purchase['total_weight'], 2) ?> KG</td></tr>
                    <tr><td class="fw-bold">Purchase Rate</td><td>Rs. <?= money($purchase['purchase_rate']) ?>/KG</td></tr>
                    <tr class="table-active"><td class="fw-bold">Total Cost</td><td class="fw-bold">Rs. <?= money($purchase['total_cost']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
