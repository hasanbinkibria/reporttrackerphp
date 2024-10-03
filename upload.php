<?php
session_start();
require 'db.php';
require 'vendor/autoload.php'; // For PhpSpreadsheet library

use PhpOffice\PhpSpreadsheet\IOFactory;

if (isset($_POST['upload'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $sheetData = $spreadsheet->getActiveSheet()->toArray();

    foreach ($sheetData as $row) {
        $reg_no = $row[0];
        $passport_no = $row[1];
        $timestamp = date('Y-m-d H:i:s');
        $received_from_director_sir = date('Y-m-d');

        // Insert into `reporttracker` database
        $db->query("INSERT INTO reporttracker.reports (reg_no, passport_no, timestamp, received_from_director_sir) VALUES ('$reg_no', '$passport_no', '$timestamp', '$received_from_director_sir')");
    }

    // Notify report update panel after 2 days
    $notify_date = date('Y-m-d', strtotime('+2 days'));
    $db->query("INSERT INTO reporttracker.notifications (user_id, message, notify_date) VALUES (NULL, 'Report update required', '$notify_date')");
}
?>
