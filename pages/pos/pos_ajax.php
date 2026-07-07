<?php
session_start();
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$pdo = getDB();
$action = $_REQUEST['action'] ?? '';

// ============== GET TODAY'S RATE + STOCK ==============
if ($action === 'get_rate') {
    $chicken_type_id = (int)($_GET['chicken_type_id'] ?? 0);
    if (!$chicken_type_id) {
        echo json_encode(['success' => false, 'debug_type_id' => $chicken_type_id, 'debug_get' => $_GET]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT rate_per_kg FROM chicken_rates
        WHERE chicken_type_id = ? AND rate_date = CURDATE()
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$chicken_type_id]);
    $rate = $stmt->fetch();

    $stock = availableStock($chicken_type_id);

    if ($rate) {
        echo json_encode([
            'success' => true,
            'rate'    => $rate['rate_per_kg'],
            'birds'   => (int)$stock['birds'],
            'weight'  => (float)$stock['weight'],
            'debug_type_id' => $chicken_type_id,
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'birds'   => (int)$stock['birds'],
            'weight'  => (float)$stock['weight'],
            'debug_type_id' => $chicken_type_id,
        ]);
    }
    exit;
}

// ============== TODAY'S RATES (all types) ==============
if ($action === 'today_rates') {
    $stmt = $pdo->query("
        SELECT ct.name, cr.rate_per_kg AS rate
        FROM chicken_rates cr
        JOIN chicken_types ct ON ct.id = cr.chicken_type_id
        WHERE cr.rate_date = CURDATE()
        ORDER BY ct.name
    ");
    echo json_encode($stmt->fetchAll());
    exit;
}

// ============== SEARCH CUSTOMER ==============
if ($action === 'search_customer' || $action === 'search_customers') {
    $q = $_GET['q'] ?? '';
    if (strlen($q) < 1) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, name, phone,
               (opening_balance +
                COALESCE((SELECT SUM(net_total) FROM sales WHERE customer_id = customers.id), 0) -
                COALESCE((SELECT SUM(amount) FROM payments WHERE customer_id = customers.id), 0)
               ) AS balance
        FROM customers
        WHERE name LIKE ? OR phone LIKE ?
        ORDER BY name LIMIT 20
    ");
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ============== SAVE SALE ==============
if ($action === 'save_sale' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrf)) {
        echo json_encode(['success' => false, 'message' => 'Invalid token. Refresh and try again.']);
        exit;
    }

    $customer_id    = (int)($_POST['customer_id'] ?? 0);
    $chicken_type_id = (int)($_POST['chicken_type_id'] ?? 0);
    $rate_per_kg    = (float)($_POST['rate_per_kg'] ?? 0);
    $birds_count    = (int)($_POST['birds_count'] ?? 0);
    $weight         = (float)($_POST['weight'] ?? 0);
    $amount         = (float)($_POST['amount'] ?? 0);
    $discount       = (float)($_POST['discount'] ?? 0);
    $net_total      = (float)($_POST['net_total'] ?? 0);
    $paid_amount    = (float)($_POST['paid_amount'] ?? 0);
    $balance        = (float)($_POST['balance'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $sale_date      = $_POST['sale_date'] ?? date('Y-m-d');

    if (!$chicken_type_id || $weight <= 0) {
        echo json_encode(['success' => false, 'message' => 'Chicken type and weight are required.']);
        exit;
    }

    // Check available stock
    $stock = availableStock($chicken_type_id);
    $availWeight = (float)$stock['weight'];
    if ($availWeight < $weight) {
        echo json_encode(['success' => false, 'message' => 'Insufficient stock! Available: ' . number_format($availWeight, 2) . ' KG, Required: ' . number_format($weight, 2) . ' KG.']);
        exit;
    }

    if ($customer_id === 0) {
        // Default to Walk-in Customer
        $stmt = $pdo->query("SELECT id FROM customers WHERE name = 'Walk-in Customer' LIMIT 1");
        $walkin = $stmt->fetch();
        $customer_id = $walkin ? (int)$walkin['id'] : 0;
    }

    $invoice_no = generate_invoice_no();

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sales (invoice_no, customer_id, chicken_type_id, birds_count, rate_per_kg, weight, amount, discount, net_total, paid_amount, balance, payment_method, sale_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$invoice_no, $customer_id ?: null, $chicken_type_id, $birds_count, $rate_per_kg, $weight, $amount, $discount, $net_total, $paid_amount, $balance, $payment_method, $sale_date, $_SESSION['user_id']]);
        $sale_id = $pdo->lastInsertId();

        // Update stock ledger (sale removes weight and birds)
        $stmt = $pdo->prepare("
            INSERT INTO stock_ledger (transaction_date, transaction_type, chicken_type_id, birds_count, weight_kg, rate_per_kg, amount, reference_id, notes)
            VALUES (?, 'sale', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$sale_date, $chicken_type_id, $birds_count, $weight, $rate_per_kg, $net_total, $sale_id, 'Sale: ' . $invoice_no]);

        // Always record paid_amount in payments table if > 0
        if ($paid_amount > 0 && $customer_id) {
            $stmt = $pdo->prepare("
                INSERT INTO payments (customer_id, sale_id, amount, payment_method, notes, payment_date, received_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$customer_id, $sale_id, $paid_amount, $payment_method, 'Payment on sale: ' . $invoice_no, $sale_date, $_SESSION['user_id']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'sale_id' => $sale_id, 'invoice_no' => $invoice_no]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
