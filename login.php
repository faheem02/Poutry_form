<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/pages/dashboard/index.php');
    exit;
}

require_once __DIR__ . '/includes/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $password === $user['password_hash']) {
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_name']   = $user['username'];
            $_SESSION['user_role']   = $user['role'];
            $_SESSION['last_activity'] = time();

            header('Location: ' . BASE_URL . '/pages/dashboard/index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Poultry Shop POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sb-admin-custom.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height:100vh;">
            <div class="col-md-5">
                <div class="card login-card">
                    <div class="card-header">
                        <div class="login-icon mb-2">
                            <i class="fas fa-drumstick-bite"></i>
                        </div>
                        <h4 class="text-gray-800 fw-bold">AL WAHAB POULTRY</h4>
                        <p class="text-muted small">Sign in to your account</p>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <?php if ($error): ?>
                            <div class="alert alert-danger py-2 small">
                                <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($_GET['expired'])): ?>
                            <div class="alert alert-warning py-2 small">
                                <i class="fas fa-clock me-1"></i> Session expired. Please login again.
                            </div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                                <i class="fas fa-sign-in-alt me-2"></i> Login
                            </button>
                        </form>
                    </div>
                </div>
                <p class="text-center text-white-50 small mt-3">&copy; <?= date('Y') ?> Poultry Shop POS</p>
            </div>
        </div>
    </div>
</body>
</html>
