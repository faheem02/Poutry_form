<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$page_title = 'Chicken Rates';

// Create / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('CSRF validation failed');

    if ($_POST['action'] === 'save') {
        $chicken_type_id = (int)$_POST['chicken_type_id'];
        $rate_per_man    = (float)$_POST['rate_per_man'];
        $rate_per_kg     = $rate_per_man / 40;
        $rate_date       = $_POST['rate_date'] ?: date('Y-m-d');

        if ($chicken_type_id && $rate_per_kg > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO chicken_rates (chicken_type_id, rate_per_kg, rate_date, created_by)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE rate_per_kg = VALUES(rate_per_kg), created_by = VALUES(created_by)
            ");
            $stmt->execute([$chicken_type_id, $rate_per_kg, $rate_date, $_SESSION['user_id']]);
            setFlash('Rate saved successfully.');
        } else {
            setFlash('Please select chicken type and enter valid rate.');
        }
    } elseif ($_POST['action'] === 'update') {
        $id          = (int)$_POST['id'];
        $rate_per_man = (float)$_POST['rate_per_man'];
        $rate_per_kg = $rate_per_man / 40;
        $rate_date   = $_POST['rate_date'] ?: date('Y-m-d');
        if ($id && $rate_per_kg > 0) {
            $stmt = $pdo->prepare("UPDATE chicken_rates SET rate_per_kg = ?, rate_date = ?, created_by = ? WHERE id = ?");
            $stmt->execute([$rate_per_kg, $rate_date, $_SESSION['user_id'], $id]);
            setFlash('Rate updated successfully.');
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM chicken_rates WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('Rate deleted successfully.');
    }
    header('Location: ' . BASE_URL . '/pages/chicken_rates/index.php');
    exit;
}

$types = $pdo->query("SELECT id, name FROM chicken_types ORDER BY name")->fetchAll();

// Get today's rate for each type
$today_rates = [];
$stmt = $pdo->query("SELECT cr.*, ct.name AS type_name
    FROM chicken_rates cr
    JOIN chicken_types ct ON ct.id = cr.chicken_type_id
    WHERE cr.rate_date = CURDATE()
    ORDER BY ct.name");
while ($row = $stmt->fetch()) {
    $today_rates[$row['chicken_type_id']] = $row;
}

// All rates history
$all_rates = $pdo->query("
    SELECT cr.*, ct.name AS type_name, u.username AS created_by_name
    FROM chicken_rates cr
    JOIN chicken_types ct ON ct.id = cr.chicken_type_id
    LEFT JOIN users u ON u.id = cr.created_by
    ORDER BY cr.rate_date DESC, ct.name
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ===== CHICKEN RATES PAGE STYLES ===== */
.rates-page {
    background: #f8fafc;
    min-height: calc(100vh - 200px);
    padding: 20px 0;
}

/* Cards */
.rates-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid #e9ecef;
    margin-bottom: 20px;
}

.rates-card .card-header {
    background: #ffffff;
    border-bottom: 1px solid #e9ecef;
    padding: 15px 20px;
    font-weight: 600;
    color: #2d3748;
    font-size: 16px;
}

.rates-card .card-body {
    padding: 20px;
}

/* Today's Rate Cards */
.rate-today-card {
    background: #ffffff;
    border-radius: 12px;
    border: 2px solid #e9ecef;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.rate-today-card:hover {
    border-color: #059669;
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(5, 150, 105, 0.1);
}

.rate-today-card .chicken-icon {
    font-size: 28px;
    color: #059669;
    margin-bottom: 8px;
}

.rate-today-card .chicken-name {
    font-size: 14px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 6px;
}

.rate-today-card .rate-value {
    font-size: 24px;
    font-weight: 700;
    color: #059669;
}

.rate-today-card .rate-label {
    font-size: 12px;
    color: #718096;
}

.rate-today-card .not-set {
    font-size: 14px;
    color: #a0aec0;
    font-weight: 500;
}

.rate-today-card .status-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 10px;
    padding: 3px 10px;
    border-radius: 50px;
    font-weight: 600;
}

.rate-today-card .status-badge.set {
    background: #f0fdf4;
    color: #059669;
}

.rate-today-card .status-badge.not-set {
    background: #fef2f2;
    color: #dc2626;
}

/* Table Styles */
.rates-table thead {
    background: #f7fafc;
}

.rates-table th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #718096;
    font-weight: 600;
    border: none;
    padding: 12px 15px;
}

.rates-table td {
    padding: 12px 15px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

.rates-table tr:hover td {
    background: #f7fafc;
}

.rates-table .rate-amount {
    font-weight: 700;
    color: #059669;
    font-size: 16px;
}

/* Buttons */
.btn-primary-custom {
    background: #059669;
    border: none;
    border-radius: 8px;
    padding: 10px 24px;
    font-weight: 600;
    color: #fff;
    transition: all 0.3s ease;
}

.btn-primary-custom:hover {
    background: #047857;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(5, 150, 105, 0.3);
    color: #fff;
}

.btn-outline-primary-custom {
    background: transparent;
    border: 2px solid #059669;
    border-radius: 8px;
    padding: 8px 20px;
    font-weight: 600;
    color: #059669;
    transition: all 0.3s ease;
}

.btn-outline-primary-custom:hover {
    background: #059669;
    color: #fff;
    transform: translateY(-2px);
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-icon.edit {
    background: #ebf5ff;
    color: #3b82f6;
}

.btn-icon.edit:hover {
    background: #3b82f6;
    color: #fff;
}

.btn-icon.delete {
    background: #fef2f2;
    color: #ef4444;
}

.btn-icon.delete:hover {
    background: #ef4444;
    color: #fff;
}

/* Modal Styles */
.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

.modal-header {
    border-bottom: 1px solid #e9ecef;
    padding: 20px 25px;
}

.modal-header .modal-title {
    font-weight: 700;
    color: #2d3748;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    border-top: 1px solid #e9ecef;
    padding: 15px 25px;
}

/* Responsive */
@media (max-width: 768px) {
    .rate-today-card .rate-value {
        font-size: 20px;
    }
    .rate-today-card .chicken-icon {
        font-size: 22px;
    }
    .rates-card .card-header {
        padding: 12px 15px;
        font-size: 14px;
    }
    .rates-card .card-body {
        padding: 15px;
    }
}
</style>

<!-- ===== PAGE CONTENT ===== -->
<div class="container-fluid rates-page">

    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h4 class="fw-bold text-gray-800 mb-0">
                <i class="fas fa-tags text-success me-2"></i>Chicken Rates
            </h4>
            <small class="text-muted">Manage chicken rate prices</small>
        </div>
        <div class="col-md-4 text-md-end mt-2 mt-md-0">
            <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#rateModal">
                <i class="fas fa-plus me-2"></i> Set Today's Rate
            </button>
        </div>
    </div>

    <!-- Today's Rates Cards -->
    <div class="rates-card">
        <div class="card-header">
            <i class="fas fa-calendar-day text-success me-2"></i> Today's Rates
            <span class="badge bg-success bg-opacity-10 text-success ms-2"><?= date('d M Y') ?></span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($types as $t): ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="rate-today-card">
                        <?php if (isset($today_rates[$t['id']])): ?>
                            <span class="status-badge set"><i class="fas fa-check-circle me-1"></i> Set</span>
                            <div class="chicken-icon"><i class="fas fa-drumstick"></i></div>
                            <div class="chicken-name"><?= htmlspecialchars($t['name']) ?></div>
                            <div class="rate-value">Rs. <?= money($today_rates[$t['id']]['rate_per_kg'] * 40) ?></div>
                            <div class="rate-label">per Man</div>
                        <?php else: ?>
                            <span class="status-badge not-set"><i class="fas fa-times-circle me-1"></i> Not Set</span>
                            <div class="chicken-icon" style="color: #cbd5e0;"><i class="fas fa-drumstick"></i></div>
                            <div class="chicken-name"><?= htmlspecialchars($t['name']) ?></div>
                            <div class="not-set">No rate set</div>
                            <div class="rate-label mt-1">
                                <button class="btn btn-sm btn-outline-primary-custom mt-2" data-bs-toggle="modal" data-bs-target="#rateModal" 
                                        onclick="document.querySelector('#rateModal select[name=chicken_type_id]').value='<?= $t['id'] ?>'">
                                    <i class="fas fa-plus me-1"></i> Set Rate
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Rate History -->
    <div class="rates-card">
        <div class="card-header">
            <i class="fas fa-history text-success me-2"></i> Rate History
            <span class="badge bg-primary bg-opacity-10 text-primary ms-2"><?= count($all_rates) ?> Records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table rates-table mb-0" id="ratesTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Chicken Type</th>
                            <th>Rate / Man</th>
                            <th>Set By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($all_rates) > 0): ?>
                            <?php foreach ($all_rates as $r): ?>
                            <tr>
                                <td>
                                    <span class="fw-medium"><?= date('d M Y', strtotime($r['rate_date'])) ?></span>
                                    <?php if ($r['rate_date'] == date('Y-m-d')): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success ms-1">Today</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars($r['type_name']) ?></span>
                                </td>
                                <td>
                                    <span class="rate-amount">Rs. <?= money($r['rate_per_kg'] * 40) ?></span>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        <i class="fas fa-user-circle me-1"></i>
                                        <?= htmlspecialchars($r['created_by_name'] ?? 'System') ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button type="button" class="btn-icon edit btn-edit-rate"
                                            data-id="<?= $r['id'] ?>"
                                            data-type="<?= htmlspecialchars($r['type_name']) ?>"
                                            data-rate="<?= $r['rate_per_kg'] * 40 ?>"
                                            data-date="<?= $r['rate_date'] ?>"
                                            title="Edit Rate">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <form method="POST" class="delete-rate-form d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <button type="button" class="btn-icon delete btn-delete-rate" title="Delete Rate">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3" style="color: #cbd5e0;"></i>
                                    <p class="text-muted mb-0">No rates have been set yet</p>
                                    <button class="btn btn-primary-custom mt-3" data-bs-toggle="modal" data-bs-target="#rateModal">
                                        <i class="fas fa-plus me-2"></i> Set First Rate
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ===== SET RATE MODAL ===== -->
<div class="modal fade" id="rateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle text-success me-2"></i>Set Chicken Rate
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Chicken Type <span class="text-danger">*</span></label>
                    <select name="chicken_type_id" class="form-select form-select-lg" required>
                        <option value="">-- Select Chicken Type --</option>
                        <?php foreach ($types as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Rate Per Man (Rs.) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-2 border-end-0">Rs.</span>
                        <input type="number" name="rate_per_man" class="form-control form-control-lg" step="0.01" min="0" required placeholder="Enter rate" style="border-left:0; font-weight:600;">
                    </div>
                    <small class="text-muted">Enter rate per Man (1 Man = 40 KG)</small>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Effective Date</label>
                    <input type="date" name="rate_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary-custom">
                    <i class="fas fa-save me-2"></i> Save Rate
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== EDIT RATE MODAL ===== -->
<div class="modal fade" id="editRateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit text-primary me-2"></i>Edit Rate — <span id="edit_type_label" class="text-success"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Rate Per Man (Rs.) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-2 border-end-0">Rs.</span>
                        <input type="number" name="rate_per_man" id="edit_rate_per_kg" class="form-control form-control-lg" step="0.01" min="0" required style="border-left:0; font-weight:600;">
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Date</label>
                    <input type="date" name="rate_date" id="edit_rate_date" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary-custom">
                    <i class="fas fa-save me-2"></i> Update Rate
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== JAVASCRIPT ===== -->
<script>
$(document).ready(function() {

    // Edit Rate Button
    $(document).on('click', '.btn-edit-rate', function () {
        $('#edit_id').val($(this).data('id'));
        $('#edit_type_label').text($(this).data('type'));
        $('#edit_rate_per_kg').val(parseFloat($(this).data('rate')).toFixed(2));
        $('#edit_rate_date').val($(this).data('date'));
        new bootstrap.Modal(document.getElementById('editRateModal')).show();
    });

    // Delete Rate Button with SweetAlert
    $(document).on('click', '.btn-delete-rate', function () {
        const form = $(this).closest('form');
        Swal.fire({
            title: 'Delete Rate?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'rounded-3'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // Auto-select type in modal when "Set Rate" clicked from card
    $('#rateModal').on('show.bs.modal', function(e) {
        // If a button triggered this, it might have set the value
        var select = $(this).find('select[name=chicken_type_id]');
        if (select.val() === null || select.val() === '') {
            select.val('').trigger('change');
        }
    });

});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>