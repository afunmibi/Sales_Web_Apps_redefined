<?php
session_start();
require_once 'db_connect.php'; // This file should establish a MySQLi connection as $conn

// 1. Authentication Check
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// 2. Authorization Check: Only 'manager' or 'admin' can access inventory management
if (!in_array($_SESSION['role'], ['manager', 'admin'])) {
    echo "Access denied. You do not have permission to view this page.";
    exit;
}

$roleTitle = ucfirst($_SESSION['role']);
$products = [];
$error_message = '';

try {
    // Change $pdo to $conn
    $stmt = $conn->prepare("SELECT id, name, price, stock_quantity FROM products ORDER BY name ASC");
    $stmt->execute();

    // Use get_result() and fetch_all(MYSQLI_ASSOC) for MySQLi
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); // Close the statement

} catch (mysqli_sql_exception $e) { // Change to mysqli_sql_exception
    error_log("Error fetching product inventory: " . $e->getMessage());
    $error_message = "Could not retrieve product inventory. Please try again later.";
}

// Ensure the connection is closed (good practice, though PHP closes it at script end)
if (isset($conn) && $conn->ping()) {
    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($roleTitle); ?> - Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #343a40; }
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 1000; padding: 48px 0 0; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); background-color: #212529 !important; }
        .sidebar .nav-link { color: #e9ecef !important; transition: background-color 0.2s ease, color 0.2s ease; padding: 10px 15px; border-radius: 5px; display: flex; align-items: center; }
        .sidebar .nav-link svg { margin-right: 10px; width: 18px; height: 18px; stroke-width: 2; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); color: #fff !important; }
        .sidebar .nav-item.mt-4 .nav-link { color: #dc3545 !important; }
        .sidebar .nav-item.mt-4 .nav-link:hover { background-color: rgba(220, 53, 69, 0.2); color: #fff !important; }
        main { margin-left: 25%; background-color: #fff; padding: 30px !important; border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); min-height: 100vh; }
        @media (min-width: 768px) { main { margin-left: calc(25% / 12 * 10); padding-left: calc(2 * 100vw / 12 + 1.5rem) !important; } }
        @media (max-width: 767.98px) { .sidebar { position: relative; width: 100%; padding: 15px; } main { margin-left: 0; } }
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
                        <span data-feather="shopping-cart"></span> New Sale
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="my_sales_history.php">
                        <span data-feather="dollar-sign"></span> My Sales History
                    </a>
                </li>
                <?php if (in_array($_SESSION['role'], ['admin', 'manager'])) : ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link active" aria-current="page" href="inventory_management.php">
                            <span data-feather="package"></span> Inventory Management
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="sales_overview.php">
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
                    <h2 class="h4">Inventory Management</h2>
                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager') : ?>
                        <a href="#" class="btn btn-primary disabled">
                            <span data-feather="plus-circle"></span> Add New Product (Coming Soon)
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($products)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        No products found in inventory.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Product ID</th>
                                    <th>Name</th>
                                    <th>Price (₦)</th>
                                    <th>Stock Quantity</th>
                                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager') : ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['id']); ?></td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td>₦<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php echo ($product['stock_quantity'] < 10) ? 'bg-danger' : (($product['stock_quantity'] < 50) ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                                <?php echo htmlspecialchars($product['stock_quantity']); ?>
                                            </span>
                                        </td>
                                        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager') : ?>
                                            <td>
                                                <a href="#" class="btn btn-sm btn-outline-secondary disabled" title="Edit Product">
                                                    <span data-feather="edit"></span> Edit
                                                </a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <div class="text-center">
        <a href="my_dashboard.php" class="btn btn-secondary mt-3">⬅️ Back to Dashboard</a>
    </div>
</div>
<script>
    feather.replace(); // Initialize Feather Icons
</script>
</body>
</html>