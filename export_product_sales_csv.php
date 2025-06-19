<?php
// export_product_sales_csv.php
session_start(); // Must be at the very top of the file

// Enable error reporting for development (disable or set to 0 in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Access Control (Crucial for Security!) ---
// If the user is not logged in or session variables are missing, deny access.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401); // Unauthorized
    die('Unauthorized access. Please log in to view this report.'); // Plain text error message
}

// Define allowed roles for this report. Only 'admin' and 'manager' should typically view this.
$allowedRoles = ['admin', 'manager'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    die('Access denied. You do not have permission to export this report.'); // Plain text error message
}
// --- End Access Control ---

// Include your database connection file
require_once 'db_connect.php'; // This should establish a MySQLi connection as $conn

// Validate date parameter from GET request
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400); // Bad Request
    die('Error: Invalid date format. Date must be YYYY-MM-DD.'); // Plain text error
}

$sales = [];          // Array to store aggregated sales data
$grandTotal = 0;      // Variable to store the overall total sales amount

try {
    // SQL Query to aggregate sales by product name for a specific date
    // CORRECTED:
    // - Uses s.sale_id for the join condition with sale_items
    // - Uses si.line_total (quantity * unit_price) directly from sale_items
    $query = "
        SELECT
            p.name AS product_name,              -- Product name from the 'products' table
            SUM(si.quantity) AS total_qty,       -- Total quantity sold for each product
            SUM(si.line_total) AS total_amount_sold -- Total sales amount for each product, using line_total
        FROM
            sales s
        JOIN
            sale_items si ON s.sale_id = si.sale_id -- Corrected join condition (s.sale_id instead of s.id)
        JOIN
            products p ON si.product_id = p.id
        WHERE
            DATE(s.sale_date) = ?                -- Filter sales by the selected date
        GROUP BY
            p.name                               -- Group results by product name
        ORDER BY
            p.name ASC                           -- Order results alphabetically by product name
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
        while ($row = $result->fetch_assoc()) {
            $sales[] = $row;
            $grandTotal += floatval($row['total_amount_sold']);
        }

        // Close the prepared statement
        $stmt->close();
    } else {
        // If prepare fails, throw an exception
        throw new mysqli_sql_exception("Failed to prepare product sales CSV query: " . $conn->error);
    }

    // Close the database connection as data has been fetched
    $conn->close();

    // --- Set HTTP Headers for CSV Download ---
    $filename_date = date('Y-m-d', strtotime($date));
    $filename = 'product_sales_report_' . $filename_date . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate'); // Standard cache control
    header('Pragma: no-cache'); // For IE compatibility
    header('Expires: 0'); // For IE compatibility

    // --- Generate CSV Content ---
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // Add UTF-8 BOM for better Excel compatibility

    // Report Header Lines
    fputcsv($output, ['Supermarket Product Sales Report']);
    fputcsv($output, ['Date: ' . date('F j, Y', strtotime($date))]);
    fputcsv($output, []); // Empty row for spacing

    if (empty($sales)) {
        fputcsv($output, ['No product sales records found for this date.']);
    } else {
        // CSV Table Header
        fputcsv($output, ['Product', 'Total Quantity', 'Total Sales (N)']);

        // CSV Table Data
        foreach ($sales as $item) {
            fputcsv($output, [
                $item['product_name'],
                $item['total_qty'],
                number_format($item['total_amount_sold'], 2) // Format currency for display
            ]);
        }

        // Grand Total Line
        fputcsv($output, ['', 'Grand Total:', 'N' . number_format($grandTotal, 2)]);
    }

    fclose($output); // Close the CSV output stream
    exit; // Terminate script execution after file output

} catch (mysqli_sql_exception $e) {
    // Catch database-specific exceptions
    // Log the detailed error for debugging purposes (check your server's error logs)
    error_log("Product sales CSV generation failed (DB Error): " . $e->getMessage());

    // Display a generic error message for the user, but detailed for debugging
    http_response_code(500); // Internal Server Error
    die('Failed to generate Product Sales CSV. Database error: ' . $e->getMessage());
} catch (Exception $e) {
    // Catch any other general exceptions
    error_log("Product sales CSV generation failed (General Error): " . $e->getMessage());

    http_response_code(500); // Internal Server Error
    die('Failed to generate Product Sales CSV. An unexpected error occurred. Please try again later.');
} finally {
    // Ensure the database connection is closed if it was opened and is still active
    // This is less critical here as we explicitly close it after fetching, but good as a fallback
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>