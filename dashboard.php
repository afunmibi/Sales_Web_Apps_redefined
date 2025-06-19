<?php
// index.php
session_start();
// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supermarket Sales System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container mt-5">
    <h2>Supermarket Sales System</h2>
    
    <div class="mb-3">
        <label for="cashierName" class="form-label">Cashier Name</label>
        <input type="text" class="form-control" id="cashierName" placeholder="Enter cashier name" required>
    </div>

    <table class="table table-bordered" id="salesTable">
        <thead>
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><input type="text" class="form-control product" required></td>
                <td><input type="number" class="form-control price" min="0.01" step="0.01" required></td>
                <td><input type="number" class="form-control qty" min="1" step="1" required></td>
                <td><input type="text" class="form-control subtotal" readonly></td>
                <td><button class="btn btn-danger removeRow">Remove</button></td>
            </tr>
        </tbody>
    </table>
    <button class="btn btn-primary" id="addRow">Add Row</button>
    <h4 class="mt-3">Grand Total: ₦<span id="grandTotal">0.00</span></h4>
    <button class="btn btn-success" id="submitSales">Submit Sales</button>
</div>

<!-- Thank You Modal -->
<div class="modal fade" id="thankYouModal" tabindex="-1" aria-labelledby="thankYouModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="thankYouModalLabel">Success!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Sales recorded successfully. Redirecting to receipt...
            </div>
        </div>
    </div>
</div>

<div class="mx-auto my-3 text-center">
    <a href="daily_sales_summary.php" class="btn btn-info mt-3">View Transaction Sales Summary</a>
    <a href="product_sales_summary.php" class="btn btn-info mt-3">View All Products Sales Summary</a>
</div>

<script>
function calculateSubtotal(row) {
    let price = parseFloat(row.find(".price").val()) || 0;
    let qty = parseFloat(row.find(".qty").val()) || 0;
    let subtotal = price * qty;
    row.find(".subtotal").val(subtotal.toFixed(2));
    calculateGrandTotal();
}

function calculateGrandTotal() {
    let total = 0;
    $(".subtotal").each(function () {
        total += parseFloat($(this).val()) || 0;
    });
    $("#grandTotal").text(total.toFixed(2));
}

$(document).ready(function () {
    // Add row
    $("#addRow").click(function () {
        let row = `<tr>
            <td><input type="text" class="form-control product" required></td>
            <td><input type="number" class="form-control price" min="0.01" step="0.01" required></td>
            <td><input type="number" class="form-control qty" min="1" step="1" required></td>
            <td><input type="text" class="form-control subtotal" readonly></td>
            <td><button class="btn btn-danger removeRow">Remove</button></td>
        </tr>`;
        $("#salesTable tbody").append(row);
    });

    // Remove row
    $(document).on("click", ".removeRow", function () {
        if ($("#salesTable tbody tr").length > 1) {
            $(this).closest("tr").remove();
            calculateGrandTotal();
        } else {
            alert("At least one row is required.");
        }
    });

    // Calculate subtotal on input
    $(document).on("input", ".price, .qty", function () {
        calculateSubtotal($(this).closest("tr"));
    });

    // Submit sales
    $("#submitSales").click(function () {
        let cashierName = $("#cashierName").val().trim();
        if (!cashierName) {
            alert("Please enter cashier name.");
            return;
        }

        let salesData = [];
        let valid = true;
        $("#salesTable tbody tr").each(function () {
            let product = $(this).find(".product").val().trim();
            let price = parseFloat($(this).find(".price").val()) || 0;
            let qty = parseInt($(this).find(".qty").val()) || 0;
            let subtotal = parseFloat($(this).find(".subtotal").val()) || 0;

            if (!product || price <= 0 || qty <= 0) {
                valid = false;
                alert("Please fill in all fields with valid values.");
                return false;
            }

            // Validate subtotal
            if (Math.abs(subtotal - (price * qty)) > 0.01) {
                valid = false;
                alert("Subtotal does not match price * quantity for product: " + product);
                return false;
            }

            salesData.push({ product, price, qty, subtotal });
        });

        if (!valid || salesData.length === 0) {
            return;
        }

        $.ajax({
            url: "submit_sales.php",
            type: "POST",
            data: {
                csrf_token: "<?php echo $_SESSION['csrf_token']; ?>",
                cashier_name: cashierName,
                sales: JSON.stringify(salesData)
            },
            success: function (response) {
                try {
                    let res = JSON.parse(response);
                    if (res.message && res.transaction_id) {
                        $("#thankYouModal").modal("show");
                        setTimeout(() => {
                            $("#thankYouModal").modal("hide");
                            window.open("receipt.php?transaction_id=" + res.transaction_id, "_blank");
                            // Reset form
                            $("#cashierName").val("");
                            $("#salesTable tbody").html(`
                                <tr>
                                    <td><input type="text" class="form-control product" required></td>
                                    <td><input type="number" class="form-control price" min="0.01" step="0.01" required></td>
                                    <td><input type="number" class="form-control qty" min="1" step="1" required></td>
                                    <td><input type="text" class="form-control subtotal" readonly></td>
                                    <td><button class="btn btn-danger removeRow">Remove</button></td>
                                </tr>
                            `);
                            $("#grandTotal").text("0.00");
                        }, 2000);
                    } else {
                        alert("Error: " + (res.error || "Unknown error"));
                    }
                } catch (e) {
                    alert("Unexpected response: " + response);
                }
            },
            error: function (xhr, status, error) {
                alert("Request failed: " + (xhr.responseJSON?.error || error));
            }
        });
    });
});
</script>
</body>
</html>