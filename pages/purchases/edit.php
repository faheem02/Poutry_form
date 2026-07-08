<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, s.name AS supplier_name,
           sl.chicken_type_id
    FROM purchases p
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    LEFT JOIN stock_ledger sl ON sl.reference_id = p.id AND sl.transaction_type = 'purchase'
    WHERE p.id = ?
");
$stmt->execute([$id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    setFlash('error', 'Purchase not found.');
    header('Location: index.php');
    exit;
}

$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();
$types     = $pdo->query("SELECT id, name FROM chicken_types ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('CSRF failed');

    $supplier_id     = (int)$_POST['supplier_id'];
    $chicken_type_id = (int)$_POST['chicken_type_id'];
    $invoice_no      = sanitize($_POST['invoice_no'] ?? '');
    $total_weight    = (float)$_POST['total_weight'];
    $purchase_rate   = (float)$_POST['purchase_rate'];
    $purchase_date   = $_POST['purchase_date'] ?: date('Y-m-d');
    $notes           = sanitize($_POST['notes'] ?? '');
    $total_cost      = $total_weight * $purchase_rate;

    if (!$supplier_id || !$chicken_type_id || $total_weight <= 0 || $purchase_rate <= 0) {
        setFlash('error', 'Supplier, chicken type, weight and rate are required.');
        header('Location: edit.php?id=' . $id);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Update purchase
        $stmt = $pdo->prepare("
            UPDATE purchases
            SET supplier_id = ?, invoice_no = ?, total_weight = ?,
                purchase_rate = ?, total_cost = ?, purchase_date = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([$supplier_id, $invoice_no, $total_weight, $purchase_rate, $total_cost, $purchase_date, $notes, $id]);

        // Update stock ledger entry
        $stmt = $pdo->prepare("
            UPDATE stock_ledger
            SET transaction_date = ?, chicken_type_id = ?,
                weight_kg = ?, rate_per_kg = ?, amount = ?,
                notes = ?
            WHERE reference_id = ? AND transaction_type = 'purchase'
        ");
        $stmt->execute([$purchase_date, $chicken_type_id, $total_weight, $purchase_rate, $total_cost, 'Purchase: ' . ($invoice_no ?: 'N/A'), $id]);

        $pdo->commit();
        setFlash('success', 'Purchase updated successfully.');
        header('Location: ' . BASE_URL . '/pages/purchases/index.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Error: ' . $e->getMessage());
        header('Location: edit.php?id=' . $id);
        exit;
    }
}

$page_title = 'Edit Purchase #' . $id;
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-edit me-1"></i> Edit Purchase
        <?php if ($purchase['invoice_no']): ?>
            – <?= htmlspecialchars($purchase['invoice_no']) ?>
        <?php else: ?>
            #<?= $id ?>
        <?php endif; ?>
    </h1>
    <a href="<?= BASE_URL ?>/pages/purchases/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow border-start-primary">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-truck me-2"></i>Purchase Information</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Supplier *</label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] === (int)$purchase['supplier_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Chicken Type *</label>
                            <select name="chicken_type_id" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($types as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $t['id'] === (int)$purchase['chicken_type_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Purchase Date</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?= $purchase['purchase_date'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Invoice No.</label>
                            <input type="text" name="invoice_no" class="form-control" value="<?= htmlspecialchars($purchase['invoice_no'] ?? '') ?>" placeholder="Optional">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Total Weight (KG) *</label>
                            <input type="number" name="total_weight" id="p_weight" class="form-control" step="0.001" min="0" required value="<?= $purchase['total_weight'] ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Purchase Rate/KG (Rs.) *</label>
                            <input type="number" name="purchase_rate" id="p_rate" class="form-control" step="0.01" min="0" required value="<?= $purchase['purchase_rate'] ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Total Cost (Auto)</label>
                            <input type="text" id="p_total" class="form-control bg-light" readonly value="<?= number_format($purchase['total_cost'], 2) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($purchase['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="<?= BASE_URL ?>/pages/purchases/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i> Update Purchase
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
