<?php
// Start session
session_start();

// Include required files
require_once('../../../wp-load.php');
require_once('../db-config.php');
require_once('../admin-config.php');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Return JSON error
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect to dashboard
    header('Location: ../dashboard.php');
    exit;
}

// Get admin username and permissions
$admin_username = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : '';

// Check if user has permission to change vehicle
if (!user_has_permission($admin_username, 'dienstwagen')) {
    $_SESSION['admin_error_message'] = 'Sie haben keine Berechtigung, Dienstwagen zu ändern.';
    header('Location: ../dashboard.php');
    exit;
}

// Get POST data
$reservation_id = isset($_POST['reservation_id']) ? intval($_POST['reservation_id']) : 0;
$new_vehicle_id = isset($_POST['new_vehicle_id']) ? intval($_POST['new_vehicle_id']) : 0;

// Validate data
if (empty($reservation_id) || empty($new_vehicle_id)) {
    $_SESSION['admin_error_message'] = 'Alle Pflichtfelder müssen ausgefüllt werden.';
    header('Location: ../dashboard.php');
    exit;
}

// Connect to database
$db_conn = null;
$db_error = false;

try {
    $db_conn = new PDO("mysql:host=$db_hostname;dbname=$db_datenbankname;charset=utf8", $db_username, $db_passwort);
    $db_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['admin_error_message'] = 'Datenbankverbindung fehlgeschlagen.';
    header('Location: ../dashboard.php');
    exit;
}

// Update vehicle
try {
    $query = "UPDATE companycar_reservations SET dienstwagen = :new_vehicle_id WHERE id = :reservation_id";
    $stmt = $db_conn->prepare($query);
    $result = $stmt->execute([
        ':new_vehicle_id' => $new_vehicle_id,
        ':reservation_id' => $reservation_id
    ]);
    
    if ($result) {
        // Get the vehicle name
        $vehicle_query = "SELECT name FROM companycar_pool WHERE id = :id";
        $vehicle_stmt = $db_conn->prepare($vehicle_query);
        $vehicle_stmt->execute([':id' => $new_vehicle_id]);
        $vehicle = $vehicle_stmt->fetch(PDO::FETCH_ASSOC);
        
        $_SESSION['admin_success_message'] = 'Fahrzeug wurde erfolgreich auf "' . $vehicle['name'] . '" geändert.';
    } else {
        $_SESSION['admin_error_message'] = 'Das Fahrzeug konnte nicht geändert werden.';
    }
    
} catch (Exception $e) {
    $_SESSION['admin_error_message'] = 'Fehler: ' . $e->getMessage();
}

// Redirect back to dashboard
header('Location: ../dashboard.php?type=dienstwagen');
exit;