<?php
session_start(); // Must be at the very top of the file

// Include your database connection file
require_once 'db_connect.php'; // This should establish a MySQLi connection as $conn

// Enable error reporting for development (disable or set to 0 in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Access Control ---
// Redirect if user is not logged in or session variables are missing
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: index.php"); // Redirect to login page
    exit;
}
require_once __DIR__ . '/vendor/autoload.php';
// Define allowed roles for this page
$allowedRoles = ['admin', 'manager'];

// Check if the user's role has permission
if (!in_array($_SESSION['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    die('Access denied. You do not have permission to view this page.');
}
// --- End Access Control ---

$username = $_SESSION['username'];
$role = $_SESSION['role'];
$roleTitle = ucfirst($role); // Capitalize the first letter of the role for display

// Initialize default date to today
$selectedDate = date('Y-m-d');
if (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
    $selectedDate = $_GET['date'];
}

// Initialize selected cashier username
$selectedCashierUsername = $_GET['cashier_username'] ?? ''; // Default to empty string for 'All Cashiers'

$cashiers = []; // Array to store cashier data

// Fetch list of cashiers for the dropdown filter
try {
    $cashierQuery = "SELECT id, username FROM users WHERE role = 'cashier' ORDER BY username ASC";
    if ($stmt = $conn->prepare($cashierQuery)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $cashiers[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare cashier query: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database error fetching cashiers: " . $e->getMessage());
    // Gracefully continue, cashiers array will be empty
} finally {
    // Close the connection if it's still open (good practice)
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
    <title><?php echo htmlspecialchars($roleTitle); ?> Reports</title>
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
        .sidebar .nav-link svg { margin-right: 10px; width: 18px; height: 18px; stroke-width: 2; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff !important;
        }
        .sidebar .nav-item.mt-4 .nav-link { color: #dc3545 !important; }
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
            .sidebar { position: relative; width: 100%; padding: 15px; height: auto; overflow-y: visible; }
            main { margin-left: 0; }
        }
        .card { border: none; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); margin-bottom: 25px; }
        .card-header { background-color: #007bff; color: #fff; border-bottom: 1px solid rgba(0,0,0,.125); border-radius: 8px 8px 0 0; }
        .card-body h5 { color: #007bff; }
        .form-control, .form-select { border-radius: 5px; }
    </style>
</head>
<body><div class="container-fluid">
    <div class="row">
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="card">
                <div class="card-header bg-info">
                    Available Reports
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h5>Product Sales Report</h5>
                            <p>Aggregated sales data per product for the selected date.</p>
                            <a id="productSalesPdf" href="export_product_sales_pdf.php?date=<?php echo htmlspecialchars($selectedDate); ?>" target="_blank" class="btn btn-outline-danger me-2">
                                <span data-feather="file-text"></span> Download PDF
                            </a>
                            <a id="productSalesCsv" href="export_product_sales_csv.php?date=<?php echo htmlspecialchars($selectedDate); ?>" class="btn btn-outline-success">
                                <span data-feather="download"></span> Download CSV
                            </a>
                        </div>
                        <div class="col-md-6">
                            <h5>Daily Sales Detail Report</h5>
                            <p>Detailed transaction list for the selected date and optional cashier.</p>
                            <a id="dailySalesPdf" href="export_daily_sales_report_pdf.php?date=<?php echo htmlspecialchars($selectedDate); ?><?php echo (!empty($selectedCashierUsername) ? '&cashier_username=' . urlencode($selectedCashierUsername) : ''); ?>" target="_blank" class="btn btn-outline-danger me-2">
                                <span data-feather="file-text"></span> Download PDF
                            </a>
                            <a id="dailySalesCsv" href="export_daily_sales_csv.php?date=<?php echo htmlspecialchars($selectedDate); ?><?php echo (!empty($selectedCashierUsername) ? '&cashier_username=' . urlencode($selectedCashierUsername) : ''); ?>" class="btn btn-outline-success">
                                <span data-feather="download"></span> Download CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>
               <a href="my_dashboard.php" class="btn  btn-success">Dashboard</a>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    feather.replace(); // Initialize Feather Icons

    document.addEventListener('DOMContentLoaded', function() {
        const reportDateInput = document.getElementById('reportDate');
        const cashierSelect = document.getElementById('cashierSelect');

        const productSalesPdfLink = document.getElementById('productSalesPdf');
        const productSalesCsvLink = document.getElementById('productSalesCsv');
        const dailySalesPdfLink = document.getElementById('dailySalesPdf');
        const dailySalesCsvLink = document.getElementById('dailySalesCsv');

        function updateReportLinks() {
            const date = reportDateInput.value;
            const cashierUsername = cashierSelect.value; // Will be '' if 'All Cashiers' is selected

            // Base URLs for the reports
            const productSalesPdfBase = 'export_product_sales_pdf.php';
            const productSalesCsvBase = 'export_product_sales_csv.php';
            // Ensure this matches your actual PDF export script filename
            const dailySalesPdfBase = 'export_daily_sales_report_pdf.php';
            const dailySalesCsvBase = 'export_daily_sales_csv.php';

            // Update Product Sales links (only depends on date)
            productSalesPdfLink.href = `${productSalesPdfBase}?date=${date}`;
            productSalesCsvLink.href = `${productSalesCsvBase}?date=${date}`;

            // Update Daily Sales links (depends on date and optional cashier)
            let dailySalesParams = `date=${date}`;
            if (cashierUsername !== '') {
                dailySalesParams += `&cashier_username=${encodeURIComponent(cashierUsername)}`;
            }
            dailySalesPdfLink.href = `${dailySalesPdfBase}?${dailySalesParams}`;
            dailySalesCsvLink.href = `${dailySalesCsvBase}?${dailySalesParams}`;
        }

        // Add event listeners to update links when inputs change
        reportDateInput.addEventListener('change', updateReportLinks);
        cashierSelect.addEventListener('change', updateReportLinks);

        // Call once on page load to ensure initial links are correct based on PHP pre-selection
        updateReportLinks();
    });
</script>
</body>
</html>