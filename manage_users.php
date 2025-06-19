<?php
session_start();
require_once 'db_connect.php'; // This should now provide $conn (MySQLi connection)

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize messages
$error_message = '';
$success_message = '';

// Check for and display messages from previous redirects
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// 1. Authentication Check
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// 2. Authorization Check: Only 'admin' can access user management with full control
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Access denied. You do not have permission to manage users.";
    header("Location: dashboard.php"); // Redirect to a more appropriate page for non-admins
    exit;
}

$roleTitle = ucfirst($_SESSION['role']);
$users = [];

// Function to handle database errors and set messages
function handleDbError($stmt, $context) {
    global $error_message;
    $errorMessage = $stmt->error ?? "Unknown error.";
    error_log("Error " . $context . ": " . $errorMessage);
    $error_message = "Database error: Could not " . $context . ".";
}

// --- Handle User Actions (Add, Edit, Delete) ---

// Handle Add User
if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $new_username = trim($_POST['username'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $new_role = $_POST['role'] ?? '';

    if (empty($new_username) || empty($new_password) || empty($new_role)) {
        $_SESSION['error_message'] = "All fields are required to add a new user.";
    } elseif (!in_array($new_role, ['cashier', 'manager', 'admin'])) {
        $_SESSION['error_message'] = "Invalid role specified.";
    } else {
        try {
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt_check->bind_param("s", $new_username);
            $stmt_check->execute();
            $stmt_check->bind_result($count);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($count > 0) {
                $_SESSION['error_message'] = "Username already exists. Please choose a different one.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_insert = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
                $stmt_insert->bind_param("sss", $new_username, $hashed_password, $new_role);
                if ($stmt_insert->execute()) {
                    $_SESSION['success_message'] = "User '{$new_username}' added successfully!";
                } else {
                    handleDbError($stmt_insert, "adding user");
                }
                $stmt_insert->close();
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Error adding user: " . $e->getMessage());
            $_SESSION['error_message'] = "Database error: Could not add user.";
        }
    }
    header("Location: manage_users.php"); // PRG pattern
    exit;
}

// Handle Delete User (GET request)
if (isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['id'])) {
    $user_id_to_delete = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($user_id_to_delete) {
        try {
            if ($user_id_to_delete === $_SESSION['user_id']) {
                $_SESSION['error_message'] = "You cannot delete your own account!";
            } else {
                $stmt_check_role = $conn->prepare("SELECT role FROM users WHERE id = ?");
                $stmt_check_role->bind_param("i", $user_id_to_delete);
                $stmt_check_role->execute();
                $stmt_check_role->bind_result($target_user_role);
                $stmt_check_role->fetch();
                $stmt_check_role->close();

                if ($target_user_role === 'admin') {
                    $_SESSION['error_message'] = "Cannot delete another administrator account directly from here.";
                } else {
                    $stmt_delete = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt_delete->bind_param("i", $user_id_to_delete);
                    if ($stmt_delete->execute()) {
                        $_SESSION['success_message'] = "User deleted successfully!";
                    } else {
                        handleDbError($stmt_delete, "deleting user");
                    }
                    $stmt_delete->close();
                }
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Error deleting user: " . $e->getMessage());
            $_SESSION['error_message'] = "Database error: Could not delete user.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid user ID for deletion.";
    }
    header("Location: manage_users.php"); // PRG pattern
    exit;
}

// Handle Edit User (POST for form submission)
if (isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $edit_user_id = filter_var($_POST['edit_id'] ?? '', FILTER_VALIDATE_INT);
    $edit_username = trim($_POST['edit_username'] ?? '');
    $edit_role = $_POST['edit_role'] ?? '';
    $edit_password = $_POST['edit_password'] ?? '';

    if (!$edit_user_id || empty($edit_username) || empty($edit_role)) {
        $_SESSION['error_message'] = "Invalid data provided for user update.";
    } elseif (!in_array($edit_role, ['cashier', 'manager', 'admin'])) {
        $_SESSION['error_message'] = "Invalid role specified for update.";
    } else {
        try {
            $stmt_check_username = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $stmt_check_username->bind_param("si", $edit_username, $edit_user_id);
            $stmt_check_username->execute();
            $stmt_check_username->bind_result($count);
            $stmt_check_username->fetch();
            $stmt_check_username->close();

            if ($count > 0) {
                $_SESSION['error_message'] = "Username '{$edit_username}' is already taken by another user.";
            } else {
                $sql = "UPDATE users SET username = ?, role = ?";
                $types = "ss";
                $params = [$edit_username, $edit_role];

                if (!empty($edit_password)) {
                    $hashed_password = password_hash($edit_password, PASSWORD_DEFAULT);
                    $sql .= ", password_hash = ?";
                    $types .= "s";
                    $params[] = $hashed_password;
                }
                $sql .= " WHERE id = ?";
                $types .= "i";
                $params[] = $edit_user_id;

                $stmt_update = $conn->prepare($sql);
                // Using ...$params for direct parameter binding (PHP 5.6+)
                $stmt_update->bind_param($types, ...$params);

                if ($stmt_update->execute()) {
                    $_SESSION['success_message'] = "User '{$edit_username}' updated successfully!";
                } else {
                    handleDbError($stmt_update, "updating user");
                }
                $stmt_update->close();
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Error updating user: " . $e->getMessage());
            $_SESSION['error_message'] = "Database error: Could not update user.";
        }
    }
    header("Location: manage_users.php"); // PRG pattern
    exit;
}

// Fetch all users after any actions
try {
    $stmt_fetch = $conn->prepare("SELECT id, username, role, created_at FROM users ORDER BY role DESC, username ASC");
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt_fetch->close();
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $error_message = "Could not retrieve user list. Please try again later.";
}

// ... (HTML remains largely the same, but the alert display depends on $success_message and $error_message)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($roleTitle); ?> - Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #343a40; }
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 1000; padding: 48px 0 0; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); background-color: #212529 !important; }
        .sidebar .nav-link { color: #e9ecef !important; transition: background-color 0.2s ease, color 0.2s ease; padding: 10px 15px; border-radius: 5px; display: flex; align-items: center; }
        .sidebar .nav-link svg { margin-right: 10px; width: 18px; height: 18px; stroke-width: 2; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); color: #fff !important; }
        .sidebar .nav-item.mt-4 .nav-link { color: #dc3545 !important; }
        .sidebar .nav-item.mt-4 .nav-link:hover { background-color: rgba(220, 53, 69, 0.2); color: #fff !important; }
        main { margin-left: 25%; background-color: #fff; padding: 30px !important; border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); min-height: 100vh; }
        @media (min-width: 768px) { main { margin-left: calc(25% / 12 * 10); padding-left: calc(2 * 100vw / 12 + 1.5rem) !important; } }
        @media (max-width: 767.98px) { .sidebar { position: relative; width: 100%; padding: 15px; } main { margin-left: 0; } }
        .card { border: none; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); margin-bottom: 25px; }
        .table thead th { background-color: #007bff; color: #fff; border-color: #0056b3; padding: 12px 15px; vertical-align: middle; }
        .table tbody tr:hover { background-color: rgba(0, 123, 255, 0.05); }
        .modal-body .form-label { font-weight: 600; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block bg-dark text-white sidebar py-3">
            <h4 class="text-white ms-3 py-2 text-center border-bottom border-secondary mb-4">
                <?php echo htmlspecialchars($roleTitle); ?> Panel
            </h4>
            <ul class="nav flex-column ms-3">
                <li class="nav-item mb-2">
                    <a class="nav-link" href="cashier_dashboard.php">
                        <span data-feather="shopping-cart"></span> New Sale
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="my_sales_history.php">
                        <span data-feather="dollar-sign"></span> My Sales History
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="inventory_management.php">
                        <span data-feather="package"></span> Inventory Management
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="sales_overview.php">
                        <span data-feather="bar-chart"></span> Sales Overview
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="product_sales_summary.php">
                        <span data-feather="file-text"></span> Product Sales Summary
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="daily_sales_summary.php">
                        <span data-feather="calendar"></span> Daily Sales Summary
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link active" aria-current="page" href="manage_users.php">
                        <span data-feather="users"></span> Manage Users
                    </a>
                </li>
                   <li class="nav-item mb-2">
                    <a class="nav-link" href="product_management.php">
                        <span data-feather="box"></span> Product Management
                    </a>
                </li>
                <li class="nav-item mt-5 pt-3 border-top border-secondary">
                    <a class="nav-link text-danger" href="logout.php">
                        <span data-feather="log-out"></span> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h4">Manage Users</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <span data-feather="user-plus"></span> Add New User
                    </button>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success_message): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($users)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        No users found.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><span class="badge <?php
                                                if ($user['role'] === 'admin') echo 'bg-danger';
                                                else if ($user['role'] === 'manager') echo 'bg-warning text-dark';
                                                else echo 'bg-info text-dark';
                                                ?>"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span></td>
                                        <td><?php echo date("F j, Y", strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-secondary edit-user-btn"
                                                    data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                    data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                    data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                                <span data-feather="edit"></span> Edit
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): // Prevent admin from deleting self ?>
                                                <a href="manage_users.php?action=delete_user&id=<?php echo htmlspecialchars($user['id']); ?>"
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Are you sure you want to delete user <?php echo htmlspecialchars($user['username']); ?>? This action cannot be undone.');">
                                                       <span data-feather="trash-2"></span> Delete
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-danger disabled" title="Cannot delete your own account">
                                                       <span data-feather="trash-2"></span> Delete
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="cashier">Cashier</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="edit_id" id="editUserId">
                    <div class="mb-3">
                        <label for="editUsername" class="form-label">Username</label>
                        <input type="text" class="form-control" id="editUsername" name="edit_username" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPassword" class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="editPassword" name="edit_password">
                    </div>
                    <div class="mb-3">
                        <label for="editRole" class="form-label">Role</label>
                        <select class="form-select" id="editRole" name="edit_role" required>
                            <option value="cashier">Cashier</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <div class="text-center">
        <a href="my_dashboard.php" class="btn btn-secondary mt-3">⬅️ Back to Dashboard</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    feather.replace(); // Initialize Feather Icons

    // JavaScript to populate the Edit User modal
    document.addEventListener('DOMContentLoaded', function() {
        var editUserModal = document.getElementById('editUserModal');
        editUserModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Button that triggered the modal
            var id = button.getAttribute('data-id');
            var username = button.getAttribute('data-username');
            var role = button.getAttribute('data-role');

            var modalIdInput = editUserModal.querySelector('#editUserId');
            var modalUsernameInput = editUserModal.querySelector('#editUsername');
            var modalRoleSelect = editUserModal.querySelector('#editRole');
            var modalPasswordInput = editUserModal.querySelector('#editPassword');

            modalIdInput.value = id;
            modalUsernameInput.value = username;
            modalRoleSelect.value = role;
            modalPasswordInput.value = ''; // Clear password field for security
        });
    });
</script>
</body>
</html>