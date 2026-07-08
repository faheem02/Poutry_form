<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'POS Sale';

$types = $pdo->query("SELECT id, name FROM chicken_types ORDER BY name")->fetchAll();

// Default invoice
$invoice_no = generate_invoice_no();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-cash-register me-1"></i> Point of Sale
    </h1>
    <span class="badge bg-primary fs-6"><?= htmlspecialchars($invoice_no) ?></span>
</div>

<div class="row">
    <div class="col-lg-7 mb-4">
        <!-- Sale Form -->
        <div class="card">
            <div class="card-header"><i class="fas fa-shopping-cart me-1"></i> New Sale</div>
            <div class="card-body">
                <form id="saleForm">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="customer_id" value="">
                    <div class="col-md-4">
    <label class="form-label small fw-bold">Sale Date</label>
    <input type="date" name="sale_date" id="sale_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
</div>

                    <div class="row g-3">
                        <!-- Chicken Type -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Chicken Type</label>
                            <select name="chicken_type_id" id="chicken_type_id" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($types as $t):
                                    $stock = availableStock($t['id']);
                                ?>
                                <option value="<?= $t['id'] ?>" data-stock-birds="<?= (int)$stock['birds'] ?>" data-stock-weight="<?= (float)$stock['weight'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted" id="stockInfo"></small>
                        </div>

                        <!-- Rate Per KG -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Rate Per KG (Rs.)</label>
                            <input type="number" name="rate_per_kg" id="rate_per_kg" class="form-control form-control-lg fw-bold" step="0.01" min="0" placeholder="Auto-loads">
                        </div>

                        <!-- Payment Method -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Payment Method</label>
                            <select name="payment_method" id="payment_method" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank</option>
                                <option value="credit">Credit</option>
                            </select>
                        </div>

                        <!-- Quantity (Birds) -->
                        <!-- <div class="col-md-4">
                            <label class="form-label small fw-bold"> (Quantity)</label>
                            <input type="number" name="birds_count" id="birds_count" class="form-control form-control-lg" min="0" step="1" placeholder="0">
                        </div> -->

                        <!-- Weight -->
<!-- Weight in Man -->
<div class="col-md-4">
    <label class="form-label small fw-bold">Weight (Man)</label>
    <input type="number" name="weight_man" id="weight_man" class="form-control form-control-lg" step="0.01" min="0" placeholder="0">
</div>

<!-- Weight in KG (extra) -->
<div class="col-md-4">
    <label class="form-label small fw-bold">Weight (KG)</label>
    <input type="number" name="weight_kg_extra" id="weight_kg_extra" class="form-control form-control-lg" step="0.001" min="0" placeholder="0.000">
</div>

<!-- Total Net Weight in Man (auto) -->
<div class="col-md-4">
    <label class="form-label small fw-bold">Total Net Weight (Man)</label>
    <input type="text" id="total_weight_man" class="form-control form-control-lg fw-bold bg-light" readonly value="0.000">
</div>

<!-- Total Net Weight in KG (auto) -->
<div class="col-md-4">
    <label class="form-label small fw-bold">Total Net Weight (KG)</label>
    <input type="text" name="weight" id="weight" class="form-control form-control-lg fw-bold bg-light" readonly value="0.000">
</div>

                        <!-- Amount -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Amount (Rs.)</label>
                            <input type="number" name="amount" id="amount" class="form-control form-control-lg" step="0.01" min="0" placeholder="0.00">
                        </div>

                        <!-- Discount -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Discount (Rs.)</label>
                            <input type="number" name="discount" id="discount" class="form-control" step="0.01" min="0" value="0">
                        </div>

                        <!-- Net Total -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Net Total</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <span class="form-control form-control-lg fw-bold text-primary" id="net_total">0.00</span>
                            </div>
                        </div>

                        <!-- Balance -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Balance</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <span class="form-control form-control-lg fw-bold text-danger" id="balance">0.00</span>
                            </div>
                        </div>

                        <!-- Paid Amount -->
                        <div class="col-md-6 credit-sensitive">
                            <label class="form-label small fw-bold">Paid Amount (Rs.)</label>
                            <input type="number" name="paid_amount" id="paid_amount" class="form-control form-control-lg" step="0.01" min="0" value="0">
                        </div>

                        <!-- Customer -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Customer</label>
                            <input type="text" id="customer_search" class="form-control" placeholder="Search customer..." autocomplete="off">
                            <input type="hidden" name="customer_id" id="customer_id" value="">
                            <small class="text-muted" id="customer_name_display">Walk-in Customer</small>
                            <div class="list-group position-absolute" id="customer_results" style="z-index:1000;display:none;max-height:200px;overflow-y:auto;"></div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-4">
        <!-- Quick Summary -->
        <div class="card border-start-primary mb-3">
            <div class="card-body py-3">
                <div class="row text-center">
                    <div class="col-3">
                        <div class="text-xs fw-bold text-warning text-uppercase">Quantity</div>
                        <div class="h5 mb-0 fw-bold" id="display_birds">0</div>
                    </div>
                    <div class="col-3">
                        <div class="text-xs fw-bold text-primary text-uppercase">Rate/KG</div>
                        <div class="h5 mb-0 fw-bold" id="display_rate">0.00</div>
                    </div>
                    <div class="col-3">
                        <div class="text-xs fw-bold text-success text-uppercase">Weight</div>
                        <div class="h5 mb-0 fw-bold" id="display_weight">0.000</div>
                    </div>
                    <div class="col-3">
                        <div class="text-xs fw-bold text-info text-uppercase">Amount</div>
                        <div class="h5 mb-0 fw-bold" id="display_amount">0.00</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Pay Buttons -->
        <div class="card mb-3 credit-sensitive">
            <div class="card-header"><i class="fas fa-bolt me-1"></i> Quick Pay</div>
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-success quick-amount" data-amount="0">Rs. 0</button>
                    <button type="button" class="btn btn-outline-primary quick-amount" data-amount="500">Rs. 500</button>
                    <button type="button" class="btn btn-outline-primary quick-amount" data-amount="1000">Rs. 1,000</button>
                    <button type="button" class="btn btn-outline-primary quick-amount" data-amount="2000">Rs. 2,000</button>
                    <button type="button" class="btn btn-outline-primary quick-amount" data-amount="5000">Rs. 5,000</button>
                    <button type="button" class="btn btn-outline-success quick-amount" data-amount="10000">Rs. 10,000</button>
                    <button type="button" class="btn btn-outline-info quick-amount" id="payFullBtn">Pay Full</button>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <button type="button" id="saveSaleBtn" class="btn btn-primary btn-lg w-100 py-3 fw-bold">
            <i class="fas fa-save me-2"></i> Save Invoice
        </button>

        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-info-circle me-1"></i> Today's Rates</div>
            <div class="card-body py-2" id="todayRates">
                <p class="text-muted small mb-0">Select chicken type to see rate.</p>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/pos.js"></script>
<script>
    // Auto-calculate Total Net Weight (Man & KG)
function calculateTotalWeight() {
    const man = parseFloat($('#weight_man').val()) || 0;
    const kg  = parseFloat($('#weight_kg_extra').val()) || 0;

    const totalKg = (man * 40) + kg;       // 1 Man = 40 KG
    const totalMan = totalKg / 40;

    $('#weight').val(totalKg.toFixed(3));           // hidden/total KG field used by pos.js for amount calc
    $('#total_weight_man').val(totalMan.toFixed(3));

    $('#weight').trigger('input');  // taake pos.js ka existing amount-calculation trigger ho jaye
}

$('#weight_man, #weight_kg_extra').on('input', calculateTotalWeight);

// Load today's rates sidebar
$.get(BASE_URL + '/pages/pos/pos_ajax.php', { action: 'today_rates' }, function (res) {
    if (res.length) {
        let html = '';
        res.forEach(function (r) {
            html += '<div class="d-flex justify-content-between"><span>' + r.name + '</span><span class="fw-bold text-primary">Rs. ' + parseFloat(r.rate).toFixed(2) + '</span></div>';
        });
        $('#todayRates').html(html);
    }
}, 'json');
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>