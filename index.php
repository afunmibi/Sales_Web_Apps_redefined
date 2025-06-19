<?php
// Start a new session or resume the existing one.
session_start();

// Include your database connection file.
// This file (e.g., 'db_connect.php') should establish a MySQLi connection
// and typically set it to a variable like $conn.
require_once 'db_connect.php';

// Initialize an error variable to store any login errors.
// This prevents "Undefined variable" warnings if no errors occur on initial load.
$error = '';

// Check if the form was submitted using the POST method.
// Also, ensure that both 'username' and 'password' fields are set in the POST data.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    // Sanitize and trim the input to prevent basic injection issues and leading/trailing spaces.
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Prepare a SQL statement to prevent SQL injection.
    // We are selecting 'id', 'username', 'password_hash' (the column storing the hashed password),
    // and 'role' from the 'users' table, based on the provided username.
    $stmt = $conn->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");

    // Check if the prepared statement was successful.
    if ($stmt === false) {
        // Log the error for debugging purposes (e.g., to your server's error logs).
        error_log("Login prepare failed: " . $conn->error);
        // Provide a generic error message to the user for security.
        $error = "An internal error occurred. Please try again later.";
    } else {
        // Bind the username parameter to the prepared statement. 's' denotes a string type.
        $stmt->bind_param("s", $username);
        // Execute the prepared statement.
        $stmt->execute();
        // Get the result set from the executed statement.
        $res = $stmt->get_result();

        // Check if exactly one user was found with the given username.
        if ($res->num_rows === 1) {
            // Fetch the user's data as an associative array.
            $user = $res->fetch_assoc();

            // Verify the provided plain-text password against the stored hashed password.
            // password_verify() safely compares the password without unhashing it.
            if (password_verify($password, $user['password_hash'])) {
                // If the password is correct, set essential session variables.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role']; // Store the user's role for role-based access control.

                // Redirect the user to the appropriate dashboard page.
                // Assuming your unified dashboard file is named 'dashboard.php'.
                // If you are using 'cashier_dashboard.php' as your unified dashboard,
                // change this line to: header("Location: cashier_dashboard.php");
                header("Location: my_dashboard.php");
                exit; // Crucial: Terminate script execution after redirection to prevent further output.
            } else {
                // If password verification fails, set a generic error message.
                $error = "Invalid username or password.";
            }
        } else {
            // If no user or more than one user is found (which shouldn't happen with unique usernames),
            // set a generic error message.
            $error = "Invalid username or password.";
        }
        // Close the prepared statement to free up resources.
        $stmt->close();
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // This block catches cases where the form was submitted, but username/password fields were empty.
    $error = "Please enter both username and password.";
}

// Close the database connection if it's open.
// This is good practice to release database resources.
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sales System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Custom CSS for layout and styling */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5; /* Light grey background */
        }
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Make container take full viewport height */
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); /* Soft shadow for the card */
        }
        .form-control:focus {
            border-color: #80bdff; /* Highlight border on focus */
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25); /* Subtle shadow on focus */
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            transition: background-color 0.2s ease, border-color 0.2s ease; /* Smooth transition for button hover */
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #004085;
        }
        .alert {
            margin-bottom: 1rem; /* Space below alert messages */
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card p-4 shadow-lg w-100" style="max-width: 400px;">
            <h4 class="mb-4 text-center text-primary">Sales System Login</h4>
            <?php if (!empty($error)) : // Display error message if $error is not empty ?>
                <div class='alert alert-danger text-center'>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="usernameInput" class="form-label">Username</label>
                    <input type="text" name="username" id="usernameInput" class="form-control" required autocomplete="username">
                </div>
                <div class="mb-3">
                    <label for="passwordInput" class="form-label">Password</label>
                    <input type="password" name="password" id="passwordInput" class="form-control" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-3">Login</button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>