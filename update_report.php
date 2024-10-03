<?php
session_start();
require 'db.php'; // Ensure db.php is set up for the connection

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'report_update') {
    header("Location: login.php");
    exit;
}

// Initialize variables for filtering
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Handle report updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Bulk update operations
    if (isset($_POST['mark_done'])) {
        if (!empty($_POST['report_ids'])) {
            $reportIds = $_POST['report_ids'];
            $eligibleReports = [];
            $ineligibleReports = [];

            // Check eligibility based on the timestamp
            foreach ($reportIds as $id) {
                $stmt = $db->prepare("SELECT * FROM reports WHERE id = ?");
                $stmt->execute([$id]);
                $report = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if at least 2 calendar days have passed
                $receivedDate = new DateTime($report['received_from_director_sir']);
                $today = new DateTime();
                $dayDifference = $today->diff($receivedDate)->days;

                if ($report['status'] === 'received' && $dayDifference >= 2) {
                    $eligibleReports[] = $id; // Add to eligible reports
                } else {
                    $ineligibleReports[] = $id; // Add to ineligible reports
                }
            }

            // Update eligible reports
            if (!empty($eligibleReports)) {
                $updateStmt = $db->prepare("UPDATE reports SET status = 'online done' WHERE id IN (" . implode(',', array_fill(0, count($eligibleReports), '?')) . ")");
                $updateStmt->execute($eligibleReports);
                $successMessage = "Selected reports marked as 'online done'.";
            }

            // Handle ineligible reports
            if (!empty($ineligibleReports)) {
                $errorMessage = "The following reports cannot be marked as 'online done' as they haven't been received for at least 2 calendar days: " . implode(', ', $ineligibleReports);
            }
        } else {
            $errorMessage = "No reports selected.";
        }
    }

    if (isset($_POST['mark_received'])) {
        if (!empty($_POST['report_ids'])) {
            $reportIds = $_POST['report_ids'];
            $updateStmt = $db->prepare("UPDATE reports SET status = 'received' WHERE id IN (" . implode(',', array_fill(0, count($reportIds), '?')) . ")");
            $updateStmt->execute($reportIds);
            $successMessage = "Selected reports marked as 'received'.";
        } else {
            $errorMessage = "No reports selected.";
        }
    }

    // Individual report actions
    if (isset($_POST['mark_received_individual'])) {
        $reportId = $_POST['report_id'];
        $updateStmt = $db->prepare("UPDATE reports SET status = 'received' WHERE id = ?");
        $updateStmt->execute([$reportId]);
        $successMessage = "Report ID $reportId marked as 'received'.";
    }

    if (isset($_POST['mark_done_individual'])) {
        $reportId = $_POST['report_id'];
        $stmt = $db->prepare("SELECT * FROM reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if at least 2 calendar days have passed
        $receivedDate = new DateTime($report['received_from_director_sir']);
        $today = new DateTime();
        $dayDifference = $today->diff($receivedDate)->days;

        if ($report['status'] === 'received' && $dayDifference >= 2) {
            $updateStmt = $db->prepare("UPDATE reports SET status = 'online done' WHERE id = ?");
            $updateStmt->execute([$reportId]);
            $successMessage = "Report ID $reportId marked as 'online done'.";
        } else {
            $errorMessage = "Report ID $reportId cannot be marked as 'online done' as it hasn't been received for at least 2 calendar days.";
        }
    }
}

// Fetch reports based on filters without pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Number of reports per page
$offset = ($page - 1) * $limit;

$query = "SELECT * FROM reports WHERE 1=1"; // Basic query
$params = [];

// Filter by status
if ($statusFilter) {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
}

// Filter by date range
if ($startDate) {
    $query .= " AND DATE(timestamp) >= ?";
    $params[] = $startDate;
}
if ($endDate) {
    $query .= " AND DATE(timestamp) <= ?";
    $params[] = $endDate;
}

// Add pagination
$query .= " LIMIT $limit OFFSET $offset"; // Directly append limit and offset

// Execute final SQL statement
$stmt = $db->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total number of reports for pagination
$totalQuery = "SELECT COUNT(*) FROM reports WHERE 1=1";
$totalParams = [];

// Append the same filters to the total count query
if ($statusFilter) {
    $totalQuery .= " AND status = ?";
    $totalParams[] = $statusFilter;
}
if ($startDate) {
    $totalQuery .= " AND DATE(timestamp) >= ?";
    $totalParams[] = $startDate;
}
if ($endDate) {
    $totalQuery .= " AND DATE(timestamp) <= ?";
    $totalParams[] = $endDate;
}

// Execute total count query
$totalStmt = $db->prepare($totalQuery);
$totalStmt->execute($totalParams);
$totalReports = $totalStmt->fetchColumn();
$totalPages = ceil($totalReports / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Update Panel</title>
    <style>
        /* Container styling for full-width design */
        .container {
            width: 85%; /* Full width */
            margin: 0 auto; /* Center container horizontally */
            padding: 30px;
            background-color: #fdfdfd;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Heading Styling */
        h2 {
            text-align: center;
            font-size: 28px;
            color: #333;
            margin-bottom: 20px;
            font-weight: bold;
        }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }

        th, td {
            padding: 15px 10px; /* Increased padding for better readability */
            text-align: left;
            border-bottom: 2px solid #eaeaea; /* Sharper lines */
        }

        th {
            background-color: #007bff; /* Header background color */
            color: #fff; /* White text for contrast */
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
        }

        td {
            font-size: 15px;
            color: #555;
            vertical-align: middle; /* Center content vertically */
        }

        /* Messages Styling */
        .success-message, .error-message {
            text-align: center;
            margin: 20px auto;
            padding: 10px;
            width: 100%;
            max-width: 800px;
            border-radius: 5px;
            font-size: 16px;
        }

        .success-message {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }

        /* Action Buttons Styling */
        .action-buttons {
            display: flex;
            justify-content: flex-start;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-buttons button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .action-buttons button:hover {
            opacity: 0.9;
        }

        /* Mark as Received Button */
        .action-buttons button {
            background-color: #28a745; /* Green button */
            color: white;
            width: 120px;
        }

        /* Mark as Done Button */
        .action-buttons button:nth-child(2) {
            background-color: #007bff; /* Blue button */
        }

        /* Pagination Styling */
        .pagination {
            margin: 20px 0;
            text-align: center;
        }

        .pagination a {
            padding: 10px 15px;
            margin: 0 5px;
            border: 1px solid #007bff;
            border-radius: 5px;
            color: #007bff;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .pagination a:hover {
            background-color: #007bff;
            color: white;
        }

        /* Filter form styling */
        .filter-form {
            margin: 20px 0;
            display: flex;
            justify-content: space-evenly;
            
        }

        .filter-form input,
        .filter-form select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        #filter-btn button{
            background-color: #007bff; /* Green button */
            color: white;
            width: 120px;
            padding: 10px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Report Update Panel</h2>

    <?php if (isset($successMessage)): ?>
        <div class="success-message"><?php echo $successMessage; ?></div>
    <?php endif; ?>

    <?php if (isset($errorMessage)): ?>
        <div class="error-message"><?php echo $errorMessage; ?></div>
    <?php endif; ?>

    <!-- Filter Form -->
 <a href="logout.php" class="logout-btn">Logout</a>
    <form class="filter-form" method="get">
        <select name="status">
            <option value="">Select Status</option>
            <option value="received" <?php if ($statusFilter === 'received') echo 'selected'; ?>>Received</option>
            <option value="online done" <?php if ($statusFilter === 'online done') echo 'selected'; ?>>Online Done</option>
        </select>
        <input type="date" name="start_date" value="<?php echo $startDate; ?>" placeholder="Start Date">
        <input type="date" name="end_date" value="<?php echo $endDate; ?>" placeholder="End Date">
       <span id="filter-btn"> <button type="submit">Filter</button></span>
    </form>

    <!-- Report Table -->
    <form method="post">
        <table>
            <thead>
                <tr>
                    <th>Select</th>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Received Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reports): ?>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><input type="checkbox" name="report_ids[]" value="<?php echo $report['id']; ?>"></td>
                            <td><?php echo $report['id']; ?></td>
                            <td><?php echo $report['status']; ?></td>
                            <td><?php echo $report['received_from_director_sir']; ?></td>
                            <td>
                            <div class="action-buttons">
                                <button  type="submit" name="mark_received_individual">Mark as Received</button>
                                <button type="submit" name="mark_done_individual">Mark as Done</button>
                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                    </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;">No reports found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="action-buttons">
            <button type="submit" name="mark_received">Mark Selected as Received</button>
            <button type="submit" name="mark_done">Mark Selected as Done</button>
        </div>
    </form>

    <!-- Pagination -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">Next</a>
        <?php endif; ?>
        <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
    </div>
</div>
</body>
</html>
