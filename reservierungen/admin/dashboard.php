<?php
// Start session
session_start();

// Include required files
require_once('../../wp-load.php');
require_once('db-config.php');
require_once('auth_functions.php');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Redirect to login page
    header('Location: index.php');
    exit;
}

// Connect to database
$db_conn = null;
$db_error = false;

try {
    $db_conn = new PDO("mysql:host=$db_hostname;dbname=$db_datenbankname;charset=utf8", $db_username, $db_passwort);
    $db_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $db_error = true;
    error_log("Es ist ein Fehler bei der Verbindung mit der Datenbank aufgetreten: " . $e->getMessage());
}

// Get current admin user information
$admin_username = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : '';
$admin_display_name = isset($_SESSION['admin_display_name']) ? $_SESSION['admin_display_name'] : 'Administrator';
$admin_permissions = isset($_SESSION['admin_permissions']) ? $_SESSION['admin_permissions'] : [];

// Fetch reservations based on type
$reservations = [];
$reservation_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Filter types based on user permissions
$allowed_types = $admin_permissions;
$reservation_types = ['room', 'edv', 'dienstwagen', 'dienstfahrrad', 'rollup'];

// If user has any permissions, include 'all' as an option
if (!empty($allowed_types)) {
    array_unshift($allowed_types, 'all');
}

// If requested type is not allowed, set to first allowed type
if (!in_array($reservation_type, $allowed_types)) {
    $reservation_type = $allowed_types[0];
}

// Success or error messages
$success_message = isset($_SESSION['admin_success_message']) ? $_SESSION['admin_success_message'] : null;
$error_message = isset($_SESSION['admin_error_message']) ? $_SESSION['admin_error_message'] : null;

// Clear session messages
unset($_SESSION['admin_success_message'], $_SESSION['admin_error_message']);

// Fetch reservations from database
if (!$db_error && $db_conn) {
    try {
        $query = "";
        
        // Determine which query to use based on requested type and user permissions
        if ($reservation_type === 'all') {
            // When showing 'all', only include types the user has permission for
            $union_queries = [];
            
            if (in_array('room', $admin_permissions)) {
                $union_queries[] = "(SELECT r.id, r.datum, r.startZeit, r.endZeit, r.anlass,
                    r.externe_teilnehmer, r.anzahl_personen, r.leitsystem_anzeige,
                    r.kaffee_personen, r.tee_personen, r.kaltgetraenke_personen,
                    NULL as ressourcentyp_name, NULL as ressource_name,
                    NULL as dienstwagen_name, NULL as dienstwagen_id, NULL as dienstwagen_reichweite,
                    NULL as dienstfahrrad_name, NULL as dienstfahrrad_id, NULL as dienstfahrrad_reichweite,
                    NULL as rollup_name, NULL as rollup_id,
                    rp.name as raum_name,
                    r.bemerkung, m.nachname, m.vorname, m.id as benutzer_id,
                    'room' as type
                    FROM room_reservations r
                    JOIN ma_info m ON r.benutzer_id = m.id
                    JOIN room_pool rp ON r.raum_id = rp.id
                    WHERE r.datum >= CURDATE())";
            }
            
            if (in_array('edv', $admin_permissions)) {
                $union_queries[] = "(SELECT r.id, r.datum, r.startZeit, r.endZeit, r.zweck as anlass,
                    NULL as externe_teilnehmer, NULL as anzahl_personen, NULL as leitsystem_anzeige,
                    NULL as kaffee_personen, NULL as tee_personen, NULL as kaltgetraenke_personen,
                    rt.name as ressourcentyp_name, res.name as ressource_name,
                    NULL as dienstwagen_name, NULL as dienstwagen_id, NULL as dienstwagen_reichweite,
                    NULL as dienstfahrrad_name, NULL as dienstfahrrad_id, NULL as dienstfahrrad_reichweite,
                    NULL as rollup_name, NULL as rollup_id,
                    NULL as raum_name,
                    r.bemerkung, m.nachname, m.vorname, m.id as benutzer_id,
                    'edv' as type
                    FROM edv_reservations r
                    JOIN ma_info m ON r.benutzer_id = m.id
                    JOIN edv_ressourcen res ON r.ressource = res.id
                    JOIN edv_ressourcen_typ rt ON res.typ_id = rt.id
                    WHERE r.datum >= CURDATE())";
            }
            
            if (in_array('dienstwagen', $admin_permissions)) {
                $union_queries[] = "(SELECT r.id, r.datum, r.startZeit, r.endZeit, r.ziel as anlass,
                    NULL as externe_teilnehmer, NULL as anzahl_personen, NULL as leitsystem_anzeige,
                    NULL as kaffee_personen, NULL as tee_personen, NULL as kaltgetraenke_personen,
                    NULL as ressourcentyp_name, NULL as ressource_name,
                    c.name as dienstwagen_name, c.id as dienstwagen_id, c.reichweite as dienstwagen_reichweite,
                    NULL as dienstfahrrad_name, NULL as dienstfahrrad_id, NULL as dienstfahrrad_reichweite,
                    NULL as rollup_name, NULL as rollup_id,
                    NULL as raum_name,
                    r.bemerkung, m.nachname, m.vorname, m.id as benutzer_id,
                    'dienstwagen' as type
                    FROM companycar_reservations r
                    JOIN ma_info m ON r.benutzer_id = m.id
                    JOIN companycar_pool c ON r.dienstwagen = c.id
                    WHERE DATE(r.datum) >= CURDATE())";
            }
            
            if (in_array('dienstfahrrad', $admin_permissions)) {
                $union_queries[] = "(SELECT r.id, r.datum, r.startZeit, r.endZeit, r.zweck as anlass,
                    NULL as externe_teilnehmer, NULL as anzahl_personen, NULL as leitsystem_anzeige,
                    NULL as kaffee_personen, NULL as tee_personen, NULL as kaltgetraenke_personen,
                    NULL as ressourcentyp_name, NULL as ressource_name,
                    NULL as dienstwagen_name, NULL as dienstwagen_id, NULL as dienstwagen_reichweite,
                    c.name as dienstfahrrad_name, c.id as dienstfahrrad_id, c.reichweite as dienstfahrrad_reichweite,
                    NULL as rollup_name, NULL as rollup_id,
                    NULL as raum_name,
                    r.bemerkung, m.nachname, m.vorname, m.id as benutzer_id,
                    'dienstfahrrad' as type
                    FROM companybicycle_reservations r
                    JOIN ma_info m ON r.benutzer_id = m.id
                    JOIN companybicycle_pool c ON r.dienstfahrrad = c.id
                    WHERE DATE(r.datum) >= CURDATE())";
            }
            
            if (in_array('rollup', $admin_permissions)) {
                $union_queries[] = "(SELECT r.id, r.datum, r.startZeit, r.endZeit, r.zweck as anlass,
                    NULL as externe_teilnehmer, NULL as anzahl_personen, NULL as leitsystem_anzeige,
                    NULL as kaffee_personen, NULL as tee_personen, NULL as kaltgetraenke_personen,
                    NULL as ressourcentyp_name, NULL as ressource_name,
                    NULL as dienstwagen_name, NULL as dienstwagen_id, NULL as dienstwagen_reichweite,
                    NULL as dienstfahrrad_name, NULL as dienstfahrrad_id, NULL as dienstfahrrad_reichweite,
                    c.name as rollup_name, c.id as rollup_id,
                    NULL as raum_name,
                    r.bemerkung, m.nachname, m.vorname, m.id as benutzer_id,
                    'rollup' as type
                    FROM rollup_reservations r
                    JOIN ma_info m ON r.benutzer_id = m.id
                    JOIN rollup_pool c ON r.rollup_id = c.id
                    WHERE DATE(r.datum) >= CURDATE())";
            }
            
            // Combine all permitted queries with UNION ALL
            if (!empty($union_queries)) {
                $query = implode(" UNION ALL ", $union_queries) . " ORDER BY datum, startZeit";
            }
        } else {
            // Handle individual type queries if user has permission
            switch ($reservation_type) {
                case 'room':
                    if (in_array('room', $admin_permissions)) {
                        $query = "SELECT r.id, r.datum, r.startZeit, r.endZeit, r.anlass, 
                            r.externe_teilnehmer, r.anzahl_personen, r.leitsystem_anzeige,
                            r.kaffee_personen, r.tee_personen, r.kaltgetraenke_personen,
                            r.bemerkung, m.nachname, m.vorname, m.id as benutzer_id,
                            rp.name as raum_name,
                            'room' as type
                            FROM room_reservations r
                            JOIN ma_info m ON r.benutzer_id = m.id
                            JOIN room_pool rp ON r.raum_id = rp.id
                            WHERE r.datum >= CURDATE()
                            ORDER BY r.datum, r.startZeit";
                    }
                    break;

                case 'edv':
                    if (in_array('edv', $admin_permissions)) {
                        $query = "SELECT r.id, r.datum, r.startZeit, r.endZeit, r.zweck as anlass, 
                                r.bemerkung, m.nachname, m.vorname, m.id as benutzer_id,
                                rt.name as ressourcentyp_name, res.name as ressource_name,
                                'edv' as type
                                FROM edv_reservations r
                                JOIN ma_info m ON r.benutzer_id = m.id
                                JOIN edv_ressourcen res ON r.ressource = res.id
                                JOIN edv_ressourcen_typ rt ON res.typ_id = rt.id
                                WHERE r.datum >= CURDATE()
                                ORDER BY r.datum, r.startZeit";
                    }
                    break;
                    
                case 'dienstwagen':
                    if (in_array('dienstwagen', $admin_permissions)) {
                        $query = "SELECT r.id, r.datum, r.startZeit, r.endZeit, r.ziel as anlass, 
                                r.bemerkung, m.nachname, m.vorname, m.id as benutzer_id,
                                c.name as dienstwagen_name, c.id as dienstwagen_id,
                                c.reichweite as dienstwagen_reichweite,
                                'dienstwagen' as type
                                FROM companycar_reservations r
                                JOIN ma_info m ON r.benutzer_id = m.id
                                JOIN companycar_pool c ON r.dienstwagen = c.id
                                WHERE r.datum >= CURDATE()
                                ORDER BY r.datum, r.startZeit";
                    }
                    break;
                    
                case 'dienstfahrrad':
                    if (in_array('dienstfahrrad', $admin_permissions)) {
                        $query = "SELECT r.id, r.datum, r.startZeit, r.endZeit, r.zweck as anlass, 
                                r.bemerkung, m.nachname, m.vorname, m.id as benutzer_id,
                                c.name as dienstfahrrad_name, c.id as dienstfahrrad_id,
                                c.reichweite as dienstfahrrad_reichweite,
                                'dienstfahrrad' as type
                                FROM companybicycle_reservations r
                                JOIN ma_info m ON r.benutzer_id = m.id
                                JOIN companybicycle_pool c ON r.dienstfahrrad = c.id
                                WHERE r.datum >= CURDATE()
                                ORDER BY r.datum, r.startZeit";
                    }
                    break;

                case 'rollup':
                    if (in_array('rollup', $admin_permissions)) {
                        $query = "SELECT r.id, r.datum, r.startZeit, r.endZeit, r.zweck as anlass, 
                                r.bemerkung, m.nachname, m.vorname, m.id as benutzer_id,
                                c.name as rollup_name, c.id as rollup_id,
                                'rollup' as type
                                FROM rollup_reservations r
                                JOIN ma_info m ON r.benutzer_id = m.id
                                JOIN rollup_pool c ON r.rollup_id = c.id
                                WHERE r.datum >= CURDATE()
                                ORDER BY r.datum, r.startZeit";
                    }
                    break;
            }
        }
        
        // Execute the query if it's not empty
        if (!empty($query)) {
            $stmt = $db_conn->prepare($query);
            $stmt->execute();
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Fetch service cars for the vehicle change dropdown (if user has dienstwagen permission)
        $dienstwagen = [];
        if (in_array('dienstwagen', $admin_permissions)) {
            $stmt = $db_conn->query("SELECT id, name FROM companycar_pool ORDER BY name");
            $dienstwagen = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Fetch service bicycles for the vehicle change dropdown (if user has dienstfahrrad permission)
        $dienstfahrrad = [];
        if (in_array('dienstfahrrad', $admin_permissions)) {
            $stmt = $db_conn->query("SELECT id, name FROM companybicycle_pool ORDER BY name");
            $dienstfahrrad = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        $db_error = true;
        error_log("Fehler beim Abrufen der Reservierungen: " . $e->getMessage());
    }
}

// Helper functions
function formatDate($date) {
    return date("d.m.Y", strtotime($date));
}

function formatTime($time) {
    return substr($time, 0, 5);
}

function getReservationTypeName($type) {
    switch ($type) {
        case 'room':
            return 'Raumreservierung';
        case 'edv':
            return 'EDV-Ressource';
        case 'dienstwagen':
            return 'Dienstwagen';
        case 'dienstfahrrad':
            return 'Dienstfahrrad';
        case 'rollup':
            return 'Roll-Up/Präsentationsstand';
        default:
            return 'Unbekannt';
    }
}

function getTypeColor($type) {
    switch ($type) {
        case 'room':
            return '#4299e1'; // Blau
        case 'edv':
            return '#48bb78'; // Grün
        case 'dienstwagen':
            return '#ed8936'; // Orange
        case 'dienstfahrrad':
            return '#d533ff'; // Pink
        case 'rollup':
            return '#805ad5'; // Lila
        default:
            return '#a0aec0'; // Grau
    }
}

function formatParticipants($anzahl, $externe) {
    $output = $anzahl . ' Person' . ($anzahl != 1 ? 'en' : '');
    if ($externe) {
        $output .= ' (inkl. Externe)';
    }
    return $output;
}

function formatCatering($kaffee, $tee, $kaltgetraenke) {
    $items = [];
    if ($kaffee > 0) {
        $items[] = "Kaffee: " . $kaffee;
    }
    if ($tee > 0) {
        $items[] = "Tee: " . $tee;
    }
    if ($kaltgetraenke > 0) {
        $items[] = "Kaltgetränke: " . $kaltgetraenke;
    }
    return empty($items) ? '-' : implode(', ', $items);
}

// Helper function to format the range
function formatRange($range) {
    if (empty($range)) {
        return 'Keine Angabe';
    }
    // Check if the range already contains 'km' or other unit
    if (preg_match('/\d+\s*(km|Km|KM)$/', $range)) {
        return $range;
    }
    return $range . ' km';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Kreis Kleve</title>
    <link rel="stylesheet" href="../style.css">
    <style>
		header {
			position: sticky;
			top: 0;
			z-index: 1000; /* Damit er über anderen Elementen liegt */
		}
        .dashboard-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background-color: var(--color-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background-color: white;
            padding: 0.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-tab:hover {
            background-color: var(--color-gray-100);
        }
        
        .filter-tab.active {
            background-color: var(--color-primary);
            color: white;
        }
        
        .reservations-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .reservations-table th {
            background-color: #f9fafb;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.1);
        }
        
        .reservations-table td {
            padding: 1rem;
            border-top: 1px solid var(--color-gray-200);
            background-color: white;
        }
        
        .reservations-table tr:hover td {
            background-color: var(--color-gray-50);
        }
        
        /* Remove hover details */
        .reservations-table tr[title],
        .reservations-table td[title] {
            title: none !important;
        }
        
        .reservation-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .btn-edit {
            background-color: #4299e1;
            color: white;
        }
        
        .btn-edit:hover {
            background-color: #3182ce;
        }
        
        .btn-delete {
            background-color: #f56565;
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #e53e3e;
        }
        
        .btn-change {
            background-color: #805ad5;
            color: white;
        }
        
        .btn-change:hover {
            background-color: #6b46c1;
        }
        
        .btn-logout {
            background-color: var(--color-gray-100);
            color: var(--color-gray-700);
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: background-color 0.2s;
        }
        
        .btn-logout:hover {
            background-color: var(--color-gray-200);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
            animation: fadeIn 0.2s ease-out;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--color-gray-200);
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--color-gray-500);
        }
        
        .modal-form .form-group:last-child {
            margin-bottom: 0;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        
        .btn-secondary {
            background-color: var(--color-gray-200);
            color: var(--color-gray-700);
        }
        
        .btn-secondary:hover {
            background-color: var(--color-gray-300);
        }
        
        .modal-description {
            margin-bottom: 1.5rem;
            color: var(--color-gray-700);
        }
        
        .reservation-details {
            background-color: var(--color-gray-50);
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 0.5rem;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
        }
        
        .detail-label {
            flex: 0 0 120px;
            font-weight: 600;
            color: var(--color-gray-700);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .empty-state {
            padding: 2rem;
            text-align: center;
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--color-gray-700);
        }
        
        .empty-state p {
            color: var(--color-gray-500);
            margin-bottom: 1.5rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.25rem;
            margin-top: 1.5rem;
        }
        
        .pagination-button {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--color-gray-300);
            background-color: white;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .pagination-button:hover {
            background-color: var(--color-gray-100);
        }
        
        .pagination-button.active {
            background-color: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }
        
        .pagination-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Additional styles for room reservation extras */
        .form-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--color-gray-200);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .form-section-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 1rem;
            color: var(--color-gray-700);
        }
        
        .toggle-wrapper {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .toggle-wrapper > label:first-child {
            margin-right: 0.75rem;
            flex: 1;
        }
        
        .toggle {
            position: relative;
            display: inline-block;
            width: 28px;
            height: 16px;
            flex-shrink: 0;
        }
        
        .toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 999px;
        }
        
        .toggle-slider::before {
            position: absolute;
            content: "";
            height: 12px;
            width: 12px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        
        .toggle input:checked + .toggle-slider {
            background-color: #4CAF50; /* oder: var(--color-primary) */
        }
        
        .toggle input:checked + .toggle-slider::before {
            transform: translateX(12px);
        }
        
        /* Modified toggle wrapper for Leitsystem */
        .leitsystem-wrapper {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .leitsystem-wrapper .leitsystem-label {
            flex: 0 0 auto;
            margin-right: 0.75rem;
        }
        
        .leitsystem-wrapper .toggle {
            margin: 0 0.75rem;
        }
        
        .leitsystem-wrapper .leitsystem-description {
            flex: 1;
            font-size: 0.875rem;
            color: var(--color-gray-700);
        }
        
        .beverage-inputs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
        }
        
        .form-group-inline {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group-inline label {
            white-space: nowrap;
        }
        
        .form-group-inline input {
            width: 70px;
        }

        /* Responsive table styles */
        @media (max-width: 1024px) {
            .reservations-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
        .btn-manage {
            background-color: #4CAF50;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: background-color 0.2s;
            margin-bottom: 1rem;
        }
        
        .btn-manage:hover {
            background-color: #45a049;
        }
        
        .management-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .btn-manage-vehicles {
            background-color: #088c37;
        }
        
        .btn-manage-vehicles:hover {
            background-color: #13a842;
        }
        
        .btn-manage-rooms {
            background-color: #088c37;
        }
        
        .btn-manage-rooms:hover {
            background-color: #13a842;
        }
        
        .btn-manage-edv {
            background-color: #088c37;
        }
        
        .btn-manage-edv:hover {
            background-color: #13a842;
        }
        
        .btn-manage-rollup {
            background-color: #088c37;
        }
        
        .btn-manage-rollup:hover {
            background-color: #13a842;
        }

        /* Range Badge Styles */
        .range-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            background-color: #EBF8FF;
            color: #2B6CB0;
            transition: all 0.2s;
        }

        .dienstwagen-range {
            background-color: #FEEBCF;
            color: #9C4221;
        }

        .dienstfahrrad-range {
            background-color: #FAE6FE;
            color: #86198F;
        }

        /* Animation for range badge */
        @keyframes pulseRange {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .range-badge-new {
            animation: pulseRange 2s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Kreis Kleve - Administrationsbereich</h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Raumreservierung</a></li>
                    <li><a href="../edv-ressourcen.php">EDV-Ressourcen-Reservierung</a></li>
                    <li><a href="../dienstwagen.php">Dienstwagen-Reservierung</a></li>
                    <li><a href="../dienstfahrrad.php">Dienstfahrrad-Reservierung</a></li>
                    <li><a href="../rollup-praesentationsstand.php">Roll-Ups & Präsentationsstand</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <div class="dashboard-header">
                <h2>Reservierungsverwaltung</h2>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo substr($admin_display_name, 0, 1); ?>
                    </div>
                    <div>
                        <div><?php echo htmlspecialchars($admin_display_name); ?></div>
                        <a href="index.php?action=logout" class="btn-logout">Abmelden</a>
                    </div>
                </div>
            </div>
            
            <?php if ($success_message): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <div class="management-buttons">
                <?php if (in_array('dienstwagen', $admin_permissions) || in_array('dienstfahrrad', $admin_permissions)): ?>
                <a href="ajax/vehiclemanagement.php" class="btn-manage btn-manage-vehicles">
                    Fahrzeuge verwalten
                </a>
                <?php endif; ?>
                
                <?php if (in_array('room', $admin_permissions)): ?>
                <a href="ajax/roommanagement.php" class="btn-manage btn-manage-rooms">
                    Räume verwalten
                </a>
                <?php endif; ?>
                
                <?php if (in_array('edv', $admin_permissions)): ?>
                <a href="ajax/edvmanagement.php" class="btn-manage btn-manage-edv">
                    EDV-Ressourcen verwalten
                </a>
                <?php endif; ?>
                
                <?php if (in_array('rollup', $admin_permissions)): ?>
                <a href="ajax/roll-upmanagement.php" class="btn-manage btn-manage-rollup">
                    Roll-Ups verwalten
                </a>
                <?php endif; ?>
            </div>
            
            <div class="filter-tabs">
                <?php if (in_array('all', $allowed_types)): ?>
                <a href="?type=all" class="filter-tab <?php echo $reservation_type === 'all' ? 'active' : ''; ?>">
                    Alle
                </a>
                <?php endif; ?>
                
                <?php if (in_array('room', $admin_permissions)): ?>
                <a href="?type=room" class="filter-tab <?php echo $reservation_type === 'room' ? 'active' : ''; ?>">
                    Räume
                </a>
                <?php endif; ?>
                
                <?php if (in_array('edv', $admin_permissions)): ?>
                <a href="?type=edv" class="filter-tab <?php echo $reservation_type === 'edv' ? 'active' : ''; ?>">
                    EDV-Ressourcen
                </a>
                <?php endif; ?>
                
                <?php if (in_array('dienstwagen', $admin_permissions)): ?>
                <a href="?type=dienstwagen" class="filter-tab <?php echo $reservation_type === 'dienstwagen' ? 'active' : ''; ?>">
                    Dienstwagen
                </a>
                <?php endif; ?>
                
                <?php if (in_array('dienstfahrrad', $admin_permissions)): ?>
                <a href="?type=dienstfahrrad" class="filter-tab <?php echo $reservation_type === 'dienstfahrrad' ? 'active' : ''; ?>">
                    Dienstfahrrad
                </a>
                <?php endif; ?>
                
                <?php if (in_array('rollup', $admin_permissions)): ?>
                <a href="?type=rollup" class="filter-tab <?php echo $reservation_type === 'rollup' ? 'active' : ''; ?>">
                    Roll-Ups
                </a>
                <?php endif; ?>
            </div>
            
            <?php if (empty($reservations)): ?>
            <div class="empty-state">
                <h3>Keine Reservierungen gefunden</h3>
                <p>Es sind derzeit keine Reservierungen für die ausgewählte Kategorie vorhanden.</p>
            </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="reservations-table">
					<thead>
						<tr>
							<th>Datum</th>
							<th>Zeit</th>
							<th>Benutzer</th>
							<th>Typ</th>
							<th>Details</th>
							<th>Bemerkung</th>
							<?php // MODIFIED_CONDITION: Show "Reichweite" column only for 'dienstwagen' or 'dienstfahrrad' ?>
							<?php if ($reservation_type === 'dienstwagen' || $reservation_type === 'dienstfahrrad'): ?>
							<th>Reichweite</th>
							<?php endif; ?>
							<th>Aktionen</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($reservations as $reservation): ?>
						<tr>
							<td><?php echo formatDate($reservation['datum']); ?></td>
							<td><?php echo formatTime($reservation['startZeit']) . ' - ' . formatTime($reservation['endZeit']); ?></td>
							<td><?php echo htmlspecialchars($reservation['nachname'] . ', ' . $reservation['vorname']); ?></td>
							<td>
								<span class="reservation-type" style="background-color: <?php echo getTypeColor($reservation['type']); ?>">
									<?php echo getReservationTypeName($reservation['type']); ?>
								</span>
							</td>
							<td>
								<?php 
								switch ($reservation['type']) {
									case 'room':
										echo htmlspecialchars($reservation['raum_name'] . ' - ' . $reservation['anlass']);
										if (isset($reservation['anzahl_personen'])) {
											echo '<br>' . formatParticipants($reservation['anzahl_personen'], $reservation['externe_teilnehmer']);
										}
										if (isset($reservation['kaffee_personen']) || isset($reservation['tee_personen']) || isset($reservation['kaltgetraenke_personen'])) {
											echo '<br>Bewirtung: ' . formatCatering($reservation['kaffee_personen'], $reservation['tee_personen'], $reservation['kaltgetraenke_personen']);
										}
										break;
									case 'edv':
										echo htmlspecialchars($reservation['ressourcentyp_name'] . ': ' . $reservation['ressource_name'] . ' - ' . $reservation['anlass']);
										break;
									case 'dienstwagen':
										echo htmlspecialchars($reservation['dienstwagen_name'] . ' - ' . $reservation['anlass']);
										break;
									case 'dienstfahrrad':
										echo htmlspecialchars($reservation['dienstfahrrad_name'] . ' - ' . $reservation['anlass']);
										break;
									case 'rollup':
										echo htmlspecialchars($reservation['rollup_name'] . ' - ' . $reservation['anlass']);
										break;
								}
								?>
							</td>
							<td>
								<?php echo !empty($reservation['bemerkung']) ? htmlspecialchars($reservation['bemerkung']) : ''; ?>
							</td>
							<?php // MODIFIED_CONDITION: Show range data cell only if column is visible (i.e., type is 'dienstwagen' or 'dienstfahrrad') ?>
							<?php if ($reservation_type === 'dienstwagen' || $reservation_type === 'dienstfahrrad'): ?>
							<td>
								<?php if ($reservation['type'] === 'dienstwagen' && isset($reservation['dienstwagen_reichweite'])): ?>
								<span class="range-badge dienstwagen-range">
									<?php echo formatRange($reservation['dienstwagen_reichweite']); ?>
								</span>
								<?php elseif ($reservation['type'] === 'dienstfahrrad' && isset($reservation['dienstfahrrad_reichweite'])): ?>
								<span class="range-badge dienstfahrrad-range">
									<?php echo formatRange($reservation['dienstfahrrad_reichweite']); ?>
								</span>
								<?php else: ?>
								<span>-</span>
								<?php endif; ?>
							</td>
							<?php endif; ?>
							<td class="actions">
								<?php if (user_has_permission($admin_username, $reservation['type'])): ?>
								<button class="btn-action btn-edit" 
										onclick="openEditModal(
											'<?php echo $reservation['type']; ?>', 
											<?php echo $reservation['id']; ?>, 
											'<?php echo $reservation['datum']; ?>', 
											'<?php echo $reservation['startZeit']; ?>', 
											'<?php echo $reservation['endZeit']; ?>', 
											'<?php echo addslashes($reservation['anlass']); ?>'
											<?php if ($reservation['type'] === 'room'): ?>,
											'<?php echo $reservation['anzahl_personen']; ?>',
											'<?php echo $reservation['externe_teilnehmer']; ?>',
											'<?php echo $reservation['leitsystem_anzeige']; ?>',
											'<?php echo $reservation['kaffee_personen']; ?>',
											'<?php echo $reservation['tee_personen']; ?>',
											'<?php echo $reservation['kaltgetraenke_personen']; ?>'
											<?php endif; ?>
										)">
									Bearbeiten
								</button>
								
								<button class="btn-action btn-delete" 
										onclick="openDeleteModal('<?php echo $reservation['type']; ?>', <?php echo $reservation['id']; ?>)">
									Stornieren
								</button>
								
								<?php if ($reservation['type'] === 'dienstwagen'): ?>
								<button class="btn-action btn-change" 
										onclick="openVehicleChangeModal(<?php echo $reservation['id']; ?>, 
										'<?php echo $reservation['dienstwagen_id']; ?>')">
									Fahrzeug ändern
								</button>
								<?php endif; ?>
								
								<?php if ($reservation['type'] === 'dienstfahrrad'): ?>
								<button class="btn-action btn-change" 
										onclick="openBicycleChangeModal(<?php echo $reservation['id']; ?>, 
										'<?php echo $reservation['dienstfahrrad_id']; ?>')">
									Fahrzeug ändern
								</button>
								<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
            </div>
            <?php endif; ?>
        </main>

        <footer>
            <p>© <?php echo date('Y'); ?> Kreis Kleve | Reservierungssystem</p>
        </footer>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reservierung bearbeiten</h3>
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            
            <form id="editForm" method="post" action="ajax/update-reservation.php">
                <input type="hidden" id="edit_type" name="type">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="form-section">
                    <div class="form-group">
                        <label for="edit_datum">Datum*</label>
                        <input type="date" id="edit_datum" name="datum" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_startZeit">Startzeit*</label>
                        <input type="time" id="edit_startZeit" name="startZeit" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_endZeit">Endzeit*</label>
                        <input type="time" id="edit_endZeit" name="endZeit" required>
                    </div>
                    
                    <div class="form-group" id="edit_anlass_group">
                        <label for="edit_anlass">Anlass/Ziel/Zweck*</label>
                        <input type="text" id="edit_anlass" name="anlass" required>
                    </div>
                </div>
                
                <div id="room_specific_fields" style="display: none;">
                    <div class="form-section">
                        <div class="form-section-title">Teilnehmer</div>
                        
                        <div class="form-group">
                            <label for="edit_anzahl_personen">Anzahl Personen*</label>
                            <input type="number" id="edit_anzahl_personen" name="anzahl_personen" min="1" value="1">
                        </div>
                        
                        <div class="toggle-wrapper">
                            <label for="edit_externe_teilnehmer">Externe Teilnehmer</label>
                            <label class="toggle">
                                <input type="checkbox" id="edit_externe_teilnehmer" name="externe_teilnehmer" value="1">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-wrapper">
                            <label for="edit_leitsystem_anzeige">Leitsystem-Anzeige</label>
                            <label class="toggle">
                                <input type="checkbox" id="edit_leitsystem_anzeige" name="leitsystem_anzeige" value="1">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">Bewirtung</div>
                        
                        <div class="beverage-inputs">
                            <div class="form-group-inline">
                                <label for="edit_kaffee_personen">Kaffee:</label>
                                <input type="number" id="edit_kaffee_personen" name="kaffee_personen" min="0" value="0">
                            </div>
                            
                            <div class="form-group-inline">
                                <label for="edit_tee_personen">Tee:</label>
                                <input type="number" id="edit_tee_personen" name="tee_personen" min="0" value="0">
                            </div>
                            
                            <div class="form-group-inline">
                                <label for="edit_kaltgetraenke_personen">Kaltgetränke:</label>
                                <input type="number" id="edit_kaltgetraenke_personen" name="kaltgetraenke_personen" min="0" value="0">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('editModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-edit">Speichern</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reservierung stornieren</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            
            <div class="modal-description">
                <p>Möchten Sie diese Reservierung wirklich stornieren? Diese Aktion kann nicht rückgängig gemacht werden.</p>
            </div>
            
            <form id="deleteForm" method="post" action="ajax/delete-reservation.php">
                <input type="hidden" id="delete_type" name="type">
                <input type="hidden" id="delete_id" name="id">
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('deleteModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-delete">Stornieren</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="vehicleChangeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Fahrzeug ändern</h3>
                <button class="modal-close" onclick="closeModal('vehicleChangeModal')">&times;</button>
            </div>
            
            <div class="modal-description">
                <p>Bitte wählen Sie ein anderes Fahrzeug für diese Reservierung.</p>
            </div>
            
            <form id="vehicleChangeForm" method="post" action="ajax/change-vehicle.php">
                <input type="hidden" id="vehicle_reservation_id" name="reservation_id">
                
                <div class="form-group">
                    <label for="vehicle_new">Neues Fahrzeug*</label>
                    <select id="vehicle_new" name="new_vehicle_id" required>
                        <?php foreach ($dienstwagen as $fahrzeug): ?>
                        <option value="<?php echo $fahrzeug['id']; ?>"><?php echo htmlspecialchars($fahrzeug['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('vehicleChangeModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-change">Ändern</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="bicycleChangeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Fahrzeug ändern</h3>
                <button class="modal-close" onclick="closeModal('bicycleChangeModal')">&times;</button>
            </div>
            
            <div class="modal-description">
                <p>Bitte wählen Sie ein anderes Fahrzeug für diese Reservierung.</p>
            </div>
            
            <form id="bicycleChangeForm" method="post" action="ajax/change-bicycle.php">
                <input type="hidden" id="bicycle_reservation_id" name="reservation_id">
                
                <div class="form-group">
                    <label for="bicycle_new">Neues Fahrzeug*</label>
                    <select id="bicycle_new" name="new_bicycle_id" required>
                        <?php foreach ($dienstfahrrad as $fahrzeug): ?>
                        <option value="<?php echo $fahrzeug['id']; ?>"><?php echo htmlspecialchars($fahrzeug['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('bicycleChangeModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-change">Ändern</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function openEditModal(type, id, datum, startZeit, endZeit, anlass, anzahlPersonen, externeTeilnehmer, leitsystemAnzeige, kaffeePersonen, teePersonen, kaltgetraenkePersonen) {
            document.getElementById('edit_type').value = type;
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_datum').value = datum;
            document.getElementById('edit_startZeit').value = startZeit;
            document.getElementById('edit_endZeit').value = endZeit;
            document.getElementById('edit_anlass').value = anlass;
            
            // Anpassen der Anlass/Zweck/Ziel Beschriftung je nach Typ
            const anlassLabel = document.querySelector('label[for="edit_anlass"]');
            const roomSpecificFields = document.getElementById('room_specific_fields');

            if (type === 'room') {
                anlassLabel.textContent = 'Anlass*';
                roomSpecificFields.style.display = 'block';
                
                // Set room-specific fields only if they are provided
                document.getElementById('edit_anzahl_personen').value = anzahlPersonen !== undefined ? anzahlPersonen : '1';
                document.getElementById('edit_externe_teilnehmer').checked = externeTeilnehmer == '1'; // Check against string '1'
                document.getElementById('edit_leitsystem_anzeige').checked = leitsystemAnzeige == '1'; // Check against string '1'
                document.getElementById('edit_kaffee_personen').value = kaffeePersonen !== undefined ? kaffeePersonen : '0';
                document.getElementById('edit_tee_personen').value = teePersonen !== undefined ? teePersonen : '0';
                document.getElementById('edit_kaltgetraenke_personen').value = kaltgetraenkePersonen !== undefined ? kaltgetraenkePersonen : '0';
            } else {
                roomSpecificFields.style.display = 'none';
                if (type === 'dienstwagen') {
                    anlassLabel.textContent = 'Ziel*';
                } else {
                    anlassLabel.textContent = 'Zweck*';
                }
            }
            
            openModal('editModal');
        }
        
        function openDeleteModal(type, id) {
            document.getElementById('delete_type').value = type;
            document.getElementById('delete_id').value = id;
            
            openModal('deleteModal');
        }
        
        function openVehicleChangeModal(reservationId, currentVehicleId) {
            document.getElementById('vehicle_reservation_id').value = reservationId;
            document.getElementById('vehicle_new').value = currentVehicleId;
            
            openModal('vehicleChangeModal');
        }
        
        function openBicycleChangeModal(reservationId, currentBicycleId) {
            document.getElementById('bicycle_reservation_id').value = reservationId;
            document.getElementById('bicycle_new').value = currentBicycleId;
            
            openModal('bicycleChangeModal');
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target === modals[i]) {
                    closeModal(modals[i].id);
                }
            }
        });
    </script>
</body>
</html>