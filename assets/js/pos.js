$(document).ready(function () {

    let currentRate = 0;

    // ---------------------- TWO-WAY CALCULATOR ----------------------
    const $rateInput    = $('#rate_per_kg');
    const $weightInput  = $('#weight');
    const $amountInput  = $('#amount');
    const $discountInput = $('#discount');
    const $netTotalEl   = $('#net_total');
    const $paidInput    = $('#paid_amount');
    const $balanceEl    = $('#balance');

    function rateKg() {
        return (parseFloat($rateInput.val()) || 0) / 40;
    }

    function recalculate() {
        const rateKg_ = rateKg();
        const rateMan = parseFloat($rateInput.val()) || 0;
        const weight   = parseFloat($weightInput.val()) || 0;
        const amount   = parseFloat($amountInput.val()) || 0;
        const discount = parseFloat($discountInput.val()) || 0;
        const paid     = parseFloat($paidInput.val()) || 0;

        let netTotal = 0;

        if (rateKg_ > 0 && weight > 0) {
            netTotal = (weight * rateKg_) - discount;
        } else if (amount > 0) {
            netTotal = amount - discount;
        }

        if (netTotal < 0) netTotal = 0;
        let balance = netTotal - paid;
        if (balance < 0) balance = 0;

        $netTotalEl.text('Rs. ' + netTotal.toFixed(2));
        $balanceEl.text('Rs. ' + balance.toFixed(2));

        // Update sidebar summary
        $('#display_rate').text(rateMan.toFixed(2));
        $('#display_weight').text(weight.toFixed(3));
        $('#display_amount').text(netTotal.toFixed(2));
    }

    // Weight changed -> calculate and fill Amount
    $weightInput.on('input', function () {
        const rateKg_ = rateKg();
        const weight = parseFloat(this.value) || 0;
        if (rateKg_ > 0 && weight > 0) {
            $amountInput.val((weight * rateKg_).toFixed(2));
        } else {
            $amountInput.val('');
        }
        recalculate();
    });

    // Amount changed -> calculate and fill Weight
    $amountInput.on('input', function () {
        const rateKg_ = rateKg();
        const amount = parseFloat(this.value) || 0;
        if (rateKg_ > 0 && amount > 0) {
            $weightInput.val((amount / rateKg_).toFixed(3));
        } else {
            $weightInput.val('');
        }
        recalculate();
    });

    // Rate changed -> if weight is already filled, recalculate amount;
    // if amount is filled but no weight, recalculate weight
    $rateInput.on('input', function () {
        const rateKg_ = rateKg();
        const weight = parseFloat($weightInput.val()) || 0;
        const amount = parseFloat($amountInput.val()) || 0;
        if (rateKg_ > 0 && weight > 0) {
            $amountInput.val((weight * rateKg_).toFixed(2));
        } else if (rateKg_ > 0 && amount > 0) {
            $weightInput.val((amount / rateKg_).toFixed(3));
        }
        recalculate();
    });

    $discountInput.on('input', recalculate);
    $paidInput.on('input', recalculate);

    // Payment method change -> hide paid amount on credit
    $('#payment_method').on('change', function () {
        const isCredit = this.value === 'credit';
        $('.credit-sensitive').toggle(!isCredit);
        if (isCredit) {
            $paidInput.val('0');
            recalculate();
        }
    });

    // ---------------------- LOAD TODAY'S RATE ----------------------
    function loadTodayRate(chickenTypeId) {
        if (!chickenTypeId) {
            $('#stockInfo').text('').hide();
            return;
        }
        $.ajax({
            url: BASE_URL + '/pages/pos/pos_ajax.php',
            method: 'GET',
            data: { action: 'get_rate', chicken_type_id: chickenTypeId },
            dataType: 'json',
            success: function (res) {
                // Always update stock info regardless of rate
                const weight = parseFloat(res.weight) || 0;
                const $info  = $('#stockInfo');

                $info.text('Available: ' + weight.toFixed(2) + ' KG');
                $info.removeClass('text-danger text-muted text-success');
                if (weight <= 0) {
                    $info.addClass('text-danger');
                } else {
                    $info.addClass('text-success');
                }
                $info.show();

                // Store on the selected option so weight validation can read it
                $('#chicken_type_id').find(':selected')
                    .data('stock-weight', weight);

                if (res.success) {
                    currentRate = parseFloat(res.rate); // per-KG from server
                    const rateMan = currentRate * 40;
                    $rateInput.val(rateMan.toFixed(2));
                    const w = parseFloat($weightInput.val()) || 0;
                    const a = parseFloat($amountInput.val()) || 0;
                    if (currentRate > 0 && w > 0) {
                        $amountInput.val((w * currentRate).toFixed(2));
                    } else if (currentRate > 0 && a > 0) {
                        $weightInput.val((a / currentRate).toFixed(3));
                    }
                    recalculate();
                    $('#display_rate').text(rateMan.toFixed(2));
                } else {
                    $rateInput.val('');
                    showError('No rate set for today for this chicken type. Please set a rate first.');
                }
            }
        });
    }

    // On chicken type change
    $('#chicken_type_id').on('change', function () {
        loadTodayRate($(this).val());
    });

    // Validate stock on weight input
    $weightInput.on('input', function () {
        const weight = parseFloat(this.value) || 0;
        const avail = parseFloat($('#chicken_type_id').find(':selected').data('stock-weight')) || 0;
        const $info = $('#stockInfo');
        if (weight > 0 && (avail <= 0 || weight > avail)) {
            $info.addClass('text-danger').removeClass('text-muted');
            $('#saveSaleBtn').prop('disabled', true);
        } else {
            $info.removeClass('text-danger').addClass('text-muted');
            $('#saveSaleBtn').prop('disabled', false);
        }
    });

    // Load rate on page load
    const initialType = $('#chicken_type_id').val();
    if (initialType) {
        loadTodayRate(initialType);
    }

    // ---------------------- CUSTOMER SEARCH ----------------------
    $('#customer_search').on('keyup', function () {
        const q = $(this).val();
        if (q.length < 1) return;
        $.ajax({
            url: BASE_URL + '/pages/pos/pos_ajax.php',
            method: 'GET',
            data: { action: 'search_customer', q: q },
            dataType: 'json',
            success: function (res) {
                const $list = $('#customer_results');
                $list.empty();
                if (res.length) {
                    res.forEach(function (c) {
                        $list.append(
                            '<a href="#" class="list-group-item list-group-item-action customer-item" data-id="' + c.id + '" data-name="' + c.name + '">' +
                            c.name + ' (' + c.phone + ')' +
                            '<span class="float-end text-muted small">Bal: ' + c.balance + '</span>' +
                            '</a>'
                        );
                    });
                    $list.show();
                } else {
                    $list.hide();
                }
            }
        });
    });

    $(document).on('click', '.customer-item', function (e) {
        e.preventDefault();
        const id   = $(this).data('id');
        const name = $(this).data('name');
        $('#customer_id').val(id);
        $('#customer_name_display').text(name);
        $('#customer_results').hide();
        $('#customer_search').val(name);
    });

    // ---------------------- SAVE SALE ----------------------
    $('#saveSaleBtn').on('click', function () {
        const data = {
            action: 'save_sale',
            csrf_token: $('#csrf_token').val(),
            customer_id: $('#customer_id').val(),
            chicken_type_id: $('#chicken_type_id').val(),
            rate_per_kg: rateKg() || 0,
            weight: $weightInput.val(),
            amount: $amountInput.val(),
            discount: $discountInput.val() || 0,
            net_total: $netTotalEl.text().replace('Rs. ', '').replace(/,/g, ''),
            paid_amount: $paidInput.val() || 0,
            balance: $balanceEl.text().replace('Rs. ', '').replace(/,/g, ''),
            payment_method: $('#payment_method').val() || 'cash',
            sale_date: $('input[name="sale_date"]').val(),
        };

        if (!data.chicken_type_id) { showError('Please select chicken type.'); return; }
        if (!data.weight || parseFloat(data.weight) <= 0) { showError('Please enter weight.'); return; }
        const availWeight = parseFloat($('#chicken_type_id').find(':selected').data('stock-weight')) || 0;
        if (availWeight < parseFloat(data.weight)) {
            showError('Insufficient stock! Available: ' + availWeight.toFixed(2) + ' KG, Required: ' + parseFloat(data.weight).toFixed(2) + ' KG.');
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: BASE_URL + '/pages/pos/pos_ajax.php',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sale Complete!',
                        text: 'Invoice: ' + res.invoice_no,
                        showCancelButton: true,
                        confirmButtonText: '<i class="fas fa-print"></i> Print Invoice',
                        cancelButtonText: 'New Sale',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.open(BASE_URL + '/pages/sales/invoice.php?id=' + res.sale_id, '_blank');
                        }
                        resetPOS();
                    });
                } else {
                    showError(res.message || 'Failed to save sale.');
                }
            },
            error: function () {
                showError('Server error. Please try again.');
            },
            complete: function () {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Invoice');
            }
        });
    });

    // ---------------------- RESET POS ----------------------
    function resetPOS() {
        $weightInput.val('');
        $amountInput.val('');
        $discountInput.val('0');
        $paidInput.val('0');
        $netTotalEl.text('0.00');
        $balanceEl.text('0.00');
        $('#customer_id').val('');
        $('#customer_name_display').text('Walk-in Customer');
        $('#customer_search').val('');
        $('#payment_method').val('cash');
        $('.credit-sensitive').show();
        loadTodayRate($('#chicken_type_id').val());
    }

    // ---------------------- QUICK AMOUNT BUTTONS ----------------------
    $('.quick-amount').on('click', function () {
        const val = $(this).data('amount');
        $paidInput.val(val);
        recalculate();
    });

});
