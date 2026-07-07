<?php
date_default_timezone_set('Asia/Karachi');
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Payments';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('CSRF failed');

    if ($_POST['action'] === 'create') {
        $cust_id = (int)$_POST['customer_id'];
        $amount  = (float)$_POST['amount'];
        $method  = $_POST['payment_method'];
        $notes   = sanitize($_POST['notes'] ?? '');
        $date    = $_POST['payment_date'] ?: date('Y-m-d');

        if ($cust_id && $amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO payments (customer_id, amount, payment_method, notes, payment_date, received_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$cust_id, $amount, $method, $notes, $date, $_SESSION['user_id']]);
            setFlash('Payment recorded successfully.');
        } else {
            setFlash('Select a customer and enter amount.');
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('Payment deleted.');
    }
    header('Location: ' . BASE_URL . '/pages/payments/index.php');
    exit;
}

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$customers = $pdo->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();

$paySql = "SELECT p.*, c.name AS customer_name, u.username AS received_by_name
           FROM payments p
           LEFT JOIN customers c ON c.id = p.customer_id
           LEFT JOIN users u ON u.id = p.received_by
           WHERE 1=1";
$payParams = [];
if ($from) { $paySql .= " AND p.payment_date >= ?"; $payParams[] = $from; }
if ($to)   { $paySql .= " AND p.payment_date <= ?"; $payParams[] = $to; }
$paySql .= " ORDER BY p.payment_date DESC, p.id DESC";
$payments = $pdo->prepare($paySql);
$payments->execute($payParams);
$payments = $payments->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ---------- Print Styles ---------- */
@media print {
    body * {
        visibility: visible !important;
        box-shadow: none !important;
        background: #fff !important;
        color: #000 !important;
    }
    .no-print, .no-print * {
        display: none !important;
    }
    .card {
        border: 1px solid #ccc !important;
        border-radius: 0 !important;
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
    .text-success, .text-danger {
        color: #000 !important;
    }
    .fw-bold {
        font-weight: 700 !important;
    }
    .badge {
        border: 1px solid #000 !important;
        background: #fff !important;
        color: #000 !important;
    }
    .card-body {
        padding: 10px 15px !important;
    }
    .table-responsive {
        overflow: visible !important;
    }
    .print-header {
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 10px;
        border-bottom: 2px solid #333;
        padding-bottom: 8px;
    }
}

/* ---------- UI refinements ---------- */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.04);
}
.card-header {
    background: transparent;
    border-bottom: 2px solid #f0f2f7;
    font-weight: 700;
    color: #2d3748;
}
.card-body {
    padding: 20px 24px;
}
.table th {
    font-weight: 700;
    color: #4a5568;
    border-top: none;
}
.table td {
    vertical-align: middle;
}
.border-start-primary { border-left: 4px solid #4e73df; }
.border-start-info { border-left: 4px solid #36b9cc; }
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-hand-holding-usd me-2" style="color:#4e73df;"></i> Customer Payments</h1>
    <div class="no-print">
        <button class="btn btn-outline-success btn-sm me-1" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print
        </button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
            <i class="fas fa-plus me-1"></i> Record Payment
        </button>
    </div>
</div>

<!-- Filter Card (hidden on print) -->
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
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list-ul me-1"></i> Payment Records</span>
        <?php if ($from || $to): ?>
        <span class="badge bg-secondary"><?= $from ? 'From ' . date('d M Y', strtotime($from)) : '' ?> <?= $to ? 'To ' . date('d M Y', strtotime($to)) : '' ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Notes</th>
                        <th>Received By</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No payments found for the selected period.</td></tr>
                    <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($p['customer_name']) ?></td>
                        <td class="fw-bold text-success">Rs. <?= money($p['amount']) ?></td>
                        <td><span class="badge bg-secondary"><?= ucfirst($p['payment_method']) ?></span></td>
                        <td><?= htmlspecialchars($p['notes'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['received_by_name'] ?? '-') ?></td>
                        <td class="no-print">
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
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment Modal (unchanged) -->
<div class="modal fade no-print" id="paymentModal">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Customer *</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
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
                <button type="submit" class="btn btn-primary">Record</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).on('click', '.btn-delete-payment', function () {
    const form = $(this).closest('form');
    Swal.fire({
        title: 'Delete this payment?',
        text: 'This will affect the customer balance.',
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