<?php
require_once __DIR__ . '/includes/base_url.php';
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/pages/dashboard/index.php');
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
