<?php
session_start();
require 'db.php'; // Ensure db.php is set up for the connection

// Check if the user is already logged in
if (isset($_SESSION['username'])) {
    // Redirect based on user role
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin.php");
    } elseif ($_SESSION['role'] == 'report_upload') {
        header("Location: report_entry.php");
    } elseif ($_SESSION['role'] == 'report_update') {
        header("Location: update_report.php"); // Corrected page name
    } elseif ($_SESSION['role'] == 'unfit_report') {
        header("Location: unfit_report.php"); // Redirecting to the unfit report page
    }
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Fetch user data from the database
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user exists and verify password
    if ($user === false) {
        $error = "Invalid username or password."; // User not found
    } elseif (password_verify($password, $user['password'])) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; // Set the user role in the session

        // Redirect based on user role
        if ($user['role'] == 'admin') {
            header("Location: admin.php");
        } elseif ($user['role'] == 'report_upload') {
            header("Location: report_entry.php");
        } elseif ($user['role'] == 'report_update') {
            header("Location: update_report.php"); // Redirecting to the correct page
        } elseif ($user['role'] == 'unfit_report') {
            header("Location: unfit_report.php"); // Redirecting to the correct page
        }
        exit;
    } else {
        $error = "Invalid username or password."; // Password incorrect
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="css/admincss.css">
</head>
<body>

    <div class="container">
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form action="login.php" method="post">
            <label for="username">Username:</label>
            <input type="text" name="username" required><br>

            <label for="password">Password:</label>
            <input type="password" name="password" required><br>

            <button type="submit">Login</button>
        </form>
    </div>

</body>
</html>
