<?php
// daily_sales_report.php
session_start(); // Must be at the very top of the file

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Access Control ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401); // Unauthorized
    die('Unauthorized access. Please log in.'); // Plain text error for direct access
}
require_once __DIR__ . '/vendor/autoload.php';
// Define allowed roles for this report page
$allowedRoles = ['admin', 'manager']; // Only admin and manager can view this report

if (!in_array($_SESSION['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    die('Access denied. You do not have permission to view this report.'); // Plain text error
}
// --- End Access Control ---

include 'db_connect.php'; // Ensure this file establishes a MySQLi connection as $conn

// Get date parameter or default to today
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400); // Bad Request
    die('Error: Invalid date format. Date must be YYYY-MM-DD.');
}

// Optional: Get cashier username parameter for filtering
$cashier_username = $_GET['cashier_username'] ?? null;
if ($cashier_username !== null) {
    $cashier_username = trim($cashier_username);
    if (empty($cashier_username)) {
        http_response_code(400);
        die('Error: Invalid cashier username provided.');
    }
}

$salesGrouped = []; // To store sales grouped by transaction
$overallGrandTotal = 0; // To store the sum of grand totals for all transactions on this date

try {
    // Query detailed sales for the given date and optional cashier
    // This query joins sales, sale_items, products, and users tables
    $query = "
        SELECT
            s.sale_id,                           -- Primary key of the sale
            s.transaction_code,                  -- Unique transaction code
            s.sale_date,
            s.subtotal AS transaction_subtotal,  -- Subtotal before discount from 'sales' table
            s.discount_percentage,
            s.discount_amount,                   -- Discount amount from 'sales' table
            s.grand_total AS transaction_grand_total, -- Final total from 'sales' table
            s.amount_paid,
            s.change_amount,
            s.customer_name,
            u.username AS cashier_name,
            p.name AS product_name,              -- CORRECTED: Used p.name
            si.quantity,
            si.unit_price AS item_unit_price,    -- CORRECTED: Used si.unit_price
            si.line_total AS item_line_total     -- Line total for this item from 'sale_items'
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
    $query .= " ORDER BY s.sale_date DESC, s.sale_id, p.name"; // Order by sale_date, then transaction, then product name

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new mysqli_sql_exception("Failed to prepare query: " . $conn->error);
    }

    if ($cashier_username !== null) {
        $stmt->bind_param("ss", $date, $cashier_username);
    } else {
        $stmt->bind_param("s", $date);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Group sales by transaction_id for PDF generation logic
    while ($row = $result->fetch_assoc()) {
        $sale_id = $row['sale_id'];
        if (!isset($salesGrouped[$sale_id])) {
            $salesGrouped[$sale_id] = [
                'transaction_code' => $row['transaction_code'],
                'cashier' => $row['cashier_name'] ?? 'Unknown',
                'date' => $row['sale_date'] ?? '',
                'transaction_subtotal' => $row['transaction_subtotal'],
                'discount_percentage' => $row['discount_percentage'],
                'discount_amount' => $row['discount_amount'],
                'transaction_grand_total' => $row['transaction_grand_total'],
                'amount_paid' => $row['amount_paid'],
                'change_amount' => $row['change_amount'],
                'customer_name' => $row['customer_name'] ?: 'Walk-in Customer',
                'items' => []
            ];
            $overallGrandTotal += floatval($row['transaction_grand_total']);
        }
        $salesGrouped[$sale_id]['items'][] = [
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'unit_price' => $row['item_unit_price'],
            'line_total' => $row['item_line_total']
        ];
    }

    $stmt->close();
    // $conn->close(); // Keep connection open if fetching products for the form later, or close here if script ends.
    // For a PDF generation script, it's usually okay to close here.

    // No HTML output, just PDF generation below.
    // ... (TCPDF code will go here, as in the next block) ...

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    error_log("Daily sales detailed report generation failed: " . $e->getMessage());
    die('Failed to generate Daily Sales Report. Database Error: ' . $e->getMessage()); // Show detailed error for debugging
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}

// --- TCPDF GENERATION (if this script outputs PDF directly, like the second one) ---
// If this script is *only* for the detailed report, and not a PDF export,
// then the TCPDF code should be removed and replaced with HTML output.
// Assuming this is the PDF export for daily sales, not just the HTML view.
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator('Supermarket Sales System');
$pdf->SetAuthor('Supermarket');
$pdf->SetTitle('Daily Sales Report - ' . date('F j, Y', strtotime($date)));
$pdf->SetSubject('Daily Sales Data');
$pdf->SetKeywords('Sales, Report, Daily, PDF');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

$pdf->AddPage();

// Report Header
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Supermarket Daily Sales Report', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Date: ' . date('F j, Y', strtotime($date)), 0, 1, 'C');
if ($cashier_username) {
    $pdf->Cell(0, 10, 'Cashier: ' . $cashier_username, 0, 1, 'C');
}
$pdf->Ln(5);

if (empty($salesGrouped)) {
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->Cell(0, 10, 'No sales records found for this date' . ($cashier_username ? ' and cashier' : '') . '.', 0, 1, 'C');
} else {
    foreach ($salesGrouped as $sale_id => $data) { // Use sale_id here instead of tid
        // Transaction header
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, "Transaction Code: " . $data['transaction_code'] .
                         " | Cashier: " . $data['cashier'] .
                         " | Customer: " . ($data['customer_name'] ?: 'N/A') .
                         " | Date: " . date('Y-m-d H:i:s', strtotime($data['date'])), 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Ln(2); // Small break

        // Table header for individual items within transaction
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(60, 7, 'Product', 1);
        $pdf->Cell(30, 7, 'Unit Price (N)', 1, 0, 'R');
        $pdf->Cell(20, 7, 'Qty', 1, 0, 'R');
        $pdf->Cell(30, 7, 'Line Total (N)', 1, 0, 'R');
        $pdf->Ln();

        // Items
        $pdf->SetFont('helvetica', '', 9);
        foreach ($data['items'] as $item) {
            $pdf->Cell(60, 7, $item['product_name'], 1);
            $pdf->Cell(30, 7, number_format($item['unit_price'], 2), 1, 0, 'R'); // CORRECTED: item_unit_price
            $pdf->Cell(20, 7, $item['quantity'], 1, 0, 'R');
            $pdf->Cell(30, 7, number_format($item['line_total'], 2), 1, 0, 'R'); // CORRECTED: item_line_total
            $pdf->Ln();
        }

        // Transaction summary
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(110, 7, 'Transaction Subtotal:', 1, 0, 'R');
        $pdf->Cell(30, 7, 'N' . number_format($data['transaction_subtotal'], 2), 1, 0, 'R'); // Using transaction_subtotal from sales table
        $pdf->Ln();

        if ($data['discount_percentage'] > 0) {
            $pdf->Cell(110, 7, 'Discount (' . $data['discount_percentage'] . '%):', 1, 0, 'R');
            $pdf->Cell(30, 7, 'N' . number_format($data['discount_amount'], 2), 1, 0, 'R'); // Using discount_amount from sales table
            $pdf->Ln();
        }

        $pdf->Cell(110, 7, 'Grand Total (Transaction):', 1, 0, 'R');
        $pdf->Cell(30, 7, 'N' . number_format($data['transaction_grand_total'], 2), 1, 0, 'R'); // Using grand_total from sales table
        $pdf->Ln();

        $pdf->Cell(110, 7, 'Amount Paid:', 1, 0, 'R');
        $pdf->Cell(30, 7, 'N' . number_format($data['amount_paid'], 2), 1, 0, 'R');
        $pdf->Ln();

        $pdf->Cell(110, 7, 'Change:', 1, 0, 'R');
        $pdf->Cell(30, 7, 'N' . number_format($data['change_amount'], 2), 1, 0, 'R');
        $pdf->Ln(10); // Spacing between transactions
    }

    // Overall Grand Total for the entire report
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(140, 10, 'OVERALL DAILY GRAND TOTAL:', 1, 0, 'R');
    $pdf->Cell(30, 10, 'N' . number_format($overallGrandTotal, 2), 1, 0, 'R');
}

// Output PDF
$filename_date = date('Y-m-d', strtotime($date));
$filename = 'daily_sales_report_' . $filename_date . ($cashier_username ? '_' . str_replace(' ', '_', $cashier_username) : '') . '.pdf';
$pdf->Output($filename, 'D');

?>