<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$page_title = 'Reports';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-bar me-1"></i> Reports</h1>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <a href="daily_sales.php" class="text-decoration-none">
            <div class="card border-start-primary dashboard-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                    <h5 class="fw-bold">Daily Sales Report</h5>
                    <p class="text-muted small mb-0">View sales with date filters, charts, and type-wise breakdown</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 mb-4">
        <a href="profit_loss.php" class="text-decoration-none">
            <div class="card border-start-success dashboard-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-coins fa-3x text-success mb-3"></i>
                    <h5 class="fw-bold">Profit & Loss</h5>
                    <p class="text-muted small mb-0">Revenue, COGS, expenses and net profit calculation</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 mb-4">
        <a href="customer_ledger.php" class="text-decoration-none">
            <div class="card border-start-info dashboard-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-users fa-3x text-info mb-3"></i>
                    <h5 class="fw-bold">Customer Ledger</h5>
                    <p class="text-muted small mb-0">Customer-wise debit/credit/balance report</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 mb-4">
        <a href="supplier_ledger.php" class="text-decoration-none">
            <div class="card border-start-warning dashboard-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-truck fa-3x text-warning mb-3"></i>
                    <h5 class="fw-bold">Supplier Ledger</h5>
                    <p class="text-muted small mb-0">Supplier purchase history and totals</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 mb-4">
        <a href="stock_report.php" class="text-decoration-none">
            <div class="card border-start-danger dashboard-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-warehouse fa-3x text-danger mb-3"></i>
                    <h5 class="fw-bold">Stock Report</h5>
                    <p class="text-muted small mb-0">Complete stock ledger with availability</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 mb-4">
        <a href="expense_report.php" class="text-decoration-none">
            <div class="card border-start-info dashboard-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-coins fa-3x text-info mb-3"></i>
                    <h5 class="fw-bold">Expense Report</h5>
                    <p class="text-muted small mb-0">Expense breakdown by category with chart</p>
                </div>
            </div>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
