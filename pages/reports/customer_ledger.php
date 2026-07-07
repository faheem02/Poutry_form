<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = getDB();
$page_title = 'Customer Ledger Report';

$customer_id = (int)($_GET['customer_id'] ?? 0);
$customers = $pdo->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();

$entries = [];
$customer_name = '';
$running = 0;

if ($customer_id) {
    $c = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $c->execute([$customer_id]);
    $cust = $c->fetch();
    if ($cust) {
        $customer_name = $cust['name'];
        $running = (float)$cust['opening_balance'];
        $entries[] = ['date' => '0000-00-00', 'sort_id' => 0, 'particular' => 'Opening Balance', 'debit' => 0, 'credit' => 0, 'balance' => $running];

        $sales = $pdo->prepare("SELECT * FROM sales WHERE customer_id = ? ORDER BY sale_date, id");
        $sales->execute([$customer_id]);
        foreach ($sales->fetchAll() as $s) {
            $entries[] = ['date' => $s['sale_date'], 'sort_id' => (int)$s['id'], 'type' => 'sale', 'particular' => 'Sale Invoice: ' . $s['invoice_no'], 'debit' => (float)$s['net_total'], 'credit' => 0];
        }

        $payments = $pdo->prepare("SELECT * FROM payments WHERE customer_id = ? ORDER BY payment_date, id");
        $payments->execute([$customer_id]);
        foreach ($payments->fetchAll() as $p) {
            $entries[] = ['date' => $p['payment_date'], 'sort_id' => (int)$p['id'], 'type' => 'payment', 'particular' => 'Payment (' . ucfirst($p['payment_method']) . ')' . ($p['notes'] ? ': ' . $p['notes'] : ''), 'debit' => 0, 'credit' => (float)$p['amount']];
        }

        usort($entries, function($a, $b) {
            if ($a['date'] === '0000-00-00') return -1;
            if ($b['date'] === '0000-00-00') return 1;
            if ($a['date'] !== $b['date']) return strcmp($a['date'], $b['date']);
            $order = ['payment' => 0, 'sale' => 1];
            $oa = $order[$a['type']] ?? 2;
            $ob = $order[$b['type']] ?? 2;
            if ($oa !== $ob) return $oa <=> $ob;
            return ($a['sort_id'] ?? 0) <=> ($b['sort_id'] ?? 0);
        });

        $running = 0;
        foreach ($entries as &$e) {
            if ($e['date'] === '0000-00-00') { $running = $e['balance']; }
            else { $running += $e['debit'] - $e['credit']; $e['balance'] = $running; }
        }
        unset($e);
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
@media print {
    .no-print, .no-print * {
        display: none !important;
    }
    .card {
        border: 1px solid #ccc !important;
        box-shadow: none !important;
    }
    .card-header {
        background: #f8f9fa !important;
        border-bottom: 2px solid #333 !important;
    }
    .table {
        font-size: 11px !important;
    }
    .table thead th {
        background: #e9ecef !important;
        color: #000 !important;
        border-bottom: 2px solid #333 !important;
    }
    .table tbody tr {
        page-break-inside: avoid;
    }
    .text-success, .text-danger {
        color: #000 !important;
    }
    .badge {
        border: 1px solid #000 !important;
        background: #fff !important;
        color: #000 !important;
    }
    .table-responsive {
        overflow: visible !important;
    }
    .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate {
        display: none !important;
    }
}
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book me-1"></i> Customer Ledger Report</h1>
    <div>
        <button class="btn btn-sm btn-outline-success no-print" onclick="window.print()">
            <i class="fas fa-file-pdf me-1"></i> PDF
        </button>
    </div>
</div>

<div class="card mb-4 border-start-info no-print">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-auto">
                <select name="customer_id" class="form-select" required>
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $customer_id === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i> View</button></div>
        </form>
    </div>
</div>

<?php if ($customer_id && count($entries)): ?>
<div class="card border-start-primary">
    <div class="card-header fw-bold"><?= htmlspecialchars($customer_name) ?> — Balance: Rs. <?= money($running) ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 small">
                <thead class="table-light">
                    <tr><th>Date</th><th>Particular</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $e): ?>
                    <tr>
                        <td><?= $e['date'] === '0000-00-00' ? '—' : date('d M Y', strtotime($e['date'])) ?></td>
                        <td>
                            <?php if (($e['type'] ?? '') === 'sale'): ?>
                                <i class="fas fa-file-invoice text-warning me-1"></i>
                            <?php elseif (($e['type'] ?? '') === 'payment'): ?>
                                <i class="fas fa-money-bill-wave text-success me-1"></i>
                            <?php else: ?>
                                <i class="fas fa-balance-scale text-primary me-1"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($e['particular']) ?>
                        </td>
                        <td class="text-end text-danger"><?= $e['debit'] > 0 ? 'Rs. ' . money($e['debit']) : '-' ?></td>
                        <td class="text-end text-success"><?= $e['credit'] > 0 ? 'Rs. ' . money($e['credit']) : '-' ?></td>
                        <td class="text-end fw-bold <?= ($e['balance'] ?? 0) > 0 ? 'text-danger' : (($e['balance'] ?? 0) < 0 ? 'text-success' : 'text-muted') ?>">
                            Rs. <?= money(abs($e['balance'] ?? 0)) ?>
                            <?php if (($e['balance'] ?? 0) > 0): ?><small>(DR)</small>
                            <?php elseif (($e['balance'] ?? 0) < 0): ?><small>(CR)</small><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($customer_id): ?>
<div class="alert alert-info">No transactions found for this customer.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
