<?php
// db.php
$host = 'localhost';  // Change if your DB is hosted elsewhere
$db = 'reporttracker';
$user = 'root';
$pass = '';

try {
    $db = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}
?>
