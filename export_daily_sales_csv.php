<?php
// export_daily_sales_csv.php
session_start(); // Must be at the very top of the file

// Enable error reporting for development (remove or set to 0 in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Access Control ---
// Redirect if user is not logged in or session variables are missing
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401); // Unauthorized
    die('Unauthorized access. Please log in.'); // Plain text error for direct download attempts
}

// Define allowed roles for this report page
$allowedRoles = ['admin', 'manager'];

// Check if the user's role has permission
if (!in_array($_SESSION['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    die('Access denied. You do not have permission to export this report.'); // Plain text error
}
// --- End Access Control ---

include 'db_connect.php'; // Ensure this file establishes a MySQLi connection as $conn

// Validate inputs
$date = $_GET['date'] ?? date('Y-m-d');
$cashier_username = $_GET['cashier_username'] ?? null; // Keep null if not set, for cleaner conditional query

// Date format validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400); // Bad Request
    die('Error: Invalid date format. Date must be YYYY-MM-DD.');
}

// Cashier username validation (optional but good if it's user-provided and could be empty string)
if ($cashier_username !== null && trim($cashier_username) === '') {
    http_response_code(400);
    die('Error: Invalid cashier username provided.');
}

try {
    // Query detailed sales with corrected column names and joins
    $query = "
        SELECT
            s.sale_id AS transaction_sale_id,   -- Using sale_id for internal grouping
            s.transaction_code,                 -- The unique human-readable transaction code
            s.sale_date,
            s.subtotal AS transaction_subtotal_before_discount, -- Subtotal from sales table
            s.discount_percentage,
            s.discount_amount,                  -- Discount amount from sales table
            s.grand_total AS transaction_final_grand_total, -- Final total from sales table
            s.amount_paid,
            s.change_amount,
            s.customer_name,
            u.username AS cashier_name,
            p.name AS product_name,             -- CORRECTED: p.name for product name
            si.quantity,
            si.unit_price AS item_unit_price,   -- CORRECTED: si.unit_price for price at sale
            si.line_total AS item_line_total    -- CORRECTED: si.line_total for item subtotal
        FROM
            sales s
        JOIN
            users u ON s.cashier_id = u.id
        JOIN
            sale_items si ON s.sale_id = si.sale_id -- CORRECTED: s.sale_id for join
        JOIN
            products p ON si.product_id = p.id
        WHERE
            DATE(s.sale_date) = ?
    ";

    if ($cashier_username !== null) {
        $query .= " AND u.username = ?";
    }
    $query .= " ORDER BY s.sale_id ASC, p.name ASC"; // Order by transaction then product name

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new mysqli_sql_exception("Failed to prepare sales report query: " . $conn->error);
    }

    if ($cashier_username !== null) {
        $stmt->bind_param("ss", $date, $cashier_username);
    } else {
        $stmt->bind_param("s", $date);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Group sales by transaction_id to structure the CSV output
    $salesGrouped = [];
    $overallGrandTotal = 0; // Total of all transaction grand_totals for the report
    while ($row = $result->fetch_assoc()) {
        $sale_id = $row['transaction_sale_id']; // Use the aliased sale_id for grouping key
        if (!isset($salesGrouped[$sale_id])) {
            $salesGrouped[$sale_id] = [
                'transaction_code' => $row['transaction_code'],
                'cashier' => $row['cashier_name'] ?? 'Unknown',
                'date' => $row['sale_date'] ?? '',
                'transaction_subtotal_before_discount' => $row['transaction_subtotal_before_discount'],
                'discount_percentage' => $row['discount_percentage'],
                'discount_amount' => $row['discount_amount'],
                'transaction_final_grand_total' => $row['transaction_final_grand_total'],
                'amount_paid' => $row['amount_paid'],
                'change_amount' => $row['change_amount'],
                'customer_name' => $row['customer_name'] ?: 'Walk-in Customer', // Default if customer_name is empty
                'items' => []
            ];
            $overallGrandTotal += floatval($row['transaction_final_grand_total']);
        }
        $salesGrouped[$sale_id]['items'][] = [
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'unit_price' => $row['item_unit_price'],
            'line_total' => $row['item_line_total']
        ];
    }

    $stmt->close();
    $conn->close();

    // Set CSV headers for download
    $filename_date = date('Y-m-d', strtotime($date));
    $filename = 'daily_sales_report_' . $filename_date . ($cashier_username ? '_' . str_replace(' ', '_', $cashier_username) : '') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate'); // Standard cache control
    header('Pragma: no-cache'); // For IE compatibility
    header('Expires: 0'); // For IE compatibility

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel compatibility

    // Report Header
    fputcsv($output, ['Supermarket Daily Sales Report']);
    fputcsv($output, ['Report Date', date('F j, Y', strtotime($date))]);
    if ($cashier_username) {
        fputcsv($output, ['Cashier', $cashier_username]);
    }
    fputcsv($output, []); // Empty line for spacing

    if (empty($salesGrouped)) {
        fputcsv($output, ['No sales records found for this date' . ($cashier_username ? ' and cashier' : '') . '.']);
    } else {
        foreach ($salesGrouped as $sale_id => $data) {
            // Transaction Header Row
            fputcsv($output, [
                "Transaction Code: " . $data['transaction_code'],
                "Cashier: " . $data['cashier'],
                "Customer: " . $data['customer_name'],
                "Sale Date: " . date('Y-m-d H:i:s', strtotime($data['date']))
            ]);
            fputcsv($output, []); // Empty line for spacing

            // Items Table Header for this transaction
            fputcsv($output, ['Product', 'Unit Price (N)', 'Quantity', 'Line Total (N)']);

            // Items for this transaction
            foreach ($data['items'] as $item) {
                fputcsv($output, [
                    $item['product_name'],
                    number_format($item['unit_price'], 2), // Corrected: item_unit_price
                    $item['quantity'],
                    number_format($item['line_total'], 2)  // Corrected: item_line_total
                ]);
            }

            // Transaction Summary for this transaction
            fputcsv($output, ['', '', 'Transaction Subtotal (Before Discount):', 'N' . number_format($data['transaction_subtotal_before_discount'], 2)]);

            if ($data['discount_percentage'] > 0) {
                fputcsv($output, ['', '', 'Discount (' . $data['discount_percentage'] . '%):', 'N' . number_format($data['discount_amount'], 2)]); // Using actual discount_amount
            }

            fputcsv($output, ['', '', 'Grand Total (Transaction):', 'N' . number_format($data['transaction_final_grand_total'], 2)]); // Using final grand_total
            fputcsv($output, ['', '', 'Amount Paid:', 'N' . number_format($data['amount_paid'], 2)]);
            fputcsv($output, ['', '', 'Change:', 'N' . number_format($data['change_amount'], 2)]);
            fputcsv($output, []); // Empty line for spacing after each transaction
        }

        // Overall Daily Grand Total
        fputcsv($output, ['', '', 'OVERALL DAILY GRAND TOTAL:', 'N' . number_format($overallGrandTotal, 2)]);
    }

    fclose($output); // Close the CSV output stream
    exit; // Terminate script execution after file output

} catch (mysqli_sql_exception $e) { // Catch database-specific exceptions
    http_response_code(500); // Internal Server Error
    error_log("Daily sales CSV generation failed: " . $e->getMessage()); // Log error details
    die('Failed to generate Daily Sales CSV. Database error: ' . $e->getMessage()); // Display error for debugging
} catch (Exception $e) { // Catch any other general exceptions
    http_response_code(500);
    error_log("Daily sales CSV generation failed (General Error): " . $e->getMessage());
    die('Failed to generate Daily Sales CSV. An unexpected error occurred.');
}
?>