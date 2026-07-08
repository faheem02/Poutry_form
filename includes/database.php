<?php

date_default_timezone_set('Asia/Karachi');

// ===== CLIENT: Alwahab Poultry (live) =====
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'atrmarke_alwahab');
// define('DB_USER', 'atrmarke_alwahab');
// define('DB_PASS', 'ATRsales123');

// ===== CLIENT: Poultry Form (live) =====
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'poultry_form');
// define('DB_USER', 'poultry_form_user');
// define('DB_PASS', 'password_here');

// ===== LOCAL DEV =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'poultry_form');
define('DB_USER', 'root');
define('DB_PASS', '');
require_once __DIR__ . '/base_url.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}
