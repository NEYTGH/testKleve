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

// Get POST data - common fields for all reservation types
$type = isset($_POST['type']) ? $_POST['type'] : '';
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$datum = isset($_POST['datum']) ? $_POST['datum'] : '';
$startZeit = isset($_POST['startZeit']) ? $_POST['startZeit'] : '';
$endZeit = isset($_POST['endZeit']) ? $_POST['endZeit'] : '';
$anlass = isset($_POST['anlass']) ? trim($_POST['anlass']) : '';

// Validate data
if (empty($type) || empty($id) || empty($datum) || empty($startZeit) || empty($endZeit) || empty($anlass)) {
    $_SESSION['admin_error_message'] = 'Alle Pflichtfelder müssen ausgefüllt werden.';
    header('Location: ../dashboard.php');
    exit;
}

// Check if user has permission to update this type of reservation
if (!user_has_permission($admin_username, $type)) {
    $_SESSION['admin_error_message'] = 'Sie haben keine Berechtigung, diese Art von Reservierung zu bearbeiten.';
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

// Update reservation based on type
try {
    $query = "";
    $params = [];
    
    switch ($type) {
        case 'room':
            // Get room-specific fields
            $anzahl_personen = isset($_POST['anzahl_personen']) ? intval($_POST['anzahl_personen']) : 1;
            $externe_teilnehmer = isset($_POST['externe_teilnehmer']) ? 1 : 0;
            $leitsystem_anzeige = isset($_POST['leitsystem_anzeige']) ? 1 : 0;
            $kaffee_personen = isset($_POST['kaffee_personen']) ? intval($_POST['kaffee_personen']) : 0;
            $tee_personen = isset($_POST['tee_personen']) ? intval($_POST['tee_personen']) : 0;
            $kaltgetraenke_personen = isset($_POST['kaltgetraenke_personen']) ? intval($_POST['kaltgetraenke_personen']) : 0;
            
            $query = "UPDATE room_reservations 
                      SET datum = :datum, 
                          startZeit = :startZeit, 
                          endZeit = :endZeit, 
                          anlass = :anlass,
                          anzahl_personen = :anzahl_personen,
                          externe_teilnehmer = :externe_teilnehmer,
                          leitsystem_anzeige = :leitsystem_anzeige,
                          kaffee_personen = :kaffee_personen,
                          tee_personen = :tee_personen,
                          kaltgetraenke_personen = :kaltgetraenke_personen
                      WHERE id = :id";
                      
            $params = [
                ':datum' => $datum,
                ':startZeit' => $startZeit,
                ':endZeit' => $endZeit,
                ':anlass' => $anlass,
                ':anzahl_personen' => $anzahl_personen,
                ':externe_teilnehmer' => $externe_teilnehmer,
                ':leitsystem_anzeige' => $leitsystem_anzeige,
                ':kaffee_personen' => $kaffee_personen,
                ':tee_personen' => $tee_personen,
                ':kaltgetraenke_personen' => $kaltgetraenke_personen,
                ':id' => $id
            ];
            break;
            
        case 'edv':
            $query = "UPDATE edv_reservations 
                      SET datum = :datum, startZeit = :startZeit, endZeit = :endZeit, zweck = :zweck 
                      WHERE id = :id";
            $params = [
                ':datum' => $datum,
                ':startZeit' => $startZeit,
                ':endZeit' => $endZeit,
                ':zweck' => $anlass,
                ':id' => $id
            ];
            break;
            
        case 'dienstwagen':
            $query = "UPDATE companycar_reservations 
                      SET datum = :datum, startZeit = :startZeit, endZeit = :endZeit, ziel = :ziel 
                      WHERE id = :id";
            $params = [
                ':datum' => $datum,
                ':startZeit' => $startZeit,
                ':endZeit' => $endZeit,
                ':ziel' => $anlass,
                ':id' => $id
            ];
            break;
            
        case 'dienstfahrrad':
            $query = "UPDATE companybicycle_reservations 
                      SET datum = :datum, startZeit = :startZeit, endZeit = :endZeit, zweck = :zweck 
                      WHERE id = :id";
            $params = [
                ':datum' => $datum,
                ':startZeit' => $startZeit,
                ':endZeit' => $endZeit,
                ':zweck' => $anlass,
                ':id' => $id
            ];
            break;
            
        case 'rollup':
            $query = "UPDATE rollup_reservations 
                      SET datum = :datum, startZeit = :startZeit, endZeit = :endZeit, zweck = :zweck 
                      WHERE id = :id";
            $params = [
                ':datum' => $datum,
                ':startZeit' => $startZeit,
                ':endZeit' => $endZeit,
                ':zweck' => $anlass,
                ':id' => $id
            ];
            break;
            
        default:
            throw new Exception('Ungültiger Reservierungstyp.');
    }
    
    // Execute query
    $stmt = $db_conn->prepare($query);
    $result = $stmt->execute($params);
    
    if ($result) {
        $_SESSION['admin_success_message'] = 'Reservierung wurde erfolgreich aktualisiert.';
    } else {
        $_SESSION['admin_error_message'] = 'Die Reservierung konnte nicht aktualisiert werden.';
    }
    
} catch (Exception $e) {
    $_SESSION['admin_error_message'] = 'Fehler: ' . $e->getMessage();
}

// Redirect back to dashboard
header('Location: ../dashboard.php?type=' . $type);
exit;