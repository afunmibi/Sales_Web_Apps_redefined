<?php
session_start();
// Use your existing db_connect.php which sets up $conn
require_once 'db_connect.php';

// 1. Authentication Check
if (!isset($_SESSION['username'])) {
    // If not logged in, redirect to login page
    header("Location: index.php");
    exit;
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];
$roleTitle = ucfirst($role); // Capitalize the first letter of the role for display

$dashboard_summary = [
    'today_sales_count' => 0,
    'today_sales_amount' => 0.00,
    'low_stock_items_count' => 0
];

// --- Fetch Today's Sales ---
$today_sales_query = "";
if ($role === 'cashier') {
    $today_sales_query = "SELECT COUNT(sale_id) as count, SUM(grand_total) as total FROM sales WHERE cashier_id = ? AND DATE(sale_date) = CURDATE()";
    
    if ($stmt = $conn->prepare($today_sales_query)) {
        $cashier_id = $_SESSION['user_id'] ?? null;
        if ($cashier_id) {
            $stmt->bind_param("i", $cashier_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $today_sales_data = $result->fetch_assoc();
            $stmt->close();
        } else {
            error_log("Cashier ID not found in session for sales query.");
            $today_sales_data = ['count' => 0, 'total' => 0.00];
        }
    } else {
        error_log("MySQLi Prepare failed for cashier sales: " . $conn->error);
        $today_sales_data = ['count' => 0, 'total' => 0.00];
    }
} else {
    $today_sales_query = "SELECT COUNT(sale_id) as count, SUM(grand_total) as total FROM sales WHERE DATE(sale_date) = CURDATE()";
    
    if ($stmt = $conn->prepare($today_sales_query)) {
        $stmt->execute();
        $result = $stmt->get_result();
        $today_sales_data = $result->fetch_assoc();
        $stmt->close();
    } else {
        error_log("MySQLi Prepare failed for overall sales: " . $conn->error);
        $today_sales_data = ['count' => 0, 'total' => 0.00];
    }
}

$dashboard_summary['today_sales_count'] = $today_sales_data['count'] ?? 0;
$dashboard_summary['today_sales_amount'] = $today_sales_data['total'] ?? 0.00;

// --- Fetch Low Stock Items (for manager/admin roles) ---
if (in_array($role, ['manager', 'admin'])) {
    $low_stock_query = "SELECT COUNT(id) as count FROM products WHERE stock_quantity < 10";
    if ($stmt = $conn->prepare($low_stock_query)) {
        $stmt->execute();
        $result = $stmt->get_result();
        $low_stock_count_data = $result->fetch_assoc();
        $dashboard_summary['low_stock_items_count'] = $low_stock_count_data['count'] ?? 0;
        $stmt->close();
    } else {
        error_log("MySQLi Prepare failed for low stock: " . $conn->error);
        $dashboard_summary['low_stock_items_count'] = 0;
    }
}

// Close the main connection when done
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($roleTitle); ?> Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: #343a40;
        }

        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1000;
            padding: 20px 0;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #212529;
            width: 250px;
            overflow-y: auto;
            overflow-x: hidden;
            transition: transform 0.3s ease-in-out;
        }

        .sidebar .nav-link {
            color: #e9ecef;
            transition: background-color 0.2s ease, color 0.2s ease;
            padding: 10px 15px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }

        .sidebar .nav-link svg {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            stroke-width: 2;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .sidebar .nav-item.mt-5 .nav-link {
            color: #dc3545;
        }

        .sidebar .nav-item.mt-5 .nav-link:hover {
            background-color: rgba(220, 53, 69, 0.2);
            color: #fff;
        }

        /* Main content */
        main {
            margin-left: 250px;
            padding: 20px;
            background-color: #fff;
            min-height: 100vh;
            transition: margin-left 0.3s ease-in-out;
        }

        /* Card styles */
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-3px);
        }

        .card-header {
            background-color: #007bff;
            color: #fff;
            border-radius: 8px 8px 0 0;
            font-size: 1.1rem;
            padding: 15px;
        }

        .card-body h5 {
            color: #007bff;
            font-size: 2rem;
        }

        .card-body .btn {
            font-size: 0.9rem;
            padding: 8px 16px;
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
                z-index: 1050;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            main {
                margin-left: 0;
            }

            .navbar-toggler {
                display: flex !important;
                align-items: center;
            }
        }

        @media (max-width: 767.98px) {
            .sidebar {
                position: fixed;
                width: 100%;
                height: 100%;
                padding: 15px;
            }

            .card-body h5 {
                font-size: 1.5rem;
            }

            .card-header {
                font-size: 1rem;
                padding: 10px;
            }

            .card-body .btn {
                font-size: 0.85rem;
                padding: 6px 12px;
            }

            .h1, .h2 {
                font-size: 1.5rem;
            }

            .lead {
                font-size: 1rem;
            }
        }

        @media (max-width: 575.98px) {
            .row.g-4 {
                margin-left: -10px;
                margin-right: -10px;
            }

            .col-12 {
                padding-left: 10px;
                padding-right: 10px;
            }

            .card-body h5 {
                font-size: 1.2rem;
            }

            .card-header {
                font-size: 0.9rem;
            }
        }

        /* Toggle button */
        .navbar-toggler {
            display: none; /* Hidden by default for large screens */
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background-color: #212529;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            color: #fff;
            font-size: 1rem;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .navbar-toggler:hover {
            background-color: #343a40;
        }

        .navbar-toggler svg {
            color: #fff;
            width: 24px;
            height: 24px;
        }

        .navbar-toggler span.text {
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Overlay for mobile when sidebar is active */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
        }

        .overlay.active {
            display: block;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <button class="navbar-toggler" type="button" id="sidebarToggle">
        <span data-feather="menu"></span>
        <span class="text">Menu</span>
    </button>
    <div class="overlay" id="overlay"></div>
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block bg-dark text-white sidebar py-3">
            <h4 class="text-white ms-3 py-2 text-center border-bottom border-secondary mb-4">
                <?php echo htmlspecialchars($roleTitle); ?> Panel
            </h4>
            <ul class="nav flex-column ms-3">
                <li class="nav-item mb-2">
                    <a class="nav-link active" aria-current="page" href="my_dashboard.php">
                        <span data-feather="home"></span> Dashboard
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="new_sale.php">
                        <span data-feather="shopping-cart"></span> New Sale
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="my_sales_history.php">
                        <span data-feather="dollar-sign"></span> My Sales History
                    </a>
                </li>

                <?php if (in_array($role, ['manager', 'admin'])) : ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="inventory_management.php">
                            <span data-feather="package"></span> Inventory Management
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="sales_overview.php">
                            <span data-feather="bar-chart"></span> Sales Overview
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#reportsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="reportsSubmenu">
                            <span data-feather="clipboard"></span> Reports
                        </a>
                        <div class="collapse" id="reportsSubmenu">
                            <ul class="nav flex-column ms-3">
                                <li class="nav-item">
                                    <a class="nav-link" href="export_product_sales_pdf.php?date=<?php echo date('Y-m-d'); ?>" target="_blank">
                                        <span data-feather="file-text"></span> Product Sales (PDF)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="export_product_sales_csv.php?date=<?php echo date('Y-m-d'); ?>">
                                        <span data-feather="download"></span> Product Sales (CSV)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="export_daily_sales_pdf.php?date=<?php echo date('Y-m-d'); ?>" target="_blank">
                                        <span data-feather="file-text"></span> Daily Sales (PDF)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="export_daily_sales_csv.php?date=<?php echo date('Y-m-d'); ?>">
                                        <span data-feather="download"></span> Daily Sales (CSV)
                                    </a>
                                </li>
                                <li class="nav-item mt-2 border-top border-secondary pt-2">
                                    <a class="nav-link" href="reports.php">
                                        <span data-feather="settings"></span> Custom Reports
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                <?php endif; ?>

                <?php if ($role === 'admin') : ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="manage_users.php">
                            <span data-feather="users"></span> Manage Users
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="product_management.php">
                            <span data-feather="box"></span> Product Management
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item mt-5 pt-3 border-top border-secondary">
                    <a class="nav-link text-danger" href="logout.php">
                        <span data-feather="log-out"></span> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Welcome, <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($roleTitle); ?>)!</h1>
            </div>

            <p class="lead">This is your <?php echo htmlspecialchars($roleTitle); ?> Dashboard.</p>

            <div class="row g-4">
                <?php if ($role === 'cashier') : ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card text-center">
                        <div class="card-header">
                            Today's Transactions (My Sales)
                        </div>
                        <div class="card-body">
                            <h5 class="card-title display-4"><?php echo htmlspecialchars($dashboard_summary['today_sales_count']); ?></h5>
                            <p class="card-text">Total sales you made today.</p>
                            <a href="my_sales_history.php" class="btn btn-outline-primary">View My History</a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card text-center">
                        <div class="card-header">
                            Today's Revenue (My Sales)
                        </div>
                        <div class="card-body">
                            <h5 class="card-title display-4">₦<?php echo number_format($dashboard_summary['today_sales_amount'], 2); ?></h5>
                            <p class="card-text">Total revenue you generated today.</p>
                            <a href="new_sale.php" class="btn btn-outline-success">Start New Sale</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array($role, ['manager', 'admin'])) : ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card text-center">
                        <div class="card-header bg-success">
                            Overall Today's Sales
                        </div>
                        <div class="card-body">
                            <h5 class="card-title display-4"><?php echo htmlspecialchars($dashboard_summary['today_sales_count']); ?></h5>
                            <p class="card-text">Total transactions across all cashiers today.</p>
                            <a href="daily_sales_summary.php" class="btn btn-outline-success">View Daily Summary</a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card text-center">
                        <div class="card-header bg-info">
                            Overall Today's Revenue
                        </div>
                        <div class="card-body">
                            <h5 class="card-title display-4">₦<?php echo number_format($dashboard_summary['today_sales_amount'], 2); ?></h5>
                            <p class="card-text">Total revenue across all cashiers today.</p>
                            <a href="sales_overview.php" class="btn btn-outline-info">View Sales Overview</a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card text-center">
                        <div class="card-header bg-warning">
                            Low Stock Alerts
                        </div>
                        <div class="card-body">
                            <h5 class="card-title display-4 text-warning"><?php echo htmlspecialchars($dashboard_summary['low_stock_items_count']); ?></h5>
                            <p class="card-text">Products with low stock levels.</p>
                            <a href="inventory_management.php" class="btn btn-outline-warning">Manage Inventory</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($role === 'admin') : ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card text-center">
                        <div class="card-header bg-danger">
                            User Management
                        </div>
                        <div class="card-body">
                            <h5 class="card-title display-4 text-danger"><span data-feather="users"></span></h5>
                            <p class="card-text">Add, edit, or remove users.</p>
                            <a href="manage_users.php" class="btn btn-outline-danger">Go to User Management</a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card text-center">
                        <div class="card-header bg-primary">
                            Product Management
                        </div>
                        <div class="card-body">
                            <h5 class="card-title display-4 text-primary"><span data-feather="box"></span></h5>
                            <p class="card-text">Full control over product listings.</p>
                            <a href="product_management.php" class="btn btn-outline-primary">Go to Product Management</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Ensure Feather Icons load after DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        feather.replace(); // Initialize Feather Icons
        console.log('Feather icons initialized'); // Debug log

        // Toggle sidebar function
        function toggleSidebar() {
            console.log('toggleSidebar called'); // Debug log
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');
            if (sidebar && overlay) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                console.log('Sidebar toggled, active:', sidebar.classList.contains('active')); // Debug log
            } else {
                console.error('Sidebar or overlay not found');
            }
        }

        // Attach click event to toggle button
        const toggleButton = document.querySelector('#sidebarToggle');
        if (toggleButton) {
            toggleButton.addEventListener('click', function(event) {
                event.preventDefault(); // Prevent any default behavior
                console.log('Toggler button clicked'); // Debug log
                toggleSidebar();
            });
        } else {
            console.error('Toggle button not found');
        }

        // Close sidebar when clicking a nav link on mobile
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 991.98) {
                    console.log('Nav link clicked, closing sidebar'); // Debug log
                    const sidebar = document.querySelector('.sidebar');
                    const overlay = document.querySelector('.overlay');
                    if (sidebar && overlay) {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                    } else {
                        console.error('Sidebar or overlay not found on nav link click');
                    }
                }
            });
        });

        // Close sidebar when clicking overlay
        const overlay = document.querySelector('#overlay');
        if (overlay) {
            overlay.addEventListener('click', function() {
                console.log('Overlay clicked, closing sidebar'); // Debug log
                toggleSidebar();
            });
        } else {
            console.error('Overlay not found');
        }
    });
</script>
</body>
</html>