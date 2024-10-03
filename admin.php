<?php
// Start the session only if it hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db.php'; // Ensure db.php is set up for the connection

// Check if the user is logged in and is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Notification check: Create notifications for reports received two days ago
$current_date = date('Y-m-d');
$date_two_days_ago = date('Y-m-d', strtotime('-2 days', strtotime($current_date)));

// Check for reports received two days ago
$stmt = $db->prepare("SELECT * FROM reporttracker.reports WHERE received_from_director_sir = ?");
$stmt->execute([$date_two_days_ago]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($reports as $report) {
    // Check if notification already exists for the report
    $notification_check_stmt = $db->prepare("SELECT * FROM reporttracker.notifications WHERE report_id = ?");
    $notification_check_stmt->execute([$report['id']]);
    if ($notification_check_stmt->rowCount() == 0) { // No existing notification
        // Create a notification for each report found
        $message = "Report ID {$report['id']} was received on {$report['received_from_director_sir']}, two calendar days ago.";
        $notification_stmt = $db->prepare("INSERT INTO reporttracker.notifications (report_id, message, is_read) VALUES (?, ?, 0)");
        $notification_stmt->execute([$report['id'], $message]);
    }
}

// Handle form submissions for marking notifications as read
if (isset($_POST['mark_as_read'])) {
    $notification_id = $_POST['notification_id'];
    $stmt = $db->prepare("UPDATE reporttracker.notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$notification_id]);
    $success = "Notification marked as read successfully!";
}

// Fetch notifications
$notifications = $db->query("SELECT * FROM reporttracker.notifications")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions for creating or deleting users
if (isset($_POST['create_user'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $stmt = $db->prepare("INSERT INTO reporttracker.users (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$username, $password, $role]);
    $success = "User created successfully!";
}

if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    $stmt = $db->prepare("DELETE FROM reporttracker.users WHERE id = ?");
    $stmt->execute([$user_id]);
    $success = "User deleted successfully!";
}

if (isset($_POST['delete_report'])) {
    if (!empty($_POST['report_id'])) {
        $stmt = $db->prepare("DELETE FROM reporttracker.reports WHERE id = ?");
        $stmt->execute([$_POST['report_id']]);
        $success = "Report deleted successfully!";
    }
}

// Fetch all users and reports
$users = $db->query("SELECT * FROM reporttracker.users")->fetchAll(PDO::FETCH_ASSOC);
$reports = $db->query("SELECT id, passport_no, received_from_director_sir, status FROM reporttracker.reports")->fetchAll(PDO::FETCH_ASSOC);

// Get the current section (default to 'view_users')
$section = isset($_GET['section']) ? $_GET['section'] : 'view_users';
$highlighted_report = isset($_GET['report_id']) ? $_GET['report_id'] : null;

// Move highlighted report to the top and set highlighted class
if ($highlighted_report) {
    $highlighted_report_data = null;
    foreach ($reports as $key => $report) {
        if ($report['id'] == $highlighted_report) {
            $highlighted_report_data = $report;
            unset($reports[$key]); // Remove from current position
            break;
        }
    }
    if ($highlighted_report_data) {
        array_unshift($reports, $highlighted_report_data); // Add to the top
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="css/admincss.css">
    <style>
        .highlighted-row {
            background-color: yellow; /* Highlight color for the clicked report */
        }
        .action-btn {
            padding: 5px 10px;
            margin: 2px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .highlight-report-btn {
            width: 30px;
            background-color: #ffff; /* Green */
            color: white;

        }
        .highlight-report-btn img{
            width: 20px;
        }
        .highlight-report-btn:hover {
            background-color: #45a049;
        }
        .mark-read-btn {
            width: 30px; /* Half size of the button */
            background-color: #fffd; /* Blue */
            color: white;
        }
        .mark-read-btn img{
            width: 20px;
        }
        
    </style>
</head>
<body>

<header>
    <div class="header-content">
        <span class="welcome-text">Admin Panel - Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<div class="container">
    <!-- Navigation Menu -->
    <nav>
        <ul>
            <li><a href="admin.php?section=create_user" <?php echo $section == 'create_user' ? 'class="active"' : ''; ?>>Create User</a></li>
            <li><a href="admin.php?section=delete_user" <?php echo $section == 'delete_user' ? 'class="active"' : ''; ?>>Delete User</a></li>
            <li><a href="admin.php?section=view_users" <?php echo $section == 'view_users' ? 'class="active"' : ''; ?>>View Users</a></li>
            <li><a href="admin.php?section=view_reports" <?php echo $section == 'view_reports' ? 'class="active"' : ''; ?>>View Reports</a></li>
            <li><a href="admin.php?section=delete_report" <?php echo $section == 'delete_report' ? 'class="active"' : ''; ?>>Delete Report</a></li>
            <li><a href="admin.php?section=view_notifications" <?php echo $section == 'view_notifications' ? 'class="active"' : ''; ?>>View Notifications</a></li>
        </ul>
    </nav>

    <!-- Display the selected section -->
    <?php if (isset($success)): ?>
        <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <!-- Create User Section -->
    <?php if ($section == 'create_user'): ?>
        <h2>Create User</h2>
        <form action="admin.php?section=create_user" method="post">
            <label for="username">Username:</label>
            <input type="text" name="username" required>
            <label for="password">Password:</label>
            <input type="password" name="password" required>
            <label for="role">Role:</label>
            <select name="role" required>
                <option value="report_upload_user">Report Upload User</option>
                <option value="report_update_user">Report Update User</option>
                <option value="admin_user">Admin User</option>
                <option value="unfit_report">Unfit Report User</option>                
            </select>
            <button type="submit" name="create_user">Create User</button>
        </form>
    <?php endif; ?>

    <!-- Delete User Section -->
    <?php if ($section == 'delete_user'): ?>
        <h2>Delete User</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Action</th>
            </tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td>
                        <form action="admin.php?section=delete_user" method="post" style="display:inline;">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" name="delete_user">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- View Users Section -->
    <?php if ($section == 'view_users'): ?>
        <h2>View Users</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
            </tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- View Reports Section -->
    <?php if ($section == 'view_reports'): ?>
        <h2>View Reports</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Passport No.</th>
                <th>Received From Director</th>
                <th>Status</th>
            </tr>
            <?php foreach ($reports as $report): ?>
                <tr class="<?php echo $highlighted_report == $report['id'] ? 'highlighted-row' : ''; ?>">
                    <td>
                        <a href="admin.php?section=view_reports&report_id=<?php echo $report['id']; ?>">
                            <?php echo htmlspecialchars($report['id']); ?>
                        </a>
                    </td>
                    <td><?php echo htmlspecialchars($report['passport_no']); ?></td>
                    <td><?php echo htmlspecialchars($report['received_from_director_sir']); ?></td>
                    <td><?php echo htmlspecialchars($report['status']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- Delete Report Section -->
    <?php if ($section == 'delete_report'): ?>
        <h2>Delete Report</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Passport No.</th>
                <th>Received From Director</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td>
                        <a href="admin.php?section=view_reports&report_id=<?php echo $report['id']; ?>">
                            <?php echo htmlspecialchars($report['id']); ?>
                        </a>
                    </td>
                    <td><?php echo htmlspecialchars($report['passport_no']); ?></td>
                    <td><?php echo htmlspecialchars($report['received_from_director_sir']); ?></td>
                    <td><?php echo htmlspecialchars($report['status']); ?></td>
                    <td>
                        <form action="admin.php?section=delete_report" method="post" style="display:inline;">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <button type="submit" name="delete_report">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- View Notifications Section -->
    <?php if ($section == 'view_notifications'): ?>
        <h2>View Notifications</h2>
        <table>
            <tr>
                <th>Notification ID</th>
                <th>Message</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php foreach ($notifications as $notification): ?>
                <tr>
                    <td><?php echo htmlspecialchars($notification['id']); ?></td>
                    <td><?php echo htmlspecialchars($notification['message']); ?></td>
                    <td><?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?></td>
                    <td>
                        <form action="admin.php?section=view_notifications" method="post" style="display:inline;">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                            <button type="submit" name="mark_as_read" class="mark-read-btn"><img src="images/check-mark-circle-icon-0dded8.webp" alt=""></button>
                        </form>
                        <a href="admin.php?section=view_reports&report_id=<?php echo $notification['report_id']; ?>" class="highlight-report-btn">
                            <img src="images/highlight-btn.png" alt="">
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
