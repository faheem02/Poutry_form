<?php

require_once __DIR__ . '/base_url.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php?expired=1');
    exit;
}

$_SESSION['last_activity'] = time();
