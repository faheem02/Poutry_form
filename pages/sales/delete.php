<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    setFlash('error', 'Invalid sale ID.');
    header('Location: index.php');
    exit;
}

// Fetch the sale to confirm it exists
$stmt = $pdo->prepare("SELECT id, invoice_no FROM sales WHERE id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    setFlash('error', 'Sale not found.');
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Delete stock ledger entry for this sale
    $stmt = $pdo->prepare("DELETE FROM stock_ledger WHERE transaction_type = 'sale' AND reference_id = ?");
    $stmt->execute([$id]);

    // Payments: FK is ON DELETE SET NULL, so we just delete the sale row
    // Optionally also delete linked payment rows tied to this sale
    $stmt = $pdo->prepare("DELETE FROM payments WHERE sale_id = ?");
    $stmt->execute([$id]);

    // Delete the sale
    $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    setFlash('success', 'Sale ' . htmlspecialchars($sale['invoice_no']) . ' deleted successfully.');
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Failed to delete sale: ' . $e->getMessage());
}

header('Location: index.php');
exit;
