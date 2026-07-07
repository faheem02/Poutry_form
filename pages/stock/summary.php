<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Stock Summary';

$types = $pdo->query("SELECT id, name FROM chicken_types ORDER BY name")->fetchAll();

// Stock summary per type
$stock_data = [];
$grand = ['in_birds' => 0, 'in_weight' => 0, 'out_birds' => 0, 'out_weight' => 0, 'in_amount' => 0, 'out_amount' => 0];
foreach ($types as $t) {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN transaction_type IN ('opening','purchase','adjustment') THEN birds_count ELSE 0 END), 0) AS in_birds,
            COALESCE(SUM(CASE WHEN transaction_type IN ('opening','purchase','adjustment') THEN weight_kg ELSE 0 END), 0) AS in_weight,
            COALESCE(SUM(CASE WHEN transaction_type = 'sale' THEN birds_count ELSE 0 END), 0) AS out_birds,
            COALESCE(SUM(CASE WHEN transaction_type = 'sale' THEN weight_kg ELSE 0 END), 0) AS out_weight,
            COALESCE(SUM(CASE WHEN transaction_type IN ('opening','purchase','adjustment') THEN amount ELSE 0 END), 0) AS in_amount,
            COALESCE(SUM(CASE WHEN transaction_type = 'sale' THEN amount ELSE 0 END), 0) AS out_amount
        FROM stock_ledger WHERE chicken_type_id = ?
    ");
    $stmt->execute([$t['id']]);
    $d = $stmt->fetch();
    $d['name'] = $t['name'];
    $d['avail_birds'] = max(0, $d['in_birds'] - $d['out_birds']);
    $d['avail_weight'] = max(0, $d['in_weight'] - $d['out_weight']);
    $d['stock_value'] = $d['avail_weight'] > 0 && $d['in_weight'] > 0
        ? ($d['in_amount'] / $d['in_weight']) * $d['avail_weight']
        : 0;
    $stock_data[] = $d;
    foreach (['in_birds','in_weight','out_birds','out_weight','in_amount','out_amount'] as $k) {
        $grand[$k] += $d[$k];
    }
}
$grand['avail_birds'] = max(0, $grand['in_birds'] - $grand['out_birds']);
$grand['avail_weight'] = max(0, $grand['in_weight'] - $grand['out_weight']);
$grand['stock_value'] = $grand['avail_weight'] > 0 && $grand['in_weight'] > 0
    ? ($grand['in_amount'] / $grand['in_weight']) * $grand['avail_weight']
    : 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-chart-pie me-1"></i> Stock Summary
    </h1>
    <div>
        <a href="index.php" class="btn btn-outline-info btn-sm"><i class="fas fa-list me-1"></i> Ledger</a>
        <a href="manage.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus me-1"></i> Manage Stock</a>
    </div>
</div>

<!-- Top Stats Row -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
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
    <div class="col-xl-3 col-md-6 mb-3">
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
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-start-info dashboard-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-3 text-center">
                        <i class="fas fa-drumstick fa-2x text-info"></i>
                    </div>
                    <div class="col-9">
                        <div class="text-xs fw-bold text-info text-uppercase mb-1">Available QTY</div>
                        <div class="h4 mb-0 fw-bold"><?= number_format($grand['avail_birds']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-start-warning dashboard-card h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-3 text-center">
                        <i class="fas fa-shopping-cart fa-2x text-warning"></i>
                    </div>
                    <div class="col-9">
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Total Sold</div>
                        <div class="h4 mb-0 fw-bold"><?= number_format($grand['out_birds']) ?> QTY</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Per-Type Stock Cards -->
<div class="row mb-4">
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
                    <span class="badge bg-<?= $isOut ? 'danger' : ($isLow ? 'warning' : 'success') ?>">
                        <?= number_format($sd['avail_birds']) ?> QTY
                    </span>
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

<!-- Quick Summary Table -->
<div class="card border-start-primary">
    <div class="card-header"><i class="fas fa-table me-1"></i> Stock Summary by Type</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Chicken Type</th>
                        <th class="text-end">In (QTY)</th>
                        <th class="text-end">In (KG)</th>
                        <th class="text-end">Sold (QTY)</th>
                        <th class="text-end">Sold (KG)</th>
                        <th class="text-end">Available QTY</th>
                        <th class="text-end">Available KG</th>
                        <th class="text-end">Stock Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stock_data as $sd): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($sd['name']) ?></td>
                        <td class="text-end text-success"><?= number_format($sd['in_birds']) ?></td>
                        <td class="text-end text-success"><?= number_format($sd['in_weight'], 1) ?></td>
                        <td class="text-end text-danger"><?= number_format($sd['out_birds']) ?></td>
                        <td class="text-end text-danger"><?= number_format($sd['out_weight'], 1) ?></td>
                        <td class="text-end fw-bold"><?= number_format($sd['avail_birds']) ?></td>
                        <td class="text-end fw-bold"><?= number_format($sd['avail_weight'], 2) ?></td>
                        <td class="text-end fw-bold text-primary">Rs. <?= money($sd['stock_value']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>TOTAL</td>
                        <td class="text-end"><?= number_format($grand['in_birds']) ?></td>
                        <td class="text-end"><?= number_format($grand['in_weight'], 1) ?></td>
                        <td class="text-end"><?= number_format($grand['out_birds']) ?></td>
                        <td class="text-end"><?= number_format($grand['out_weight'], 1) ?></td>
                        <td class="text-end"><?= number_format($grand['avail_birds']) ?></td>
                        <td class="text-end"><?= number_format($grand['avail_weight'], 2) ?></td>
                        <td class="text-end text-primary">Rs. <?= money($grand['stock_value']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
