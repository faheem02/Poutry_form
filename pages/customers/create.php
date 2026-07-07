<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Create Customer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('CSRF failed');
    $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, address, opening_balance) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        sanitize($_POST['name']),
        sanitize($_POST['phone']),
        sanitize($_POST['email']),
        sanitize($_POST['address']),
        (float)($_POST['opening_balance'] ?? 0)
    ]);
    setFlash('Customer created successfully.');
    header('Location: ' . BASE_URL . '/pages/customers/index.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user-plus me-1"></i> Create Customer</h1>
    <a href="<?= BASE_URL ?>/pages/customers/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back to List
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow border-start-primary">
            <div class="card-header py-3 d-flex align-items-center">
                <i class="fas fa-user-circle fa-2x text-primary me-3"></i>
                <div>
                    <h5 class="mb-0 fw-bold">Customer Information</h5>
                    <small class="text-muted">Enter the details of the new customer</small>
                </div>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="text" name="phone" class="form-control" placeholder="e.g. 0300-1234567">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="e.g. john@example.com">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Opening Balance (Rs.)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-coins"></i></span>
                                <input type="number" name="opening_balance" class="form-control" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="col-12 mb-4">
                            <label class="form-label fw-semibold">Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                <textarea name="address" class="form-control" rows="2" placeholder="e.g. 123 Main Street, Lahore"></textarea>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="<?= BASE_URL ?>/pages/customers/index.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i> Cancel</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Save Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
