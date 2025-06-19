<?php
// sales_overview.php
session_start();
ini_set('display_errors', 1); // Display errors for debugging
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php'; // Your database connection file

// --- 1. Authentication Check ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $_SESSION['message'] = "Please log in to access this page.";
    $_SESSION['message_type'] = "danger";
    header("Location: index.php"); // Assuming index.php is your login page
    exit;
}

// --- 2. Authorization Check: Only 'manager' or 'admin' can access the full sales overview ---
if (!in_array($_SESSION['role'], ['manager', 'admin'])) {
    $_SESSION['message'] = "Access denied. You do not have permission to view this page.";
    $_SESSION['message_type'] = "danger";
    header("Location: my_dashboard.php"); // Redirect to dashboard or appropriate page
    exit;
}

$roleTitle = ucfirst($_SESSION['role']);
$allSales = [];
$error_message = '';

try {
    // Corrected Query:
    // - Selects from 'sales' table.
    // - Uses 'sale_id', 'transaction_code', 'sale_date', 'grand_total'.
    // - Joins with 'users' table to get the cashier's username using 'cashier_id'.
    $query = "SELECT 
                s.sale_id,
                s.transaction_code,
                u.username AS cashier_username, -- Get username from users table
                s.customer_name,                -- Include customer name for overview
                s.sale_date,
                s.grand_total
              FROM 
                sales s
              JOIN 
                users u ON s.cashier_id = u.id
              ORDER BY 
                s.sale_date DESC";

    if ($stmt = $conn->prepare($query)) {
        $stmt->execute();
        $result = $stmt->get_result();
        $allSales = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        throw new mysqli_sql_exception("Failed to prepare sales overview query: " . $conn->error);
    }

} catch (mysqli_sql_exception $e) {
    error_log("Error fetching all sales overview: " . $e->getMessage());
    $error_message = "Could not retrieve sales overview. Please try again later.";
} finally {
    // Ensure the connection is closed only if it was successfully opened and is still active
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($roleTitle); ?> - Sales Overview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #343a40; }
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1000;
            padding: 48px 0 0;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #212529 !important;
            width: 250px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .sidebar .nav-link {
            color: #e9ecef !important;
            transition: background-color 0.2s ease, color 0.2s ease;
            padding: 10px 15px;
            border-radius: 5px;
            display: flex;
            align-items: center;
        }
        .sidebar .nav-link svg {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            stroke-width: 2;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff !important;
        }
        .sidebar .nav-item.mt-4 .nav-link {
            color: #dc3545 !important;
        }
        .sidebar .nav-item.mt-4 .nav-link:hover {
            background-color: rgba(220, 53, 69, 0.2);
            color: #fff !important;
        }
        main {
            margin-left: 250px;
            background-color: #fff;
            padding: 30px !important;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            min-height: 100vh;
        }
        @media (max-width: 767.98px) {
            .sidebar {
                position: relative;
                width: 100%;
                padding: 15px;
                height: auto;
                overflow-y: visible;
            }
            main {
                margin-left: 0;
            }
        }
        .card { border: none; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); margin-bottom: 25px; }
        .table thead th { background-color: #007bff; color: #fff; border-color: #0056b3; padding: 12px 15px; vertical-align: middle; }
        .table tbody tr:hover { background-color: rgba(0, 123, 255, 0.05); }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block bg-dark text-white sidebar py-3">
            <h4 class="text-white ms-3 py-2 text-center border-bottom border-secondary mb-4">
                <?php echo htmlspecialchars($roleTitle); ?> Panel
            </h4>
            <ul class="nav flex-column ms-3">
                <li class="nav-item mb-2">
                    <a class="nav-link" href="my_dashboard.php">
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
                <?php if (in_array($_SESSION['role'], ['manager', 'admin'])) : ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="inventory_management.php">
                            <span data-feather="package"></span> Inventory Management
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link active" aria-current="page" href="sales_overview.php">
                            <span data-feather="bar-chart"></span> Sales Overview
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="product_sales_summary.php">
                            <span data-feather="file-text"></span> Product Sales Summary
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="daily_sales_summary.php">
                            <span data-feather="calendar"></span> Daily Sales Summary
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($_SESSION['role'] === 'admin') : ?>
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
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h4">All Sales Overview</h2>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($allSales)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        No sales records found.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Transaction Code</th>
                                    <th>Cashier</th>
                                    <th>Customer Name</th>
                                    <th>Date</th>
                                    <th>Grand Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allSales as $sale): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($sale['transaction_code']); ?></td>
                                        <td><?php echo htmlspecialchars($sale['cashier_username']); ?></td>
                                        <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date("F j, Y, g:i a", strtotime($sale['sale_date'])); ?></td>
                                        <td>₦<?php echo number_format($sale['grand_total'], 2); ?></td>
                                        <td>
                                            <a href="receipt.php?sale_id=<?php echo htmlspecialchars($sale['sale_id']); ?>" class="btn btn-info btn-sm" target="_blank">
                                                <span data-feather="printer"></span> View Receipt
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
<script>
    feather.replace(); // Initialize Feather Icons
</script>
</body>
</html>