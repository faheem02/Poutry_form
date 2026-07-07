<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, ct.name AS chicken_type, u.username AS created_by_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    JOIN chicken_types ct ON ct.id = s.chicken_type_id
    LEFT JOIN users u ON u.id = s.created_by
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    header('Location: index.php');
    exit;
}

$prev_balance = 0;
if ($sale['customer_id']) {
    $current_balance = getCustomerBalance((int)$sale['customer_id']);
    $prev_balance = $current_balance - (float)$sale['net_total'] + (float)$sale['paid_amount'];
}

$page_title = 'Sale - ' . $sale['invoice_no'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-file-invoice me-1"></i> <?= htmlspecialchars($sale['invoice_no']) ?>
    </h1>
    <div>
        <a href="edit.php?id=<?= $sale['id'] ?>" class="btn btn-warning btn-sm">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
        <a href="<?= BASE_URL ?>/pages/sales/invoice.php?id=<?= $sale['id'] ?>" class="btn btn-primary btn-sm" target="_blank">
            <i class="fas fa-print me-1"></i> Print Invoice
        </a>
        <a href="<?= BASE_URL ?>/pages/sales/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card border-start-primary">
            <div class="card-header">Sale Info</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="fw-bold">Invoice No</td><td><?= htmlspecialchars($sale['invoice_no']) ?></td></tr>
                    <tr><td class="fw-bold">Date</td><td><?= date('d M Y', strtotime($sale['sale_date'])) ?></td></tr>
                    <tr><td class="fw-bold">Customer</td><td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td></tr>
                    <tr><td class="fw-bold">Phone</td><td><?= htmlspecialchars($sale['customer_phone'] ?? '-') ?></td></tr>
                    <tr><td class="fw-bold">Chicken Type</td><td><?= htmlspecialchars($sale['chicken_type']) ?></td></tr>
                    <tr><td class="fw-bold">Payment Method</td><td><span class="badge bg-secondary"><?= ucfirst($sale['payment_method']) ?></span></td></tr>
                    <tr><td class="fw-bold">Sold By</td><td><?= htmlspecialchars($sale['created_by_name'] ?? '-') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card border-start-success">
            <div class="card-header">Amount Details</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="fw-bold">Rate Per KG</td><td>Rs. <?= money($sale['rate_per_kg']) ?></td></tr>
                    <tr><td class="fw-bold">Weight</td><td><?= number_format($sale['weight'], 3) ?> KG</td></tr>
                    <tr><td class="fw-bold">Amount</td><td>Rs. <?= money($sale['amount']) ?></td></tr>
                    <tr><td class="fw-bold">Discount</td><td>Rs. <?= money($sale['discount']) ?></td></tr>
                    <tr class="table-active"><td class="fw-bold">Net Total</td><td class="fw-bold">Rs. <?= money($sale['net_total']) ?></td></tr>
                    <tr><td class="fw-bold">Paid Amount</td><td>Rs. <?= money($sale['paid_amount']) ?></td></tr>
                    <tr class="<?= $sale['balance'] > 0 ? 'table-danger' : 'table-success' ?>">
                        <td class="fw-bold">Balance</td>
                        <td class="fw-bold">Rs. <?= money($sale['balance']) ?></td>
                    </tr>
                    <?php if ($sale['customer_id']): ?>
                    <tr>
                        <td class="text-muted">Previous Balance</td>
                        <td class="text-muted">Rs. <?= money($prev_balance) ?></td>
                    </tr>
                    <tr class="table-secondary">
                        <td class="fw-bold">Total Outstanding</td>
                        <td class="fw-bold">Rs. <?= money($prev_balance + $sale['balance']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
