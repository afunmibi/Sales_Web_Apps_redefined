<?php
session_start(); // Start the session at the very beginning

// Set error reporting for development (disable or set to 0 in production for security)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include your database connection file
require_once 'db_connect.php'; // This should establish a MySQLi connection as $conn

// Check if a database connection error occurred
if ($conn->connect_error) {
    http_response_code(500);
    error_log("Database connection failed: " . $conn->connect_error); // Log the detailed error
    die(json_encode(['error' => 'Could not connect to the database. Please try again later.']));
}

// 1. Validate the sale ID from the GET parameter
// We're now expecting 'sale_id' in the URL, which is your sales table's primary key (an integer).
$sale_id = $_GET['sale_id'] ?? null;

// Validate if it's a positive integer
if (!$sale_id || !ctype_digit($sale_id) || $sale_id <= 0) {
    http_response_code(400); // Bad Request
    error_log("Invalid sale ID format or missing: " . ($sale_id ?? 'NULL'));
    die(json_encode(['error' => 'Invalid or missing sale ID. Please check the link.']));
}

$sale_details = null; // Will hold the main sale header details
$cashier_username_on_record = 'N/A';
$cashier_role_on_record = 'N/A';

try {
    // We will query the 'sales' table directly, joining with 'users' to get cashier info.
    // The 'sales' table already has 'subtotal', 'grand_total', 'sale_date', and 'cashier_id'.
    // Your schema does NOT include individual product items for a sale in the 'sales' table.
    // It seems 'sales' stores only the transaction summary.
    // If you have a separate table for 'sale_items' or 'transaction_products'
    // that links back to `sales.sale_id`, we'll need to join and fetch those.

    // ASSUMPTION: You need a second query (or a more complex JOIN) if you have
    // a separate table storing the individual products within a sale.
    // Your `sales` table only has `subtotal`, `grand_total`, etc. but no `product`, `quantity`, `price` columns.
    // I will proceed with fetching ONLY the sales summary from the 'sales' table.
    // If you also have a table like `sale_items` with `sale_id`, `product_id`, `quantity`, `price`, `subtotal_item`,
    // you MUST tell me so I can include that logic to display individual items.

    $sql = "SELECT
                s.sale_id,
                s.transaction_code,
                s.sale_date,
                s.subtotal,
                s.discount_percentage,
                s.discount_amount,
                s.grand_total,
                s.amount_paid,
                s.change_amount,
                s.payment_method,
                s.status,
                u.username AS cashier_username,
                u.role AS cashier_role
            FROM
                sales s
            JOIN
                users u ON s.cashier_id = u.id -- Joins to get cashier's name and role
            WHERE
                s.sale_id = ?"; // Querying by the numeric 'sale_id'

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param("i", $sale_id); // 'i' for integer binding for 'sale_id'
    $stmt->execute();
    $result = $stmt->get_result();
    $sale_details = $result->fetch_assoc(); // Fetch the single sale record

    // If you have a separate `sale_items` table, you'd add a second query here:
    // $items_sql = "SELECT product_name, quantity, item_price, item_subtotal FROM sale_items WHERE sale_id = ?";
    // Then loop through those results for the table.
    $sale_items = []; // Placeholder, will be empty unless you provide a schema for individual items.

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("Database error fetching receipt data for sale ID " . $sale_id . ": " . $e->getMessage());
    die(json_encode(['error' => 'An internal server error occurred while retrieving receipt data.']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Sale #<?= htmlspecialchars($sale_id) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* General Styles */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: #343a40;
        }
        .receipt-box {
            max-width: 600px;
            margin: 30px auto;
            padding: 25px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .receipt-title {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px dashed #e0e0e0;
        }
        .receipt-info p {
            margin-bottom: 5px;
            font-size: 0.95rem;
        }
        .table th, .table td {
            vertical-align: middle;
            padding: 12px;
        }
        .table .text-end {
            text-align: right;
        }
        .table tfoot td {
            border-top: 2px solid #dee2e6;
            font-weight: bold;
            padding-top: 10px;
        }
        .grand-total {
            text-align: right;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #e0e0e0;
        }
        .grand-total h5 {
            font-size: 1.5rem;
            color: #28a745; /* Green for total */
        }
        .receipt-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px dashed #e0e0e0;
            font-size: 0.9rem;
            color: #6c757d;
        }

        /* Print-specific styles */
        @media print {
            .no-print { display: none !important; }
            body {
                font-size: 10pt;
                margin: 0;
                padding: 0;
                background-color: #fff;
            }
            .receipt-box {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            .receipt-title, .receipt-footer {
                border-bottom: 1px dashed #ccc;
                border-top: 1px dashed #ccc;
            }
            .table th, .table td {
                padding: 0.2rem 0.5rem;
                border-color: #eee !important;
            }
            .table thead th {
                border-bottom: 1px solid #ccc !important;
            }
            .table tfoot td {
                border-top: 1px solid #ccc !important;
            }
        }
    </style>
</head>
<body class="bg-light">
<div class="receipt-box">
    <?php if ($sale_details): // Check if a sale record was found ?>
        <div class="receipt-title">
            <h4 class="fw-bold text-primary">🛒 Supermarket Receipt</h4>
            <p class="text-muted mb-0">Your Store Name Here</p>
            <p class="text-muted">Abeokuta, Ogun State, Nigeria</p>
        </div>

        <div class="receipt-info mb-4">
            <p><strong>Sale ID:</strong> <span class="fw-bold text-dark"><?= htmlspecialchars($sale_details['sale_id']) ?></span></p>
            <?php if (!empty($sale_details['transaction_code'])): ?>
            <p><strong>Transaction Code:</strong> <span class="fw-bold text-dark"><?= htmlspecialchars($sale_details['transaction_code']) ?></span></p>
            <?php endif; ?>
            <p><strong>Date:</strong> <?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($sale_details['sale_date']))) ?></p>
            <p><strong>Cashier:</strong> <?= htmlspecialchars($sale_details['cashier_username']) ?> (<?= htmlspecialchars(ucfirst($sale_details['cashier_role'])) ?>)</p>
            <?php if (!empty($sale_details['customer_name'])): ?>
            <p><strong>Customer:</strong> <?= htmlspecialchars($sale_details['customer_name']) ?></p>
            <?php endif; ?>
        </div>

        <table class="table table-sm table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Description</th>
                    <th class="text-end">Amount (₦)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // This section assumes you DO NOT have a separate table for individual sale items.
                // It just lists the summary details from the 'sales' table.
                // If you have `sale_items` table, you need a second query and loop here.
                ?>
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-end"><?= number_format($sale_details['subtotal'], 2) ?></td>
                </tr>
                <?php if ($sale_details['discount_amount'] > 0): ?>
                <tr>
                    <td>Discount (<?= htmlspecialchars($sale_details['discount_percentage']) ?>%):</td>
                    <td class="text-end">-<?= number_format($sale_details['discount_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="text-end fw-bold">Grand Total:</td>
                    <td class="text-end fw-bold fs-5 text-success">₦<?= number_format($sale_details['grand_total'], 2) ?></td>
                </tr>
                <tr>
                    <td class="text-end">Amount Paid:</td>
                    <td class="text-end">₦<?= number_format($sale_details['amount_paid'], 2) ?></td>
                </tr>
                <tr>
                    <td class="text-end">Change:</td>
                    <td class="text-end">₦<?= number_format($sale_details['change_amount'], 2) ?></td>
                </tr>
                <tr>
                    <td class="text-end">Payment Method:</td>
                    <td class="text-end"><?= htmlspecialchars($sale_details['payment_method']) ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="receipt-footer">
            <p class="text-muted">Thank you for your purchase!</p>
            <p class="text-muted">Come again soon.</p>
        </div>

        <div class="text-center mt-4 no-print">
            <button class="btn btn-primary me-2" onclick="window.print()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer-fill" viewBox="0 0 16 16">
                    <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm-1 2h10a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1"/>
                </svg> Print Receipt
            </button>
            <?php
            // Determine the correct dashboard link based on user role
            $back_link = 'index.php'; // Default fallback (login page)
            if (isset($_SESSION['role'])) {
                switch ($_SESSION['role']) {
                    case 'admin':
                        $back_link = 'my_dashboard.php';
                        break;
                    case 'manager':
                        $back_link = 'my_dashboard.php';
                        break;
                    case 'cashier':
                        $back_link = 'my_dashboard.php'; // Or my_dashboard.php if it's a universal cashier dashboard
                        break;
                    // Add other roles as needed
                }
            }
            ?>
            <a href="<?= htmlspecialchars($back_link) ?>" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5a.5.5 0 0 0 .5-.5"/>
                </svg> Back to Dashboard
            </a>
        </div>
    <?php else: // If no sale record was found ?>
        <div class="alert alert-warning text-center">
            <h4 class="alert-heading">Sale Not Found!</h4>
            <p>No sale record found for ID: <span class="fw-bold"><?= htmlspecialchars($sale_id ?? 'N/A') ?></span>.</p>
            <p>This could mean the sale ID is incorrect or the sale was not properly recorded.</p>
        </div>
        <div class="text-center mt-4 no-print">
            <?php
            // Same dynamic back link for the error state
            $back_link = 'index.php';
            if (isset($_SESSION['role'])) {
                switch ($_SESSION['role']) {
                    case 'admin': $back_link = 'my_dashboard.php'; break;
                    case 'manager': $back_link = 'my_dashboard.php'; break;
                    case 'cashier': $back_link = 'my_dashboard.php'; break;
                }
            }
            ?>
            <a href="<?= htmlspecialchars($back_link) ?>" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5a.5.5 0 0 0 .5-.5"/>
                </svg> Back to Dashboard
            </a>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>