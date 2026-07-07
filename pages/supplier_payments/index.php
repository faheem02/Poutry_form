<?php
date_default_timezone_set('Asia/Karachi');
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Supplier Payments';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('CSRF failed');

    if ($_POST['action'] === 'create') {
        $supplier_id = (int)$_POST['supplier_id'];
        $amount      = (float)$_POST['amount'];
        $method      = $_POST['payment_method'];
        $notes       = sanitize($_POST['notes'] ?? '');
        $date        = $_POST['payment_date'] ?: date('Y-m-d');

        if ($supplier_id && $amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO supplier_payments (supplier_id, amount, payment_method, notes, payment_date, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$supplier_id, $amount, $method, $notes, $date, $_SESSION['user_id']]);
            setFlash('Supplier payment recorded successfully.');
        } else {
            setFlash('Select a supplier and enter amount.');
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM supplier_payments WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('Supplier payment deleted.');
    }
    header('Location: ' . BASE_URL . '/pages/supplier_payments/index.php');
    exit;
}

$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();

// Get the filter date from query string or default to current month
$filter_date = $_GET['date'] ?? '';
$where_clause = '';
$params = [];
if ($filter_date) {
    $where_clause = "WHERE sp.payment_date = ?";
    $params[] = $filter_date;
}

$sql = "
    SELECT sp.*, s.name AS supplier_name, u.username AS created_by_name
    FROM supplier_payments sp
    LEFT JOIN suppliers s ON s.id = sp.supplier_id
    LEFT JOIN users u ON u.id = sp.created_by
    $where_clause
    ORDER BY sp.payment_date DESC, sp.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-hand-holding-usd me-1"></i> Supplier Payments</h1>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
        <i class="fas fa-plus me-1"></i> Pay Supplier
    </button>
</div>

<div class="card mb-4 border-start-info">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small">Filter by Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i> Filter</button>
            </div>
            <?php if ($filter_date): ?>
            <div class="col-auto">
                <a href="<?= BASE_URL ?>/pages/supplier_payments/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-times me-1"></i> Clear</a>
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
                        <th class="text-end">Amount</th>
                        <th>Method</th>
                        <th>Notes</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($p['supplier_name']) ?></td>
                        <td class="fw-bold text-danger text-end">Rs. <?= money($p['amount']) ?></td>
                        <td><span class="badge bg-secondary"><?= ucfirst($p['payment_method']) ?></span></td>
                        <td><?= htmlspecialchars($p['notes'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['created_by_name'] ?? '-') ?></td>
                        <td>
                            <form method="POST" class="d-inline delete-payment-form">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-payment">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header">
                <h5 class="modal-title">Record Supplier Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Supplier *</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Amount (Rs.) *</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).on('click', '.btn-delete-payment', function () {
    const form = $(this).closest('form');
    Swal.fire({
        title: 'Delete this payment?',
        text: 'This will affect the supplier balance.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e3342f',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
    }).then((result) => {
        if (result.isConfirmed) form.submit();
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
