<?php
// product_sales_summary.php
session_start(); // Always start the session at the very top

// Enable error reporting for development (remove or set to 0 in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include your database connection file
require_once 'db_connect.php'; // This file should establish a MySQLi connection as $conn

// --- Access Control ---
// Redirect to login if user is not logged in or session variables are missing
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $_SESSION['message'] = "Please log in to access this page.";
    $_SESSION['message_type'] = "danger";
    header("Location: index.php"); // Assuming index.php is your login page
    exit;
}

// Define allowed roles for this report page
$allowedRoles = ['admin', 'manager'];

// Check if the user's role has permission
if (!in_array($_SESSION['role'], $allowedRoles)) {
    $_SESSION['message'] = "Access denied. You do not have permission to view this report.";
    $_SESSION['message_type'] = "danger";
    header("Location: my_dashboard.php"); // Redirect to a dashboard or appropriate page
    exit;
}
// --- End Access Control ---

// Get date parameter from GET request or default to today's date
$date = $_GET['date'] ?? date('Y-m-d');

// Validate date format to prevent SQL injection or unexpected behavior
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    // If the date format is invalid, set an error message and exit cleanly
    http_response_code(400); // Bad Request
    die(json_encode(['error' => 'Invalid date format provided.']));
}

$sales = [];          // Array to store fetched sales data
$grandTotal = 0;      // Variable to store the overall total sales amount
$error_message = null; // Variable to store any database error messages

try {
    // SQL Query to aggregate sales by product name for a specific date
    // It joins sales, sale_items, and products tables to get the product name
    $query = "
        SELECT
            p.name AS product,                 -- CORRECTED: Use 'p.name' as per products table schema
            SUM(si.quantity) AS total_qty,     -- Sum of quantities sold for each product
            SUM(si.line_total) AS total_amount -- Sum of line totals (quantity * unit_price) for each product
        FROM
            sales s
        JOIN
            sale_items si ON s.sale_id = si.sale_id
        JOIN
            products p ON si.product_id = p.id
        WHERE
            DATE(s.sale_date) = ?              -- Filter sales by the selected date
        GROUP BY
            p.name                             -- Group results by product name
        ORDER BY
            p.name                             -- Order results by product name
    ";

    // Prepare the SQL statement for secure execution
    if ($stmt = $conn->prepare($query)) {
        // Bind the date parameter to the prepared statement
        $stmt->bind_param("s", $date); // 's' indicates a string type

        // Execute the prepared statement
        $stmt->execute();

        // Get the result set from the executed statement
        $result = $stmt->get_result();

        // Fetch all rows into the $sales array and calculate grand total
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $sales[] = $row;
                $grandTotal += floatval($row['total_amount']);
            }
        }
        // If num_rows is 0, $sales remains an empty array, which is handled in the HTML part

        // Close the prepared statement
        $stmt->close();
    } else {
        // If prepare fails, throw an exception
        throw new mysqli_sql_exception("Failed to prepare product sales summary query: " . $conn->error);
    }

} catch (mysqli_sql_exception $e) {
    // Catch database-specific exceptions
    // Log the detailed error for debugging purposes (check your server's error logs)
    error_log("Database Error in product_sales_summary.php: " . $e->getMessage());

    // Set a user-friendly error message
    $error_message = "Could not retrieve product sales summary. Please try again later or contact support.";

    // For debugging, you can display the detailed error, but in production, avoid it
    // http_response_code(500); // Internal Server Error
    // die(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
} finally {
    // Ensure the database connection is closed if it was opened and is still active
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
    <title>Product Sales Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #343a40; padding-bottom: 20px; }
        .container { max-width: 960px; }
        .table th, .table td { vertical-align: middle; }
        .table .text-end { text-align: end; } /* Use text-end for right alignment in Bootstrap 5 */
        .header-actions { display: flex; flex-direction: column; gap: 10px; }
        @media (min-width: 768px) {
            .header-actions { flex-direction: row; justify-content: space-between; align-items: center; }
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4">
    <h3 class="text-center mb-4">Product Sales Summary (<?= htmlspecialchars(date('F j, Y', strtotime($date))) ?>)</h3>

    <div class="mb-3 header-actions">
        <form method="GET" class="d-flex flex-wrap gap-2">
            <div>
                <label for="date" class="form-label visually-hidden">Date</label>
                <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($date) ?>" required>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
        <div class="d-flex gap-2">
            <a href="export_product_sales_pdf.php?date=<?= htmlspecialchars($date) ?>" class="btn btn-danger">Export PDF</a>
            <a href="export_product_sales_csv.php?date=<?= htmlspecialchars($date) ?>" class="btn btn-success">Export Excel</a>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($sales)): ?>
        <div class="alert alert-info text-center">No product sales records found for <?= htmlspecialchars(date('F j, Y', strtotime($date))) ?>.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Total Quantity Sold</th>
                        <th class="text-end">Total Sales Amount (₦)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['product']) ?></td>
                            <td class="text-end"><?= htmlspecialchars($row['total_qty']) ?></td>
                            <td class="text-end">₦<?= number_format($row['total_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <h5 class="text-end mt-4">Grand Total for Date: ₦<?= number_format($grandTotal, 2) ?></h5>
    <?php endif; ?>

    <div class="text-center mt-5">
        <a href="my_dashboard.php" class="btn btn-secondary">⬅️ Back to Dashboard</a>
    </div>
</div>
</body>
</html>