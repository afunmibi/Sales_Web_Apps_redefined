<?php
// export_product_sales_pdf.php
session_start(); // Must be at the very top of the file

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Access Control (Crucial for Security!) ---
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

include 'db_connect.php'; // Assumed to handle its own connection errors or throw exceptions
require_once 'vendor/tecnickcom/tcpdf/tcpdf.php'; // Ensure TCPDF is correctly installed via Composer

// Validate date parameter
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400); // Bad Request
    die('Error: Invalid date format. Date must be YYYY-MM-DD.'); // Plain text error
}

$sales = [];
$grandTotal = 0;

try {
    // Corrected Query:
    // - Selects p.name (from products table) as 'product_name'
    // - Uses si.line_total (from sale_items table) for summing total amount sold
    $stmt = $conn->prepare("
        SELECT
            p.name AS product_name,              -- CORRECTED: Use p.name for product name
            SUM(si.quantity) AS total_qty,
            SUM(si.line_total) AS total_amount_sold -- CORRECTED: Use si.line_total
        FROM
            sales s
        JOIN
            sale_items si ON s.sale_id = si.sale_id -- CORRECTED: s.sale_id for join
        JOIN
            products p ON si.product_id = p.id
        WHERE
            DATE(s.sale_date) = ?
        GROUP BY
            p.name                               -- CORRECTED: Group by p.name
        ORDER BY
            p.name ASC                           -- CORRECTED: Order by p.name
    ");
    if ($stmt === false) {
        throw new mysqli_sql_exception("Failed to prepare product sales PDF query: " . $conn->error);
    }

    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
        $grandTotal += floatval($row['total_amount_sold']);
    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("Product sales PDF generation failed: " . $e->getMessage()); // Log error
    die('Failed to generate Product Sales PDF. Database Error: ' . $e->getMessage()); // Show detailed error for debugging
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}

// Create PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator('Supermarket Sales System');
$pdf->SetAuthor('Supermarket');
$pdf->SetTitle('Product Sales Report - ' . date('F j, Y', strtotime($date)));
$pdf->SetSubject('Product Sales Data');
$pdf->SetKeywords('Sales, Report, Product, PDF');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

$pdf->AddPage();

// Report Header Content
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Supermarket Product Sales Report', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Date: ' . date('F j, Y', strtotime($date)), 0, 1, 'C');
$pdf->Ln(5);

if (empty($sales)) {
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->Cell(0, 10, 'No product sales records found for this date.', 0, 1, 'C');
} else {
    // Table header for aggregated items
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(80, 8, 'Product', 1);
    $pdf->Cell(40, 8, 'Total Quantity', 1, 0, 'R');
    $pdf->Cell(40, 8, 'Total Sales (N)', 1, 0, 'R');
    $pdf->Ln();

    // Table data
    $pdf->SetFont('helvetica', '', 10);
    foreach ($sales as $row) {
        $pdf->Cell(80, 8, $row['product_name'], 1);
        $pdf->Cell(40, 8, $row['total_qty'], 1, 0, 'R');
        $pdf->Cell(40, 8, number_format($row['total_amount_sold'], 2), 1, 0, 'R');
        $pdf->Ln();
    }

    // Grand total
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(120, 10, 'Grand Total:', 1);
    $pdf->Cell(40, 10, 'N' . number_format($grandTotal, 2), 1, 0, 'R');
}

// Output PDF
$filename_date = date('Y-m-d', strtotime($date));
$pdf->Output('product_sales_report_' . $filename_date . '.pdf', 'D');

?>