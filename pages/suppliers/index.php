<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Suppliers';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('CSRF failed');

    if ($_POST['action'] === 'create') {
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, email, address, opening_balance) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            sanitize($_POST['name']),
            sanitize($_POST['phone']),
            sanitize($_POST['email']),
            sanitize($_POST['address']),
            (float)($_POST['opening_balance'] ?? 0)
        ]);
        setFlash('Supplier added successfully.');
    } elseif ($_POST['action'] === 'update') {
        $stmt = $pdo->prepare("UPDATE suppliers SET name=?, phone=?, email=?, address=?, opening_balance=? WHERE id=?");
        $stmt->execute([
            sanitize($_POST['name']),
            sanitize($_POST['phone']),
            sanitize($_POST['email']),
            sanitize($_POST['address']),
            (float)($_POST['opening_balance'] ?? 0),
            (int)$_POST['id']
        ]);
        setFlash('Supplier updated successfully.');
    }
    header('Location: ' . BASE_URL . '/pages/suppliers/index.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('Supplier deleted successfully.');
    header('Location: ' . BASE_URL . '/pages/suppliers/index.php');
    exit;
}

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Suppliers</h1>
    <a href="<?= BASE_URL ?>/pages/suppliers/create.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i> Add Supplier
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
                        <th>Address</th>
                        <th class="text-end">Opening Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['phone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($s['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($s['address'] ?? '-') ?></td>
                        <td class="text-end fw-bold">Rs. <?= money($s['opening_balance'] ?? 0) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary edit-btn"
                                data-id="<?= $s['id'] ?>"
                                data-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                                data-phone="<?= htmlspecialchars($s['phone'] ?? '', ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($s['email'] ?? '', ENT_QUOTES) ?>"
                                data-address="<?= htmlspecialchars(str_replace(["\r","\n"],' ', $s['address'] ?? ''), ENT_QUOTES) ?>"
                                data-opening="<?= $s['opening_balance'] ?? 0 ?>"
                                data-bs-toggle="modal" data-bs-target="#editModal">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete">
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
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header">
                <h5 class="modal-title">Edit Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Opening Balance (Rs.)</label>
                    <input type="number" name="opening_balance" id="edit_opening" class="form-control" step="0.01">
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
        $('#edit_opening').val(btn.data('opening'));
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
