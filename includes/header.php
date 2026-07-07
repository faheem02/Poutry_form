<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poultry Shop POS - <?= $page_title ?? 'Dashboard' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sb-admin-custom.css" rel="stylesheet">
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <style>
        /* ---------- Global Print Styles ---------- */
        @media print {
            #accordionSidebar, .topbar, .sidebar-brand, #sidebarToggle, #sidebarToggleTop,
            .navbar, nav, .breadcrumb, .no-print, .no-print * {
                display: none !important;
            }
            #wrapper, #content-wrapper, #content, .container-fluid {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
        }
        /* Sticky Topbar Styles */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 1030;
            background: white !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08) !important;
            transition: all 0.3s ease;
            padding: 0.5rem 1.5rem;
        }
        
        /* Add shadow on scroll */
        .topbar.scrolled {
            box-shadow: 0 4px 20px rgba(0,0,0,0.12) !important;
        }
        
        /* Ensure content doesn't hide behind sticky header */
        #content {
            padding-top: 0;
        }
        
        /* Smooth transition for the header */
        .topbar .navbar-brand,
        .topbar .nav-link,
        .topbar .dropdown-toggle {
            transition: all 0.3s ease;
        }
        
        /* Add a subtle background blur effect when scrolling */
        .topbar.scrolled {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.98) !important;
        }
        
        /* Topbar brand text */
        .topbar .navbar-brand {
            font-weight: 600;
            color: #059669;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .topbar {
                padding: 0.35rem 0.75rem;
            }
            
            .topbar .d-none.d-sm-inline-block {
                display: none !important;
            }
        }
        @media (max-width: 576px) {
            .topbar #sidebarToggleTop {
                padding: 0.25rem;
                margin-right: 0.5rem !important;
            }
            #sidebarToggleTop i {
                font-size: 1rem;
            }
        }
        
        /* Optional: Add a progress bar at top of header */
        .topbar-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(to right, #059669, #10b981);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }
        
        .topbar.scrolled .topbar-progress {
            transform: scaleX(1);
        }

        /* Center the sidebar toggle button */
#accordionSidebar .text-center.d-none.d-md-block {
    text-align: center !important;
}

#sidebarToggle {
    display: inline-block !important;
    margin: 0 auto !important;
    float: none !important;
}

/* Ensure the parent container allows centering */
.sidebar-inner {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Push the toggle to the bottom and center it */
#accordionSidebar .text-center.d-none.d-md-block {
    margin-top: auto;
    padding: 1rem 0;
    width: 100%;
}

/* ---------- Print Header ---------- */
@media screen {
    .print-header {
        display: none !important;
    }
}
.print-header {
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #333;
    padding-bottom: 15px;
    padding-top: 10px;
}
.print-header h2 {
    margin: 0 0 5px;
    font-size: 22px;
    font-weight: bold;
}
.print-header p {
    margin: 2px 0;
    font-size: 13px;
}
@media print {
    .print-header {
        display: block !important;
    }
}
    </style>
</head>
<body id="page-top">

<?php date_default_timezone_set('Asia/Karachi'); ?>

<div class="print-header">
    <h2>Alwahab Poultry</h2>
    <p>Add Qazia wah pul near Press club badin</p>
    <p>Prop Sajid Wahla &bull; Manger Asif Memon</p>
    <p>+92 333 2528003</p>
</div>

<div id="wrapper">

<?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <!-- Topbar - NOW STICKY -->
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow" id="stickyTopbar">
                <button id="sidebarToggleTop" class="btn btn-link rounded-circle me-3">
                    <i class="fa fa-bars"></i>
                </button>

                <div class="d-none d-sm-inline-block fw-bold text-muted small">
                    <i class="fas fa-calendar-alt me-1"></i> <?= date('l, d M Y') ?>
                </div> 

                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown no-arrow">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <span class="me-2 text-gray-600 small">
                                <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                                <span class="badge bg-<?= isAdmin() ? 'danger' : 'primary' ?> ms-1"><?= ucfirst($_SESSION['user_role'] ?? '') ?></span>
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in">
                            <a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">
                                <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout
                            </a>
                        </div>
                    </li>
                </ul>
                
                <!-- Optional: Progress bar effect -->
                <div class="topbar-progress"></div>
            </nav>
            <!-- End Topbar -->

            <!-- Begin Page Content -->
            <div class="container-fluid">
<?php
$flash = flashMessage();
if ($flash):
?>
<div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
    <i class="fas fa-check-circle me-2"></i> <?= $flash ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>