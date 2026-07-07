<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Customers';

// Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('CSRF failed');

    if ($_POST['action'] === 'create') {
        $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, address, opening_balance) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            sanitize($_POST['name']),
            sanitize($_POST['phone']),
            sanitize($_POST['email']),
            sanitize($_POST['address']),
            (float)($_POST['opening_balance'] ?? 0)
        ]);
        setFlash('Customer added successfully.');
    } elseif ($_POST['action'] === 'update') {
        $stmt = $pdo->prepare("UPDATE customers SET name=?, phone=?, email=?, address=?, opening_balance=? WHERE id=?");
        $stmt->execute([
            sanitize($_POST['name']),
            sanitize($_POST['phone']),
            sanitize($_POST['email']),
            sanitize($_POST['address']),
            (float)($_POST['opening_balance'] ?? 0),
            (int)$_POST['id']
        ]);
        setFlash('Customer updated successfully.');
    }
    header('Location: ' . BASE_URL . '/pages/customers/index.php');
    exit;
}

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('Customer deleted successfully.');
    header('Location: ' . BASE_URL . '/pages/customers/index.php');
    exit;
}

$customers = $pdo->query("SELECT * FROM customers ORDER BY name")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Customers</h1>
    <a href="<?= BASE_URL ?>/pages/customers/create.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i> Add Customer
    </a>
</div>

<div class="card border-start-primary">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Opening Bal.</th>
                        <th>Current Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): $bal = getCustomerBalance($c['id']); ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($c['name']) ?></td>
                        <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['email'] ?? '-') ?></td>
                        <td>Rs. <?= money($c['opening_balance']) ?></td>
                        <td>
                            <span class="badge bg-<?= $bal > 0 ? 'warning' : 'success' ?>">
                                Rs. <?= money($bal) ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>/pages/customers/ledger.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-info" title="Ledger">
                                <i class="fas fa-book"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-primary edit-btn"
                                data-id="<?= $c['id'] ?>"
                                data-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>"
                                data-phone="<?= htmlspecialchars($c['phone'] ?? '', ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($c['email'] ?? '', ENT_QUOTES) ?>"
                                data-address="<?= htmlspecialchars(str_replace(["\r","\n"],' ', $c['address'] ?? ''), ENT_QUOTES) ?>"
                                data-balance="<?= $c['opening_balance'] ?>"
                                data-bs-toggle="modal" data-bs-target="#editModal">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete"
                               data-text="Delete this customer? All related data will be preserved.">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header">
                <h5 class="modal-title">Edit Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opening Balance (Rs.)</label>
                        <input type="number" name="opening_balance" id="edit_balance" class="form-control" step="0.01">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                    </div>
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
$(function () {
    $('#editModal').on('show.bs.modal', function (e) {
        const btn = $(e.relatedTarget);
        $('#edit_id').val(btn.data('id'));
        $('#edit_name').val(btn.data('name'));
        $('#edit_phone').val(btn.data('phone'));
        $('#edit_email').val(btn.data('email'));
        $('#edit_address').val(btn.data('address'));
        $('#edit_balance').val(btn.data('balance'));
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
