<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    setFlash('error', 'Invalid purchase ID.');
    header('Location: index.php');
    exit;
}

// Fetch the purchase to confirm it exists
$stmt = $pdo->prepare("SELECT id, invoice_no FROM purchases WHERE id = ?");
$stmt->execute([$id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    setFlash('error', 'Purchase not found.');
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Delete stock ledger entry for this purchase
    $stmt = $pdo->prepare("DELETE FROM stock_ledger WHERE transaction_type = 'purchase' AND reference_id = ?");
    $stmt->execute([$id]);

    // Delete the purchase
    $stmt = $pdo->prepare("DELETE FROM purchases WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    $label = $purchase['invoice_no'] ? htmlspecialchars($purchase['invoice_no']) : 'Purchase #' . $id;
    setFlash('success', $label . ' deleted successfully.');
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Failed to delete purchase: ' . $e->getMessage());
}

header('Location: index.php');
exit;
