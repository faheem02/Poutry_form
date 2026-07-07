<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT s.*, c.name AS customer_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    header('Location: index.php');
    exit;
}

$types = $pdo->query("SELECT id, name FROM chicken_types ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('CSRF failed');

    $customer_id     = (int)($_POST['customer_id'] ?? 0);
    $chicken_type_id = (int)($_POST['chicken_type_id'] ?? 0);
    $birds_count     = (int)($_POST['birds_count'] ?? 0);
    $weight          = (float)($_POST['weight'] ?? 0);
    $rate_per_kg     = (float)($_POST['rate_per_kg'] ?? 0);
    $amount          = (float)($_POST['amount'] ?? 0);
    $discount        = (float)($_POST['discount'] ?? 0);
    $net_total       = (float)($_POST['net_total'] ?? 0);
    $paid_amount     = (float)($_POST['paid_amount'] ?? 0);
    $balance         = (float)($_POST['balance'] ?? 0);
    $payment_method  = $_POST['payment_method'] ?? 'cash';
    $sale_date       = $_POST['sale_date'] ?? date('Y-m-d');

    if (!$chicken_type_id || $weight <= 0) {
        setFlash('Chicken type and weight are required.');
        header('Location: edit.php?id=' . $id);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE sales
            SET customer_id = ?, chicken_type_id = ?, birds_count = ?, weight = ?,
                rate_per_kg = ?, amount = ?, discount = ?, net_total = ?,
                paid_amount = ?, balance = ?, payment_method = ?, sale_date = ?
            WHERE id = ?
        ");
        $stmt->execute([$customer_id ?: null, $chicken_type_id, $birds_count, $weight, $rate_per_kg, $amount, $discount, $net_total, $paid_amount, $balance, $payment_method, $sale_date, $id]);

        $stmt = $pdo->prepare("
            UPDATE stock_ledger
            SET transaction_date = ?, chicken_type_id = ?, birds_count = ?, weight_kg = ?,
                rate_per_kg = ?, amount = ?, notes = ?
            WHERE reference_id = ? AND transaction_type = 'sale'
        ");
        $stmt->execute([$sale_date, $chicken_type_id, $birds_count, $weight, $rate_per_kg, $net_total, 'Sale: ' . $sale['invoice_no'], $id]);

        $stmt = $pdo->prepare("SELECT id FROM payments WHERE sale_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $existingPayment = $stmt->fetch();

        if ($paid_amount > 0 && $customer_id) {
            if ($existingPayment) {
                $stmt = $pdo->prepare("UPDATE payments SET amount = ?, payment_method = ?, payment_date = ? WHERE sale_id = ?");
                $stmt->execute([$paid_amount, $payment_method, $sale_date, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO payments (customer_id, sale_id, amount, payment_method, notes, payment_date, received_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$customer_id, $id, $paid_amount, $payment_method, 'Payment on sale: ' . $sale['invoice_no'], $sale_date, $_SESSION['user_id']]);
            }
        } elseif ($existingPayment) {
            $stmt = $pdo->prepare("DELETE FROM payments WHERE sale_id = ?");
            $stmt->execute([$id]);
        }

        $pdo->commit();
        setFlash('Sale updated successfully.');
        header('Location: ' . BASE_URL . '/pages/sales/index.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('Error: ' . $e->getMessage());
        header('Location: edit.php?id=' . $id);
        exit;
    }
}

$page_title = 'Edit Sale - ' . $sale['invoice_no'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-edit me-1"></i> Edit Sale – <?= htmlspecialchars($sale['invoice_no']) ?>
    </h1>
    <a href="<?= BASE_URL ?>/pages/sales/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow border-start-primary">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-file-invoice me-2"></i>Sale Information</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Sale Date</label>
                            <input type="date" name="sale_date" class="form-control" value="<?= $sale['sale_date'] ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash" <?= $sale['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="bank" <?= $sale['payment_method'] === 'bank' ? 'selected' : '' ?>>Bank</option>
                                <option value="credit" <?= $sale['payment_method'] === 'credit' ? 'selected' : '' ?>>Credit</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Chicken Type</label>
                            <select name="chicken_type_id" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($types as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $t['id'] === (int)$sale['chicken_type_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer</label>
                            <select name="customer_id" class="form-select">
                                <option value="">Walk-in Customer</option>
                                <?php
                                $customers = $pdo->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();
                                foreach ($customers as $c):
                                ?>
                                <option value="<?= $c['id'] ?>" <?= $c['id'] === (int)$sale['customer_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class=""> 
                            <!--<label class="form-label fw-semibold">Birds Count</label>-->
                            <input type="hidden" name="birds_count" class="form-control" min="0" value="<?= (int)$sale['birds_count'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Weight (KG)</label>
                            <input type="number" name="weight" class="form-control" step="0.001" min="0" required value="<?= $sale['weight'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Rate Per KG</label>
                            <input type="number" name="rate_per_kg" class="form-control" step="0.01" min="0" required value="<?= $sale['rate_per_kg'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Amount</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" value="<?= $sale['amount'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Discount</label>
                            <input type="number" name="discount" class="form-control" step="0.01" min="0" value="<?= $sale['discount'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Net Total</label>
                            <input type="number" name="net_total" class="form-control" step="0.01" min="0" required value="<?= $sale['net_total'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Paid Amount</label>
                            <input type="number" name="paid_amount" class="form-control" step="0.01" min="0" value="<?= $sale['paid_amount'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Balance</label>
                            <input type="number" name="balance" class="form-control" step="0.01" min="0" value="<?= $sale['balance'] ?>">
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="<?= BASE_URL ?>/pages/sales/index.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i> Cancel</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Update Sale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
