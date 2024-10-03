<?php
session_start();
require 'db.php'; // Ensure db.php is set up for the connection

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'report_upload') {
    header("Location: login.php");
    exit;
}

// Handle CSV upload for report entry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];

        if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
            fgetcsv($handle); // Skip header row
            $checkRegStmt = $db->prepare("SELECT reg_no, passport_no, timestamp FROM reports WHERE reg_no = ? AND passport_no = ?");
            $checkPassStmt = $db->prepare("SELECT reg_no, timestamp FROM reports WHERE passport_no = ?");
            $checkRegNoStmt = $db->prepare("SELECT passport_no, timestamp FROM reports WHERE reg_no = ?"); // New query to check for duplicate reg_no
            $insertStmt = $db->prepare("INSERT INTO reports (reg_no, passport_no, timestamp, received_from_director_sir, status) VALUES (?, ?, NOW(), ?, 'pending')");

            $duplicateRecords = [];
            $duplicatePassports = [];
            $duplicateRegNos = [];

            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $regNo = $data[0];
                $passportNo = $data[1];
                $receivedDate = date('Y-m-d H:i:s');

                // Check for duplicate passport entry
                $checkPassStmt->execute([$passportNo]);
                $duplicatePassportEntry = $checkPassStmt->fetch(PDO::FETCH_ASSOC);

                if ($duplicatePassportEntry) {
                    $duplicatePassports[] = [
                        "passport_no" => $passportNo,
                        "previous_reg_no" => $duplicatePassportEntry['reg_no'],
                        "new_reg_no" => $regNo,
                        "timestamp" => $duplicatePassportEntry['timestamp']
                    ];
                } else {
                    // Check for duplicate registration and passport entry
                    $checkRegStmt->execute([$regNo, $passportNo]);
                    $duplicateEntry = $checkRegStmt->fetch(PDO::FETCH_ASSOC);

                    if ($duplicateEntry) {
                        $duplicateRecords[] = [
                            "reg_no" => $regNo,
                            "passport_no" => $passportNo,
                            "timestamp" => $duplicateEntry['timestamp']
                        ];
                    } else {
                        // Check for duplicate registration number
                        $checkRegNoStmt->execute([$regNo]);
                        $duplicateRegNoEntry = $checkRegNoStmt->fetch(PDO::FETCH_ASSOC);

                        if ($duplicateRegNoEntry) {
                            $duplicateRegNos[] = [
                                "passport_no" => $duplicateRegNoEntry['passport_no'],
                                "duplicate_reg_no" => $regNo,
                                "timestamp" => $duplicateRegNoEntry['timestamp']
                            ];
                        } else {
                            // Only insert if there are no duplicates
                            $insertStmt->execute([$regNo, $passportNo, $receivedDate]);
                        }
                    }
                }
            }

            fclose($handle);
            $successMessage = "Reports uploaded successfully.";
            if (!empty($duplicateRecords) || !empty($duplicatePassports) || !empty($duplicateRegNos)) {
                $errorMessage = "Duplicate entries found.";
            }
        } else {
            $errorMessage = "Failed to open the CSV file.";
        }
    } else {
        $errorMessage = "No file uploaded or there was an upload error.";
    }
}

// Handle report checking
if (isset($_POST['check_reports']) && isset($_FILES['check_file'])) {
    if ($_FILES['check_file']['error'] == UPLOAD_ERR_OK) {
        $checkFileTmpPath = $_FILES['check_file']['tmp_name'];
        $unfitReports = [];
        $missingReports = [];

        if (($handle = fopen($checkFileTmpPath, 'r')) !== FALSE) {
            fgetcsv($handle); // Skip header row
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $regNo = $data[0];
                $passportNo = $data[1];

                // Check for missing reports
                $stmt = $db->prepare("SELECT * FROM reports WHERE reg_no = ? AND passport_no = ?");
                $stmt->execute([$regNo, $passportNo]);
                $report = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$report) {
                    $missingReports[] = [
                        'reg_no' => $regNo,
                        'passport_no' => $passportNo
                    ];
                } else {
                    // Check for unfit reports associated with the report ID
                    $unfitStmt = $db->prepare("SELECT unfit_cause FROM unfit_reports WHERE report_id = ?");
                    $unfitStmt->execute([$report['id']]);
                    $unfitReport = $unfitStmt->fetch(PDO::FETCH_ASSOC);

                    if ($unfitReport) {
                        $unfitReports[] = [
                            'reg_no' => $report['reg_no'],
                            'passport_no' => $report['passport_no'],
                            'unfit_cause' => $unfitReport['unfit_cause'],
                            'updated_at' => $report['timestamp'] // Adjust if needed
                        ];
                    } else {
                        $noProblemMessage[] = [
                            'reg_no' => $regNo,
                            'passport_no' => $passportNo
                        ];
                    }
                }
            }
            fclose($handle);
            $successMessage = "Reports checked successfully.";
        } else {
            $errorMessage = "Failed to open the check file.";
        }
    } else {
        $errorMessage = "No check file uploaded or there was an upload error.";
    }
}

// Logout functionality
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Entry</title>
    <link rel="stylesheet" href="css/admincss.css">
    <style>
        .container {
            width: 60%;
            margin: auto;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
        }
        .logout-button {
            text-align: right;
        }
        .logout-button button {
            background: none;
            border: none;
            color: red;
            cursor: pointer;
        }
        form {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        input[type="file"] {
            margin-bottom: 20px;
        }
        button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }
        button:hover {
            background-color: #45a049;
        }
        .success-message {
            color: green;
            text-align: center;
            margin-top: 10px;
        }
        .error-message {
            color: red;
            text-align: center;
            margin-top: 10px;
        }
        .duplicate-entries, .missing-reports, .unfit-reports, .no-problem {
            margin-top: 20px;
            border: 2px solid #ffcc00;
            background-color: #fff8e5;
            color: #b76a00;
            border-radius: 5px;
            padding: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>Upload Reports</h2>

        <!-- Logout Form -->
        <div class="logout-button">
            <form action="report_entry.php" method="post" style="display: inline;">
                <button type="submit" name="logout">Logout</button>
            </form>
        </div>

        <?php if (isset($successMessage)): ?>
            <p class="success-message"><?php echo htmlspecialchars($successMessage); ?></p>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <p class="error-message"><?php echo htmlspecialchars($errorMessage); ?></p>
        <?php endif; ?>

        <!-- Report Upload Form -->
        <form action="report_entry.php" method="post" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit">Upload Reports</button>
        </form>

        <!-- Check Reports Form -->
        <form action="report_entry.php" method="post" enctype="multipart/form-data">
            <input type="file" name="check_file" accept=".csv" required>
            <button type="submit" name="check_reports">Check Reports</button>
        </form>

        <!-- Display Duplicate Entries -->
        <?php if (!empty($duplicateRecords)): ?>
            <div class="duplicate-entries">
                <h4>Duplicate Entries Found:</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Registration No</th>
                            <th>Passport No</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($duplicateRecords as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['reg_no']); ?></td>
                                <td><?php echo htmlspecialchars($record['passport_no']); ?></td>
                                <td><?php echo htmlspecialchars($record['timestamp']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Display Missing Reports -->
        <?php if (!empty($missingReports)): ?>
            <div class="missing-reports">
                <h4>Missing Reports:</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Registration No</th>
                            <th>Passport No</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($missingReports as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['reg_no']); ?></td>
                                <td><?php echo htmlspecialchars($report['passport_no']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Display Unfit Reports -->
        <?php if (!empty($unfitReports)): ?>
            <div class="unfit-reports">
                <h4>Unfit Reports:</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Registration No</th>
                            <th>Passport No</th>
                            <th>Unfit Cause</th>
                            <th>Updated At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unfitReports as $unfit): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($unfit['reg_no']); ?></td>
                                <td><?php echo htmlspecialchars($unfit['passport_no']); ?></td>
                                <td><?php echo htmlspecialchars($unfit['unfit_cause']); ?></td>
                                <td><?php echo htmlspecialchars($unfit['updated_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!empty($noProblemMessage)): ?>
            <div class="no-problem">
                <h4>No Problem with the Following Reports:</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Registration No</th>
                            <th>Passport No</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($noProblemMessage as $message): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($message['reg_no']); ?></td>
                                <td><?php echo htmlspecialchars($message['passport_no']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Display Duplicate Passport Entries -->
        <?php if (!empty($duplicatePassports)): ?>
            <div class="duplicate-entries">
                <h4>Duplicate Passport Entries Found:</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Passport No</th>
                            <th>Previous Registration No</th>
                            <th>New Registration No</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($duplicatePassports as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['passport_no']); ?></td>
                                <td><?php echo htmlspecialchars($record['previous_reg_no']); ?></td>
                                <td><?php echo htmlspecialchars($record['new_reg_no']); ?></td>
                                <td><?php echo htmlspecialchars($record['timestamp']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Display Duplicate Registration Entries -->
        <?php if (!empty($duplicateRegNos)): ?>
            <div class="duplicate-entries">
                <h4>Duplicate Registration Entries Found:</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Previous Passport No</th>
                            <th>Duplicate Registration No</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($duplicateRegNos as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['passport_no']); ?></td>
                                <td><?php echo htmlspecialchars($record['duplicate_reg_no']); ?></td>
                                <td><?php echo htmlspecialchars($record['timestamp']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
