<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Adjustment History';

$types = $pdo->query("SELECT id, name FROM chicken_types ORDER BY name")->fetchAll();

// Filters
$filter_type   = $_GET['chicken_type_id'] ?? '';
$filter_from   = $_GET['from_date'] ?? '';
$filter_to     = $_GET['to_date'] ?? '';

$where = ["transaction_type = 'adjustment'"];
$params = [];

if ($filter_type !== '') {
    $where[] = "sl.chicken_type_id = ?";
    $params[] = (int)$filter_type;
}
if ($filter_from !== '') {
    $where[] = "sl.transaction_date >= ?";
    $params[] = $filter_from;
}
if ($filter_to !== '') {
    $where[] = "sl.transaction_date <= ?";
    $params[] = $filter_to;
}

$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT sl.*, ct.name AS chicken_type_name
    FROM stock_ledger sl
    JOIN chicken_types ct ON ct.id = sl.chicken_type_id
    WHERE $whereSql
    ORDER BY sl.transaction_date DESC, sl.id DESC
");
$stmt->execute($params);
$adjustments = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-history me-1"></i> Adjustment History
    </h1>
    <div>
        <a href="manage.php" class="btn btn-outline-warning btn-sm"><i class="fas fa-sliders-h me-1"></i> New Adjustment</a>
        <a href="summary.php" class="btn btn-outline-success btn-sm"><i class="fas fa-chart-pie me-1"></i> Summary</a>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Chicken Type</label>
                <select name="chicken_type_id" class="form-select">
                    <option value="">-- All --</option>
                    <?php foreach ($types as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $filter_type == $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($filter_from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($filter_to) ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i> Filter</button>
                <a href="adjustment_history.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i> Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- History Table -->
<div class="card">
    <div class="card-header"><i class="fas fa-list me-1"></i> Adjustments (<?= count($adjustments) ?>)</div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Chicken Type</th>
                    <th>Weight (KG)</th>
                    <th>Rate/KG (Rs.)</th>
                    <th>Amount (Rs.)</th>
                    <th>Reason / Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($adjustments)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">No adjustments found.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($adjustments as $adj): ?>
                    <tr>
                        <td><?= htmlspecialchars($adj['transaction_date']) ?></td>
                        <td><?= htmlspecialchars($adj['chicken_type_name']) ?></td>
                        <td class="<?= $adj['weight_kg'] < 0 ? 'text-danger' : 'text-success' ?>">
                            <?= ($adj['weight_kg'] > 0 ? '+' : '') . number_format($adj['weight_kg'], 3) ?>
                        </td>
                        <td><?= number_format($adj['rate_per_kg'], 2) ?></td>
                        <td><?= number_format($adj['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($adj['notes']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>