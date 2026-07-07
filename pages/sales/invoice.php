<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
           ct.name AS chicken_type, u.username AS created_by_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    JOIN chicken_types ct ON ct.id = s.chicken_type_id
    LEFT JOIN users u ON u.id = s.created_by
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    echo 'Sale not found.';
    exit;
}

// Calculate customer's balance before this sale
$prev_balance = 0;
if ($sale['customer_id']) {
    $current_balance = getCustomerBalance((int)$sale['customer_id']);
    $prev_balance = $current_balance - (float)$sale['net_total'] + (float)$sale['paid_amount'];
}

$page_title = 'Invoice - ' . $sale['invoice_no'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= htmlspecialchars($sale['invoice_no']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sb-admin-custom.css" rel="stylesheet">
    <style>
        @media print { .no-print { display: none !important; } body { background: #fff; } .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; } }
        .invoice-title { font-size: 1.5rem; font-weight: 700; color: #059669; }
        table { font-size: 0.95rem; }
        .table td, .table th { padding: 0.5rem 0.75rem; }
    </style>
</head>
<body>
    <div class="container mt-3 mb-3">
        <div class="text-center no-print mb-3">
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i> Print</button>
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <div class="text-center mb-4 pb-3" style="border-bottom: 2px solid #059669;">
                    <h3 style="margin:0;font-weight:700;color:#059669;">Alwahab Poultry</h3>
                    <p style="margin:2px 0;font-size:14px;">Add Qazia wah pul near Press club badin</p>
                    <p style="margin:2px 0;font-size:14px;">Prop Sajid Wahla &bull; Manger Asif Memon</p>
                    <p style="margin:2px 0;font-size:14px;">+92 333 2528003</p>
                </div>
                <div class="row mb-4">
                    <div class="col-6">
                        <small class="text-muted">Invoice</small>
                    </div>
                    <div class="col-6 text-end">
                        <h5 class="fw-bold"><?= htmlspecialchars($sale['invoice_no']) ?></h5>
                        <small>Date: <?= date('d M Y', strtotime($sale['sale_date'])) ?></small>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-6">
                        <strong>Customer:</strong><br>
                        <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?>
                        <?php if ($sale['customer_phone']): ?><br>Phone: <?= htmlspecialchars($sale['customer_phone']) ?><?php endif; ?>
                    </div>
                    <div class="col-6 text-end">
                        <strong>Payment:</strong><br>
                        <span class="badge bg-secondary"><?= ucfirst($sale['payment_method']) ?></span>
                    </div>
                </div>

                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Chicken Type</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Rate/KG</th>
                            <th class="text-end">Weight (KG)</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= htmlspecialchars($sale['chicken_type']) ?></td>
                            <td class="text-center fw-bold"><?= (int)$sale['birds_count'] ?></td>
                            <td class="text-end">Rs. <?= money($sale['rate_per_kg']) ?></td>
                            <td class="text-end"><?= number_format($sale['weight'], 3) ?></td>
                            <td class="text-end">Rs. <?= money($sale['amount']) ?></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Subtotal</td>
                            <td class="text-end">Rs. <?= money($sale['amount']) ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Discount</td>
                            <td class="text-end">Rs. <?= money($sale['discount']) ?></td>
                        </tr>
                        <tr class="table-active">
                            <td colspan="4" class="text-end fw-bold">Net Total</td>
                            <td class="text-end fw-bold">Rs. <?= money($sale['net_total']) ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Paid</td>
                            <td class="text-end">Rs. <?= money($sale['paid_amount']) ?></td>
                        </tr>
                        <tr class="<?= $sale['balance'] > 0 ? 'table-danger' : 'table-success' ?>">
                            <td colspan="4" class="text-end fw-bold">Balance</td>
                            <td class="text-end fw-bold">Rs. <?= money($sale['balance']) ?></td>
                        </tr>
                        <?php if ($sale['customer_id']): ?>
                        <tr>
                            <td colspan="4" class="text-end text-muted">Previous Balance</td>
                            <td class="text-end text-muted">Rs. <?= money($prev_balance) ?></td>
                        </tr>
                        <tr class="table-secondary">
                            <td colspan="4" class="text-end fw-bold">Total Outstanding</td>
                            <td class="text-end fw-bold">Rs. <?= money($prev_balance + $sale['balance']) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tfoot>
                </table>

                <div class="row mt-4">
                    <div class="col-6">
                        <small class="text-muted">Sold by: <?= htmlspecialchars($sale['created_by_name'] ?? '-') ?></small>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-muted">Thank you for your business!</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
