<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Chicken Types';

// Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('CSRF validation failed');

    if ($_POST['action'] === 'create') {
        $name = sanitize($_POST['name']);
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO chicken_types (name) VALUES (?)");
            $stmt->execute([$name]);
            setFlash('Chicken type added successfully.');
        }
    } elseif ($_POST['action'] === 'update') {
        $id  = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        if ($id && $name) {
            $stmt = $pdo->prepare("UPDATE chicken_types SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            setFlash('Chicken type updated successfully.');
        }
    }
    header('Location: ' . BASE_URL . '/pages/chicken_types/index.php');
    exit;
}

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM chicken_types WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('Chicken type deleted successfully.');
    header('Location: ' . BASE_URL . '/pages/chicken_types/index.php');
    exit;
}

$types = $pdo->query("SELECT * FROM chicken_types ORDER BY name")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Chicken Types</h1>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="fas fa-plus me-1"></i> Add Type
    </button>
</div>

<div class="card border-start-primary">
    <div class="card-body">
        <table class="table datatable table-hover">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($types as $i => $t): ?>
                <tr>
                    <td><?= $t['id'] ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($t['name']) ?></td>
                    <td><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary edit-btn"
                            data-id="<?= $t['id'] ?>"
                            data-name="<?= htmlspecialchars($t['name']) ?>"
                            data-bs-toggle="modal" data-bs-target="#editModal">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?delete=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete"
                           data-text="This will delete the chicken type and all related rates.">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header">
                <h5 class="modal-title">Add Chicken Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Type Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Broiler">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
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
                <h5 class="modal-title">Edit Chicken Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Type Name</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
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
    $('#edit_name').val($(this).data('name'));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
