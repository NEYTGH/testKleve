<?php
// Start session
session_start();

// Include required files
require_once('../../../wp-load.php');
require_once('../db-config.php');
require_once('../auth_functions.php');

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

// Get POST data
$type = isset($_POST['type']) ? $_POST['type'] : '';
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

// Validate data
if (empty($type) || empty($id)) {
    $_SESSION['admin_error_message'] = 'Ungültige Anfrage.';
    header('Location: ../dashboard.php');
    exit;
}

// Check if user has permission to delete this type of reservation
if (!user_has_permission($admin_username, $type)) {
    $_SESSION['admin_error_message'] = 'Sie haben keine Berechtigung, diese Art von Reservierung zu stornieren.';
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

// Delete reservation based on type
try {
    $query = "";
    
    switch ($type) {
        case 'room':
            $query = "DELETE FROM room_reservations WHERE id = :id";
            break;
            
        case 'edv':
            $query = "DELETE FROM edv_reservations WHERE id = :id";
            break;
            
        case 'dienstwagen':
            $query = "DELETE FROM companycar_reservations WHERE id = :id";
            break;
            
        case 'dienstfahrrad':
            $query = "DELETE FROM companybicycle_reservations WHERE id = :id";
            break;
            
        case 'rollup':
            $query = "DELETE FROM rollup_reservations WHERE id = :id";
            break;
            
        default:
            throw new Exception('Ungültiger Reservierungstyp.');
    }
    
    // Execute query
    $stmt = $db_conn->prepare($query);
    $result = $stmt->execute([':id' => $id]);
    
    if ($result) {
        $_SESSION['admin_success_message'] = 'Reservierung wurde erfolgreich storniert.';
    } else {
        $_SESSION['admin_error_message'] = 'Die Reservierung konnte nicht storniert werden.';
    }
    
} catch (Exception $e) {
    $_SESSION['admin_error_message'] = 'Fehler: ' . $e->getMessage();
}

// Redirect back to dashboard
header('Location: ../dashboard.php?type=' . $type);
exit;
?>