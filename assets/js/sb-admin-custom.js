$(document).ready(function () {

    // Wrap any table not already in a horizontal-scroll container so wide
    // tables scroll inside their card instead of widening the whole page on
    // mobile. Runs before DataTables init; tables already inside
    // .table-responsive (or a DataTables wrapper) are left alone.
    $('table').each(function () {
        var $t = $(this);
        if ($t.closest('.table-responsive, .dataTables_wrapper').length === 0) {
            $t.wrap('<div class="table-responsive"></div>');
        }
    });

    // Sidebar toggle
    $('#sidebarToggle, #sidebarToggleTop, #sidebarCloseBtn').on('click', function () {
        $('body').toggleClass('sidebar-toggled');
    });

    // Close sidebar when clicking backdrop (mobile)
    $(document).on('click', '#sidebarBackdrop', function () {
        $('body').removeClass('sidebar-toggled');
    });

    // Collapse toggle handler
    $(document).on('click', '#accordionSidebar .nav-link[data-bs-toggle="collapse"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        var target = $(this).data('bs-target');
        if (!target) return;

        // Desktop icon-only: expand sidebar to show dropdown
        if ($(window).width() >= 769 && $('body').hasClass('sidebar-toggled')) {
            $('#accordionSidebar').addClass('sidebar-expanded');
        }

        // Use Bootstrap Collapse API
        $(target).collapse('toggle');
    });
    $(document).on('mouseleave', '#accordionSidebar', function () {
        $('#accordionSidebar').removeClass('sidebar-expanded');
    });

    // Mobile: close sidebar when clicking a direct nav link (not collapse toggle)
    $(document).on('click', '#accordionSidebar .nav-link', function () {
        if ($(window).width() < 769 && !$(this).attr('data-bs-toggle')) {
            $('body').removeClass('sidebar-toggled');
        }
    });

    // Ensure sidebar starts at full width on desktop
    $(window).resize(function () {
        if ($(window).width() >= 769) {
            $('body').removeClass('sidebar-toggled');
        }
    }).trigger('resize');

    // DataTables
    if ($.fn.dataTable) {
        $('.datatable').DataTable({
            pageLength: 25,
            language: {
                search: '<i class="fas fa-search me-1"></i>',
                searchPlaceholder: 'Search...',
                lengthMenu: '_MENU_ per page',
                info: 'Showing _START_ to _END_ of _TOTAL_',
            },
            dom: '<"row align-items-center mb-3"<"col-sm-6"l><"col-sm-6"f>>tip',
            stateSave: true,
        });
    }

    // Auto-dismiss alerts
    window.setTimeout(function () {
        $('.alert').fadeOut(500);
    }, 4000);

    // Delete confirmation with SweetAlert2
    $(document).on('click', '.btn-delete', function (e) {
        e.preventDefault();
        const link = $(this).attr('href');
        const text = $(this).data('text') || 'This action cannot be undone.';
        Swal.fire({
            title: 'Are you sure?',
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = link;
            }
        });
    });

    // Flash message helper
    window.showSuccess = function (msg) {
        Swal.fire({ icon: 'success', title: 'Success!', text: msg, timer: 2000, showConfirmButton: false });
    };

    window.showError = function (msg) {
        Swal.fire({ icon: 'error', title: 'Error!', text: msg });
    };

});
