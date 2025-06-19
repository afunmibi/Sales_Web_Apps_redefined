<?php
session_start();
require_once 'db_connect.php'; // This file MUST connect using MySQLi and set $conn

// Enable error reporting for debugging (remove or restrict in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize messages (these will be populated from $_SESSION after redirect)
$error_message = '';
$success_message = '';

// Check for and display messages from previous redirects (PRG pattern)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// 1. Authentication Check
if (!isset($_SESSION['role'])) {
    header("Location: index.php"); // Redirect to login if not logged in
    exit;
}

// 2. Authorization Check: Only 'admin' can access product management
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Access denied. Only administrators can manage products.";
    header("Location: my_dashboard.php"); // Redirect to a more appropriate page
    exit;
}

$roleTitle = ucfirst($_SESSION['role']);
$products = []; // Array to hold products fetched from DB

// Function to handle database errors and set session messages
function handleDbError($conn, $context, $e = null) {
    // For debugging: log the actual MySQLi error
    $errorMessage = $conn->error ?? ($e ? $e->getMessage() : "Unknown error.");
    error_log("Product Management Error ({$context}): " . $errorMessage);
    $_SESSION['error_message'] = "Database error: Could not " . $context . ". Please try again later.";
}

// --- Handle Product Actions (Add, Edit, Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_product':
                    $name = trim($_POST['name'] ?? '');
                    $description = trim($_POST['description'] ?? ''); // Added description
                    $price = floatval($_POST['price'] ?? 0);
                    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);

                    if (empty($name) || $price <= 0 || $stock_quantity < 0) {
                        $_SESSION['error_message'] = "All fields (name, price, stock) are required and must be valid.";
                    } else {
                        // Include description and created_at
                        $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock_quantity, created_at) VALUES (?, ?, ?, ?, NOW())");
                        if ($stmt === false) {
                            throw new mysqli_sql_exception("Prepare failed: " . $conn->error);
                        }
                        $stmt->bind_param("ssdi", $name, $description, $price, $stock_quantity); // 'ssdi' for string, string, double, int
                        if ($stmt->execute()) {
                            $_SESSION['success_message'] = "Product '{$name}' added successfully!";
                        } else {
                            throw new mysqli_sql_exception("Execute failed: " . $stmt->error);
                        }
                        $stmt->close();
                    }
                    break;

                case 'edit_product':
                    $id = intval($_POST['product_id'] ?? 0);
                    $name = trim($_POST['name'] ?? '');
                    $description = trim($_POST['description'] ?? ''); // Added description
                    $price = floatval($_POST['price'] ?? 0);
                    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);

                    if ($id <= 0 || empty($name) || $price <= 0 || $stock_quantity < 0) {
                        $_SESSION['error_message'] = "Invalid data provided for product update.";
                    } else {
                        // Include description and updated_at
                        $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock_quantity = ?, updated_at = NOW() WHERE id = ?");
                        if ($stmt === false) {
                            throw new mysqli_sql_exception("Prepare failed: " . $conn->error);
                        }
                        $stmt->bind_param("ssdii", $name, $description, $price, $stock_quantity, $id); // 'ssdii' for string, string, double, int, int
                        if ($stmt->execute()) {
                            $_SESSION['success_message'] = "Product '{$name}' updated successfully!";
                        } else {
                            throw new mysqli_sql_exception("Execute failed: " . $stmt->error);
                        }
                        $stmt->close();
                    }
                    break;

                case 'delete_product':
                    $id = intval($_POST['product_id'] ?? 0);

                    if ($id <= 0) {
                        $_SESSION['error_message'] = "Invalid product ID for deletion.";
                    } else {
                        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
                        if ($stmt === false) {
                            throw new mysqli_sql_exception("Prepare failed: " . $conn->error);
                        }
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            $_SESSION['success_message'] = "Product deleted successfully!";
                        } else {
                            throw new mysqli_sql_exception("Execute failed: " . $stmt->error);
                        }
                        $stmt->close();
                    }
                    break;

                default:
                    $_SESSION['error_message'] = "Unknown action.";
                    break;
            }
        } catch (mysqli_sql_exception $e) {
            handleDbError($conn, $_POST['action'] . " product", $e);
        }
    }
    // IMPORTANT: Always redirect after a POST to prevent form re-submission on refresh
    header("Location: product_management.php"); // PRG pattern redirect
    exit;
}

// --- Fetch all products for display ---
try {
    // Include description, created_at, updated_at
    $stmt = $conn->prepare("SELECT id, name, description, price, stock_quantity, created_at, updated_at FROM products ORDER BY name ASC");
    if ($stmt === false) {
        throw new mysqli_sql_exception("Prepare failed: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $error_message = "Could not retrieve product list. Please try again later.";
} finally {
    // Ensure the connection is closed even if an error occurs earlier
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
    <title><?php echo htmlspecialchars($roleTitle); ?> - Product Management</title>
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
            background-color: #212529 !important; /* Dark background for sidebar */
            width: 250px; /* Fixed width for larger screens */
            overflow-y: auto; /* Enable vertical scrolling if content overflows */
            overflow-x: hidden; /* Hide horizontal scrollbar */
        }
        .sidebar .nav-link {
            color: #e9ecef !important; /* Light text color */
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
            background-color: rgba(255, 255, 255, 0.1); /* Slightly lighter on hover/active */
            color: #fff !important;
        }
        .sidebar .nav-item.mt-4 .nav-link {
            color: #dc3545 !important; /* Red for logout button */
        }
        .sidebar .nav-item.mt-4 .nav-link:hover {
            background-color: rgba(220, 53, 69, 0.2);
            color: #fff !important;
        }
        main {
            margin-left: 250px; /* Adjust margin to accommodate the sidebar */
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
                margin-left: 0; /* No margin on small screens */
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
                    <a class="nav-link" href="cashier_dashboard.php">
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
                <?php // Note: Using $_SESSION['role'] directly is fine here, assuming it's correctly set.
                      // 'role' vs 'roleTitle' - 'role' is the actual value, 'roleTitle' is just capitalized for display.
                      // So conditions like in_array($_SESSION['role'], ['manager', 'admin']) are correct.
                ?>
                <?php if (in_array($_SESSION['role'], ['manager', 'admin'])) : ?>
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
                        <a class="nav-link active" aria-current="page" href="product_management.php">
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
                    <h2 class="h4">Product Management</h2>
                    <?php if ($_SESSION['role'] === 'admin') : ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <span data-feather="plus-circle"></span> Add New Product
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($products)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        No products found in the database.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Price (₦)</th>
                                    <th>Stock Quantity</th>
                                    <th>Created At</th>
                                    <th>Updated At</th>
                                    <?php if ($_SESSION['role'] === 'admin') : ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['id']); ?></td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['description'] ?? 'N/A'); ?></td>
                                        <td>₦<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php echo ($product['stock_quantity'] < 10) ? 'bg-danger' : (($product['stock_quantity'] < 50) ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                                <?php echo htmlspecialchars($product['stock_quantity']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date("Y-m-d H:i", strtotime($product['created_at'])); ?></td>
                                        <td><?php echo $product['updated_at'] ? date("Y-m-d H:i", strtotime($product['updated_at'])) : 'N/A'; ?></td>
                                        <?php if ($_SESSION['role'] === 'admin') : ?>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-product-btn"
                                                        data-bs-toggle="modal" data-bs-target="#editProductModal"
                                                        data-id="<?php echo htmlspecialchars($product['id']); ?>"
                                                        data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                        data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>"
                                                        data-price="<?php echo htmlspecialchars($product['price']); ?>"
                                                        data-quantity="<?php echo htmlspecialchars($product['stock_quantity']); ?>">
                                                    <span data-feather="edit"></span> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-product-btn"
                                                        data-bs-toggle="modal" data-bs-target="#deleteProductModal"
                                                        data-id="<?php echo htmlspecialchars($product['id']); ?>"
                                                        data-name="<?php echo htmlspecialchars($product['name']); ?>">
                                                    <span data-feather="trash-2"></span> Delete
                                                </button>
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
</div>

<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="product_management.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_product">
                    <div class="mb-3">
                        <label for="productName" class="form-label">Product Name</label>
                        <input type="text" class="form-control" id="productName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="productDescription" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="productDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="productPrice" class="form-label">Price (₦)</label>
                        <input type="number" step="0.01" class="form-control" id="productPrice" name="price" required min="0.01">
                    </div>
                    <div class="mb-3">
                        <label for="stockQuantity" class="form-label">Stock Quantity</label>
                        <input type="number" class="form-control" id="stockQuantity" name="stock_quantity" required min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="product_management.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="product_id" id="editProductId">
                    <div class="mb-3">
                        <label for="editProductName" class="form-label">Product Name</label>
                        <input type="text" class="form-control" id="editProductName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editProductDescription" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="editProductDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editProductPrice" class="form-label">Price (₦)</label>
                        <input type="number" step="0.01" class="form-control" id="editProductPrice" name="price" required min="0.01">
                    </div>
                    <div class="mb-3">
                        <label for="editStockQuantity" class="form-label">Stock Quantity</label>
                        <input type="number" class="form-control" id="editStockQuantity" name="stock_quantity" required min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteProductModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="product_management.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" id="deleteProductId">
                    <p>Are you sure you want to delete product: <strong id="deleteProductName"></strong>? This action cannot be undone and may affect historical sales data.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Product</button>
                </div>
            </form>
        </div>
    </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    feather.replace(); // Initialize Feather Icons

    // JavaScript for populating Edit Product Modal
    document.addEventListener('DOMContentLoaded', function() {
        var editProductModal = document.getElementById('editProductModal');
        editProductModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Button that triggered the modal
            var id = button.getAttribute('data-id');
            var name = button.getAttribute('data-name');
            var description = button.getAttribute('data-description'); // Get description
            var price = button.getAttribute('data-price');
            var quantity = button.getAttribute('data-quantity');

            var modalIdInput = editProductModal.querySelector('#editProductId');
            var modalNameInput = editProductModal.querySelector('#editProductName');
            var modalDescriptionInput = editProductModal.querySelector('#editProductDescription'); // Set description
            var modalPriceInput = editProductModal.querySelector('#editProductPrice');
            var modalQuantityInput = editProductModal.querySelector('#editStockQuantity');

            modalIdInput.value = id;
            modalNameInput.value = name;
            modalDescriptionInput.value = description; // Assign description
            modalPriceInput.value = price;
            modalQuantityInput.value = quantity;
        });

        // JavaScript for populating Delete Product Modal
        var deleteProductModal = document.getElementById('deleteProductModal');
        deleteProductModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Button that triggered the modal
            var id = button.getAttribute('data-id');
            var name = button.getAttribute('data-name');

            var modalIdInput = deleteProductModal.querySelector('#deleteProductId');
            var modalNameSpan = deleteProductModal.querySelector('#deleteProductName');

            modalIdInput.value = id;
            modalNameSpan.textContent = name;
        });
    });
</script>
</body>
</html>