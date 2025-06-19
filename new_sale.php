<?php
// TOP OF new_sale.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once 'db_connect.php'; // Ensure this path is correct for your database connection

// Initialize response message variables
$response_message = $_SESSION['message'] ?? '';
$response_type = $_SESSION['message_type'] ?? '';

// Clear session messages after displaying them
unset($_SESSION['message']);
unset($_SESSION['message_type']);

// Generate CSRF token if not already set in the session
// This token is crucial for security against Cross-Site Request Forgery (CSRF) attacks.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Authentication and Authorization Check ---
// Redirects to login if user is not logged in or has an unauthorized role.
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['cashier', 'manager', 'admin'])) {
    $_SESSION['message'] = "Please log in to access this page.";
    $_SESSION['message_type'] = "danger";
    header("Location: index.php"); // Redirect to login page
    exit;
}

// Get user details from session for display and further processing
$cashier_id = $_SESSION['user_id']; // This will be used for sales insertion
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$roleTitle = ucfirst($role);

// --- PROCESS SALE LOGIC (formerly in process_sale.php) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token validation (crucial for security)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $response_message = "Security error: Invalid request token. Please try again.";
        $response_type = "danger";
    } else {
        $customer_name = !empty($_POST['customer_name']) ? trim($_POST['customer_name']) : 'Walk-in Customer';
        $discount_percentage = isset($_POST['discount']) ? floatval($_POST['discount']) : 0;
        $amount_paid = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : 0;
        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];

        if (empty($items)) {
            $response_message = "No items selected for the sale.";
            $response_type = "danger";
        } elseif ($amount_paid < 0) {
            $response_message = "Amount paid cannot be negative.";
            $response_type = "danger";
        } else {
            // Start Database Transaction
            $conn->begin_transaction();

            try {
                $subtotal = 0;
                $sales_items_to_insert = [];
                $product_stock_updates = [];

                // Fetch product details from DB and validate quantities
                foreach ($items as $item) {
                    $product_id = intval($item['product_id']);
                    $quantity = intval($item['quantity']);

                    $stmt_product = $conn->prepare("SELECT name, price, stock_quantity FROM products WHERE id = ?");
                    if (!$stmt_product) {
                        throw new Exception("Database prepare error (products): " . $conn->error);
                    }
                    $stmt_product->bind_param("i", $product_id);
                    $stmt_product->execute();
                    $result_product = $stmt_product->get_result();
                    $product_data = $result_product->fetch_assoc();
                    $stmt_product->close();

                    if (!$product_data) {
                        throw new Exception("Product ID " . htmlspecialchars($product_id) . " not found.");
                    }

                    $product_name = $product_data['name'];
                    $unit_price = $product_data['price']; // Actual price from DB
                    $current_stock = $product_data['stock_quantity'];

                    // Critical: Check stock availability
                    if ($quantity <= 0) {
                        throw new Exception("Quantity for " . htmlspecialchars($product_name) . " must be at least 1.");
                    }
                    if ($quantity > $current_stock) {
                        throw new Exception("Insufficient stock for " . htmlspecialchars($product_name) . ". Available: " . $current_stock . ", Ordered: " . $quantity);
                    }

                    $line_total = $unit_price * $quantity;
                    $subtotal += $line_total;

                    $sales_items_to_insert[] = [
                        'product_id' => $product_id,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'line_total' => $line_total
                    ];

                    $product_stock_updates[] = [
                        'product_id' => $product_id,
                        'new_stock' => $current_stock - $quantity
                    ];
                }

                // Calculate final totals
                $discount_amount = $subtotal * ($discount_percentage / 100);
                $grand_total = $subtotal - $discount_amount;
                $change_amount = $amount_paid - $grand_total;

                // Generate a unique transaction code
                $transaction_code = 'SALE-' . date('Ymd-His') . '-' . substr(uniqid(), -5);

                // Insert into sales table (UPDATED TO MATCH NEW SCHEMA)
                $stmt_sale = $conn->prepare("INSERT INTO sales (transaction_code, cashier_id, customer_name, subtotal, discount_percentage, discount_amount, grand_total, amount_paid, change_amount, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Cash', 'Completed')");
                if (!$stmt_sale) {
                    throw new Exception("Database prepare error (sales): " . $conn->error);
                }
                $stmt_sale->bind_param("siddddddd",
                    $transaction_code,
                    $cashier_id,
                    $customer_name,
                    $subtotal,
                    $discount_percentage,
                    $discount_amount,
                    $grand_total,
                    $amount_paid,
                    $change_amount
                );

                if (!$stmt_sale->execute()) {
                    throw new Exception("Error inserting sale: " . $stmt_sale->error);
                }
                $sale_id = $conn->insert_id; // Get the ID of the new sale
                $stmt_sale->close();

                // Insert into sale_items table and update product stock
                $stmt_item = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt_item) {
                    throw new Exception("Database prepare error (sale_items): " . $conn->error);
                }
                $stmt_stock_update = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                if (!$stmt_stock_update) {
                    throw new Exception("Database prepare error (products stock update): " . $conn->error);
                }

                foreach ($sales_items_to_insert as $item_data) {
                    // Insert sale item
                    $stmt_item->bind_param("iiidd", $sale_id, $item_data['product_id'], $item_data['quantity'], $item_data['unit_price'], $item_data['line_total']);
                    if (!$stmt_item->execute()) {
                        throw new Exception("Error inserting sale item for product " . htmlspecialchars($item_data['product_id']) . ": " . $stmt_item->error);
                    }

                    // Update product stock
                    $new_stock_value = null;
                    foreach ($product_stock_updates as $stock_update) {
                        if ($stock_update['product_id'] == $item_data['product_id']) {
                            $new_stock_value = $stock_update['new_stock'];
                            break;
                        }
                    }
                    if ($new_stock_value === null) {
                        throw new Exception("Stock update data not found for product ID " . htmlspecialchars($item_data['product_id']));
                    }

                    $stmt_stock_update->bind_param("ii", $new_stock_value, $item_data['product_id']);
                    if (!$stmt_stock_update->execute()) {
                        throw new Exception("Error updating stock for product " . htmlspecialchars($item_data['product_id']) . ": " . $stmt_stock_update->error);
                    }
                }
                $stmt_item->close();
                $stmt_stock_update->close();

                // Commit the transaction if everything succeeded
                $conn->commit();
                $response_message = "Sale processed successfully! Transaction Code: **" . htmlspecialchars($transaction_code) . "** | Change: **₦" . number_format($change_amount, 2) . "**";
                $response_type = 'success';

            } catch (Exception $e) {
                // Rollback the transaction on any error
                $conn->rollback();
                $response_message = "Error processing sale: " . $e->getMessage();
                $response_type = 'danger';
                error_log("Sale processing error: " . $e->getMessage()); // Log error for server-side debugging
            } finally {
                // Connection will be closed at the end of the script (after product fetch)
            }
        }
    }
}
// --- END PROCESS SALE LOGIC ---


// --- Fetch Products from Database (for the form's dropdowns) ---
$products = [];
$sql_products = "SELECT id, name, price, stock_quantity FROM products WHERE stock_quantity > 0 ORDER BY name ASC";
if ($stmt = $conn->prepare($sql_products)) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
} else {
    error_log("Failed to prepare product query in new_sale.php: " . $conn->error);
    $response_message = "Could not load products. Please try again later.";
    $response_type = "danger";
}

// Close the database connection once data is fetched and processing is done
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($roleTitle); ?> - New Sale</title>
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
        .table-sale th, .table-sale td { vertical-align: middle; }
        .table-sale .form-control { min-width: 80px; }
        .table-sale .product-select { width: 100%; }
        .product-price, .line-total {
            text-align: right;
            padding-right: 15px; /* Added padding for better alignment */
            font-weight: bold;
        }
        .summary-row td {
            font-size: 1.1em;
            font-weight: bold;
            text-align: right;
            padding-right: 15px;
        }
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
                        <a class="nav-link active" aria-current="page" href="new_sale.php">
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
                    <h1 class="h2">New Sale</h1>
                </div>

                <?php if ($response_message) : ?>
                    <div class="alert alert-<?php echo htmlspecialchars($response_type); ?> alert-dismissible fade show" role="alert">
                        <?php echo $response_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card p-4">
                    <h5 class="mb-4">Create New Transaction</h5>
                    <form id="saleForm" method="POST" action=""> <input type="hidden" name="cashier_username" value="<?php echo htmlspecialchars($username); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                        <div class="mb-3">
                            <label for="customerName" class="form-label">Customer Name (Optional)</label>
                            <input type="text" class="form-control" id="customerName" name="customer_name" placeholder="Enter customer name">
                        </div>

                        <hr>
                        <h6>Sale Items</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-sale">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price (₦)</th>
                                        <th>Qty</th>
                                        <th>Total (₦)</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="saleItemsBody">
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end" id="subtotalDisplay">₦0.00</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Discount (%):</strong></td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm text-end" id="discountInput" name="discount" value="0" min="0" max="100" step="0.01">
                                        </td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                        <td class="text-end" id="grandTotalDisplay">₦0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="addItemBtn">
                            <span data-feather="plus-circle"></span> Add Item
                        </button>

                        <div class="row mt-4">
                            <div class="col-md-6 mb-3">
                                <label for="amountPaid" class="form-label">Amount Paid (₦)</label>
                                <input type="number" class="form-control" id="amountPaid" name="amount_paid" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="change" class="form-label">Change (₦)</label>
                                <input type="text" class="form-control" id="change" name="change" value="0.00" readonly>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100 mt-3">Process Sale</button>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        feather.replace(); // Initialize Feather Icons

        // PHP products array to JavaScript
        const products = <?php echo json_encode($products); ?>;
        let itemCounter = 0; // To uniquely identify rows

        // Function to create a new sale item row
        function createSaleItemRow() {
            itemCounter++;
            const rowId = `itemRow-${itemCounter}`;
            const row = document.createElement('tr');
            row.id = rowId;
            row.innerHTML = `
                <td>
                    <select class="form-select form-select-sm product-select" name="items[${itemCounter}][product_id]" required>
                        <option value="">Select Product</option>
                        ${products.map(p => `<option value="${p.id}" data-price="${p.price}" data-stock="${p.stock_quantity}">${p.name} (Stock: ${p.stock_quantity})</option>`).join('')}
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm product-price" value="0.00" step="0.01" readonly>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm product-qty" name="items[${itemCounter}][quantity]" value="1" min="1" required>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm line-total" value="0.00" step="0.01" readonly>
                </td>
                <td>
                    <button type="button" class="btn btn-outline-danger btn-sm remove-item-btn" data-row-id="${rowId}">
                        <span data-feather="x-circle"></span>
                    </button>
                </td>
            `;
            document.getElementById('saleItemsBody').appendChild(row);
            feather.replace(); // Re-initialize feather icons for new buttons

            // Add event listeners to the newly created elements
            const productSelect = row.querySelector('.product-select');
            const qtyInput = row.querySelector('.product-qty');
            const removeItemBtn = row.querySelector('.remove-item-btn');

            productSelect.addEventListener('change', updateRow);
            qtyInput.addEventListener('input', updateRow);
            removeItemBtn.addEventListener('click', () => {
                row.remove();
                calculateTotals(); // Recalculate after removing a row
            });

            // Initial update for the new row (in case default qty is not 1 or price needs setting)
            updateRow({ target: productSelect }); // Trigger update based on product selection
        }

        // Function to update individual row total and overall totals
        function updateRow(event) {
            const row = event.target.closest('tr');
            const productSelect = row.querySelector('.product-select');
            const priceInput = row.querySelector('.product-price');
            const qtyInput = row.querySelector('.product-qty');
            const lineTotalInput = row.querySelector('.line-total');

            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const price = parseFloat(selectedOption.dataset.price || 0);
            const stock = parseInt(selectedOption.dataset.stock || 0);
            let quantity = parseInt(qtyInput.value) || 0;

            // Update price input with selected product's price
            priceInput.value = price.toFixed(2);

            // Quantity validation (cannot exceed stock)
            if (selectedOption.value !== "") { // Only validate if a product is selected
                if (quantity > stock) {
                    alert(`Quantity for ${selectedOption.text.split(' (')[0]} cannot exceed available stock (${stock}).`);
                    quantity = stock; // Cap quantity at stock
                    qtyInput.value = stock;
                }
                if (quantity < 1) { // Ensure quantity is at least 1
                    quantity = 1;
                    qtyInput.value = 1;
                }
            } else { // If no product selected, quantity should be 0
                quantity = 0;
                qtyInput.value = 0;
            }


            const lineTotal = price * quantity;
            lineTotalInput.value = lineTotal.toFixed(2);

            calculateTotals();
        }

        // Function to calculate and update subtotal, discount, grand total, and change
        function calculateTotals() {
            let subtotal = 0;
            document.querySelectorAll('#saleItemsBody tr').forEach(row => {
                const lineTotal = parseFloat(row.querySelector('.line-total').value);
                subtotal += lineTotal;
            });

            const discountPercentage = parseFloat(document.getElementById('discountInput').value) || 0;
            const discountAmount = subtotal * (discountPercentage / 100);
            const grandTotal = subtotal - discountAmount;

            document.getElementById('subtotalDisplay').textContent = `₦${subtotal.toFixed(2)}`;
            document.getElementById('grandTotalDisplay').textContent = `₦${grandTotal.toFixed(2)}`;

            // Calculate change
            const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
            const change = amountPaid - grandTotal;
            document.getElementById('change').value = change.toFixed(2);
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', () => {
            createSaleItemRow(); // Add an initial item row when the page loads

            document.getElementById('addItemBtn').addEventListener('click', createSaleItemRow);
            document.getElementById('discountInput').addEventListener('input', calculateTotals);
            document.getElementById('amountPaid').addEventListener('input', calculateTotals);

            // Add an event listener to the form for submission (for final validation)
            document.getElementById('saleForm').addEventListener('submit', function(event) {
                // Ensure at least one item is selected and has a valid product ID
                const productSelects = document.querySelectorAll('.product-select');
                let hasValidItem = false;
                productSelects.forEach(select => {
                    if (select.value !== "") { // If any product is selected
                        hasValidItem = true;
                    }
                });

                if (!hasValidItem) {
                    alert('Please add at least one product to the sale.');
                    event.preventDefault(); // Stop form submission if no product is selected
                }
                 // Client-side check for amount paid less than grand total
                const grandTotal = parseFloat(document.getElementById('grandTotalDisplay').textContent.replace('₦', '')) || 0;
                const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;

                if (amountPaid < grandTotal) {
                    const difference = grandTotal - amountPaid;
                    alert(`Amount paid is insufficient. Please collect ₦${difference.toFixed(2)} more.`);
                    event.preventDefault(); // Prevent form submission
                }
            });
        });
    </script>
</body>
</html>