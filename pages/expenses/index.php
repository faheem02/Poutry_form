<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Expenses';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('CSRF failed');

    if ($_POST['action'] === 'create') {
        $stmt = $pdo->prepare("INSERT INTO expenses (expense_category, amount, description, expense_date, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['expense_category'],
            (float)$_POST['amount'],
            sanitize($_POST['description'] ?? ''),
            $_POST['expense_date'] ?: date('Y-m-d'),
            $_SESSION['user_id']
        ]);
        setFlash('Expense added successfully.');
    } elseif ($_POST['action'] === 'update') {
        $stmt = $pdo->prepare("UPDATE expenses SET expense_category=?, amount=?, description=?, expense_date=? WHERE id=?");
        $stmt->execute([
            $_POST['expense_category'],
            (float)$_POST['amount'],
            sanitize($_POST['description'] ?? ''),
            $_POST['expense_date'] ?: date('Y-m-d'),
            (int)$_POST['id']
        ]);
        setFlash('Expense updated successfully.');
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('Expense deleted.');
    }
    header('Location: ' . BASE_URL . '/pages/expenses/index.php');
    exit;
}

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT e.*, u.username AS created_by_name
    FROM expenses e
    LEFT JOIN users u ON u.id = e.created_by
    WHERE e.expense_date BETWEEN ? AND ?
    ORDER BY e.expense_date DESC, e.id DESC
");
$stmt->execute([$from, $to]);
$expenses = $stmt->fetchAll();

$categories = ['labour', 'transport', 'electricity', 'misc'];


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
    .text-danger, .text-warning {
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
.border-start-warning { border-left: 4px solid #f6c23e; }
.border-start-danger { border-left: 4px solid #e74a3b; }
.border-start-info { border-left: 4px solid #36b9cc; }
.border-start-primary { border-left: 4px solid #4e73df; }
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-coins me-2" style="color:#f6c23e;"></i> Expenses</h1>
    <div class="no-print">
        <button class="btn btn-outline-success btn-sm me-1" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print
        </button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#expenseModal">
            <i class="fas fa-plus me-1"></i> Add Expense
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
            <?php if ($from !== date('Y-m-01') || $to !== date('Y-m-d')): ?>
            <div class="col-auto">
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Expenses Table -->
<div class="card border-start-primary">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list-ul me-1"></i> Expense Records</span>
        <?php if ($from || $to): ?>
        <span class="badge bg-secondary"><?= date('d M Y', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Added By</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No expenses found for the selected period.</td></tr>
                    <?php else: ?>
                    <?php foreach ($expenses as $e): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($e['expense_date'])) ?></td>
                        <td><span class="badge bg-warning text-dark"><?= ucfirst($e['expense_category']) ?></span></td>
                        <td class="fw-bold text-danger">Rs. <?= money($e['amount']) ?></td>
                        <td><?= htmlspecialchars($e['description'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($e['created_by_name'] ?? '-') ?></td>
                        <td class="no-print">
                            <button class="btn btn-sm btn-outline-primary edit-btn"
                                data-id="<?= $e['id'] ?>"
                                data-cat="<?= $e['expense_category'] ?>"
                                data-amount="<?= $e['amount'] ?>"
                                data-desc="<?= htmlspecialchars(str_replace(["\r","\n"],' ', $e['description'] ?? ''), ENT_QUOTES) ?>"
                                data-date="<?= $e['expense_date'] ?>"
                                data-bs-toggle="modal" data-bs-target="#editModal">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger btn-delete"><i class="fas fa-trash"></i></button>
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

<!-- Create Modal (no-print) -->
<div class="modal fade no-print" id="expenseModal">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header">
                <h5 class="modal-title">Add Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Category *</label>
                    <input type="text" name="expense_category" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Amount (Rs.) *</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal (no-print) -->
<div class="modal fade no-print" id="editModal">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header">
                <h5 class="modal-title">Edit Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Category *</label>
                    <input type="text" name="expense_category" id="edit_cat" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Amount (Rs.) *</label>
                    <input type="number" name="amount" id="edit_amount" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="expense_date" id="edit_date" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).on('click', '.edit-btn', function () {
    $('#edit_id').val($(this).data('id'));
    $('#edit_cat').val($(this).data('cat'));
    $('#edit_amount').val($(this).data('amount'));
    $('#edit_date').val($(this).data('date'));
    $('#edit_desc').val($(this).data('desc'));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>