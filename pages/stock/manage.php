<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Stock Management';

$types = $pdo->query("SELECT id, name FROM chicken_types ORDER BY name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type    = $_POST['action_type'] ?? ''; // 'opening' or 'adjustment'
    $chicken_type_id = (int)($_POST['chicken_type_id'] ?? 0);
    // $birds_count    = (int)($_POST['birds_count'] ?? 0);
    $weight_kg      = (float)($_POST['weight_kg'] ?? 0);
    $rate_per_kg    = (float)($_POST['rate_per_kg'] ?? 0);
    $amount         = (float)($_POST['amount'] ?? 0);
    $adjust_date    = $_POST['adjust_date'] ?? date('Y-m-d');
    $notes          = sanitize($_POST['notes'] ?? '');

    if (!$chicken_type_id || $weight_kg == 0 || ($action_type === 'opening' && $weight_kg < 0)) {
    setFlash('Chicken type and valid weight are required.');
    } else {
        $amount = $amount ?: ($rate_per_kg * $weight_kg);
        $trans_type = $action_type === 'opening' ? 'opening' : 'adjustment';

        try {
            $stmt = $pdo->prepare("
                INSERT INTO stock_ledger (transaction_date, transaction_type, chicken_type_id, weight_kg, rate_per_kg, amount, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$adjust_date, $trans_type, $chicken_type_id, $weight_kg, $rate_per_kg, $amount, $notes]);
            setFlash('Stock ' . ($action_type === 'opening' ? 'opening' : 'adjustment') . ' added successfully.');
            header('Location: summary.php');
            exit;
        } catch (Exception $e) {
            setFlash('Error: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-plus-circle me-1"></i> Stock Management
    </h1>
    <div>
        <a href="summary.php" class="btn btn-outline-success btn-sm"><i class="fas fa-chart-pie me-1"></i> Summary</a>
        <a href="index.php" class="btn btn-outline-info btn-sm"><i class="fas fa-list me-1"></i> Ledger</a>
    </div>
</div>

<div class="row">
    <!-- Opening Stock Form -->
    <div class="col-lg-6 mb-4">
        <div class="card border-start-success h-100">
            <div class="card-header"><i class="fas fa-box-open me-1"></i> Add Opening Stock</div>
            <div class="card-body">
                <p class="text-muted small">Use this to record initial stock or add new stock manually.</p>
                <form method="POST">
                    <input type="hidden" name="action_type" value="opening">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Chicken Type</label>
                            <select name="chicken_type_id" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($types as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Date</label>
                            <input type="date" name="adjust_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Weight (KG)</label>
                            <input type="number" name="weight_kg" class="form-control" step="0.001" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Rate/KG (Rs.)</label>
                            <input type="number" name="rate_per_kg" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Amount (Rs.)</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" value="0" placeholder="Auto-calc from rate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Notes</label>
                            <input type="text" name="notes" class="form-control" placeholder="Optional notes" maxlength="255">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Save Opening Stock</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Form -->
    <div class="col-lg-6 mb-4">
        <div class="card border-start-warning h-100">
            <div class="card-header"><i class="fas fa-sliders-h me-1"></i> Stock Adjustment</div>
            <div class="card-body">
                <p class="text-muted small">Use this for corrections, damage/loss, or manual additions/removals.</p>
                <form method="POST">
                    <input type="hidden" name="action_type" value="adjustment">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Chicken Type</label>
                            <select name="chicken_type_id" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($types as $t): 
                                    $stock = availableStock($t['id']);
                                ?>
                                <option value="<?= $t['id'] ?>" data-avail="<?= $stock['weight'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= number_format($stock['weight'], 1) ?> KG avail)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Date</label>
                            <input type="date" name="adjust_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                       
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Weight (+/- KG)</label>
                            <input type="number" name="weight_kg" class="form-control" step="0.001" required placeholder="+10 or -5">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Rate/KG (Rs.)</label>
                            <input type="number" name="rate_per_kg" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Amount (Rs.)</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Reason / Notes</label>
                            <input type="text" name="notes" class="form-control" placeholder="e.g. Damaged, lost, correction" maxlength="255" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Save Adjustment</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('[name="rate_per_kg"]').on('input', function () {
        const $form = $(this).closest('form');
        const rate = parseFloat($(this).val()) || 0;
        const weight = parseFloat($form.find('[name="weight_kg"]').val()) || 0;
        if (rate > 0 && weight > 0) {
            $form.find('[name="amount"]').val((rate * weight).toFixed(2));
        }
    });
    $('[name="weight_kg"]').on('input', function () {
        const $form = $(this).closest('form');
        const rate = parseFloat($form.find('[name="rate_per_kg"]').val()) || 0;
        const weight = parseFloat($(this).val()) || 0;
        if (rate > 0 && weight > 0) {
            $form.find('[name="amount"]').val((rate * weight).toFixed(2));
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
