<?php
session_start(); // Always start the session at the very top

// --- Access Control ---
if (!isset($_SESSION['role'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit;
}

// Define allowed roles for this report page
$allowedRoles = ['admin', 'manager']; // Only admin and manager can view this report
if (!in_array($_SESSION['role'], $allowedRoles)) {
    echo "Access denied. You do not have permission to view this report.";
    exit;
}
// --- End Access Control ---

include 'db_connect.php';

// Get date and cashier parameters or default to today and empty cashier
$date = $_GET['date'] ?? date('Y-m-d');
$cashier = $_GET['cashier'] ?? '';

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid date format']));
}

try {
    // Query sales for the given date, with optional cashier filter
    $query = "SELECT * FROM sales WHERE DATE(sale_date) = ?";
    if ($cashier) {
        $query .= " AND cashier_name = ?";
    }
    $query .= " ORDER BY transaction_id, id";

    $stmt = $conn->prepare($query);

    if ($cashier) {
        $stmt->bind_param("ss", $date, $cashier);
    } else {
        $stmt->bind_param("s", $date);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // Organize sales by transaction_id
    $salesGrouped = [];
    $grandTotal = 0;

    while ($row = $result->fetch_assoc()) {
        $tid = $row['transaction_id'];
        if (!isset($salesGrouped[$tid])) {
            $salesGrouped[$tid] = [
                'cashier' => $row['cashier_name'] ?? 'Unknown',
                'date' => $row['sale_date'] ?? '',
                'items' => [],
                'total' => 0
            ];
        }
        $salesGrouped[$tid]['items'][] = $row;
        $salesGrouped[$tid]['total'] += floatval($row['subtotal']);
        $grandTotal += floatval($row['subtotal']);
    }

    $stmt->close();

    // Fetch distinct cashier names for the dropdown filter
    $cashiers = $conn->query("SELECT DISTINCT cashier_name FROM sales ORDER BY cashier_name");
    $cashierNames = [];
    while ($row = $cashiers->fetch_assoc()) {
        $cashierNames[] = $row['cashier_name'];
    }
    $cashiers->close();

} catch (Exception $e) {
    http_response_code(500);
    // IMPORTANT: In a production environment, you should log $e->getMessage()
    // and show a generic error to the user, not the detailed error.
    die(json_encode(['error' => 'Database Error: ' . $e->getMessage()]));
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
    <title>Daily Sales Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .card-header { font-size: 1.1rem; }
        .table th, .table td { vertical-align: middle; }
        .table .text-right { text-align: right; }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4">
    <h3 class="text-center">Daily Sales Summary (<?= htmlspecialchars(date('F j, Y', strtotime($date))) ?>)</h3>

    <div class="mb-3">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-auto">
                <label for="date" class="form-label mb-0">Date:</label>
                <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($date) ?>" required>
            </div>
            <div class="col-auto">
                <label for="cashier" class="form-label mb-0">Cashier:</label>
                <select class="form-select" id="cashier" name="cashier">
                    <option value="">All Cashiers</option>
                    <?php foreach ($cashierNames as $name): ?>
                        <option value="<?= htmlspecialchars($name) ?>" <?= ($cashier === $name) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary mt-4">Filter</button>
            </div>
            <div class="col-auto ms-auto">
                <a href="export_daily_sales_pdf.php?date=<?= htmlspecialchars($date) ?><?= $cashier ? '&cashier=' . urlencode($cashier) : '' ?>" class="btn btn-danger mt-4">Export PDF</a>
                <a href="export_daily_sales_excel.php?date=<?= htmlspecialchars($date) ?><?= $cashier ? '&cashier=' . urlencode($cashier) : '' ?>" class="btn btn-success mt-4">Export Excel</a>
            </div>
        </form>
    </div>

    <?php if (!empty($salesGrouped)): ?>
        <?php foreach ($salesGrouped as $tid => $data): ?>
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <strong>Transaction ID:</strong> <?= htmlspecialchars($tid) ?> |
                    <strong>Cashier:</strong> <?= htmlspecialchars($data['cashier']) ?> |
                    <strong>Date:</strong> <?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($data['date']))) ?>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">Price (₦)</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Subtotal (₦)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['items'] as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['product']) ?></td>
                                    <td class="text-right"><?= number_format($item['price'], 2) ?></td>
                                    <td class="text-right"><?= htmlspecialchars($item['quantity']) ?></td>
                                    <td class="text-right"><?= number_format($item['subtotal'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="text-end p-2">
                        <strong>Total: ₦<?= number_format($data['total'], 2) ?></strong>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="alert alert-success text-end">
            <strong>Grand Total: ₦<?= number_format($grandTotal, 2) ?></strong>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">No sales records found for this date.</div>
    <?php endif; ?>
    <div class="text-center">
        <a href="admin_dashboard.php" class="btn btn-secondary mt-3">⬅️ Back to Dashboard</a>
    </div>
</div>
</body>
</html>