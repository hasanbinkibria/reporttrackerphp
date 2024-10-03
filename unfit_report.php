<?php
session_start();
require 'db.php'; // Ensure db.php is set up for the connection

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'unfit_report') {
    header("Location: login.php");
    exit;
}

// Handle form submission for marking a report as unfit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $passportNo = $_POST['passport_no'];
    $unfitCause = $_POST['unfit_cause'];

    // Search for the report in the main reports table by passport number
    $stmt = $db->prepare("SELECT id FROM reports WHERE passport_no = ?");
    $stmt->execute([$passportNo]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($report) {
        $reportId = $report['id'];

        // Check if this report is already marked as unfit
        $checkStmt = $db->prepare("SELECT * FROM unfit_reports WHERE report_id = ?");
        $checkStmt->execute([$reportId]);
        $existingUnfitReport = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUnfitReport) {
            $errorMessage = "This report is already marked as unfit.";
        } else {
            // Insert the unfit report into the unfit_reports table
            $insertStmt = $db->prepare("INSERT INTO unfit_reports (report_id, unfit_cause) VALUES (?, ?)");
            $insertStmt->execute([$reportId, $unfitCause]);
            $successMessage = "Report marked as unfit successfully.";
        }
    } else {
        $errorMessage = "No report found with the provided passport number.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unfit Report Update</title>
    <link rel="stylesheet" href="css/admincss.css">
</head>
<body>

    <div class="container">
        <h2>Update Unfit Report</h2>

        <?php if (isset($successMessage)): ?>
            <p class="success-message"><?php echo htmlspecialchars($successMessage); ?></p>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <p class="error-message"><?php echo htmlspecialchars($errorMessage); ?></p>
        <?php endif; ?>

        <form action="unfit_report.php" method="post">
            <label for="passport_no">Passport No:</label>
            <input type="text" name="passport_no" required><br>

            <label for="unfit_cause">Unfit Cause:</label>
            <textarea name="unfit_cause" required></textarea><br>

            <button type="submit">Mark as Unfit</button>
        </form>
 <a href="logout.php" class="logout-btn">Logout</a>
    </div>

</body>
</html>
