<?php
ini_set('error_log', 'C:/xampp/htdocs/kreiskleveprojektvinf23/wp-content/debug.log');
session_start();

// Include database configuration
require_once('admin/db-config.php');

$db_conn = null;
$db_error = false;
$successMessage = null;
$errorMessage = null;
$reservationType = null;
$resourceOptions = [];
$originalReservation = null;
$isFormSubmitted = false;

// Connect to database
try {
    $db_conn = new PDO("mysql:host=$db_hostname;dbname=$db_datenbankname;charset=utf8", $db_username, $db_passwort);
    $db_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $db_error = true;
    error_log("Es ist ein Fehler bei der Verbindung mit der Datenbank aufgetreten: " . $e->getMessage());
}

// Function to check if date-time is in the past
function isDateTimeInPast($date, $time) {
    $dateTime = new DateTime($date . ' ' . $time);
    $now = new DateTime();
    return $dateTime < $now;
}

// Function to format date for display
function formatDate($date) {
    return date("d.m.Y", strtotime($date));
}

// Function to format time for display
function formatTime($time) {
    return substr($time, 0, 5);
}

// Function to get resource type name
function getResourceTypeName($type) {
    switch ($type) {
        case 'room': return 'Raum';
        case 'car': return 'Dienstwagen';
        case 'bicycle': return 'Dienstfahrrad';
        case 'rollup': return 'Roll-Up/Präsentationsstand';
        case 'edv': return 'EDV-Ressource';
        default: return 'Unbekannt';
    }
}

// Function to get the resource ID field name based on reservation type
function getResourceIdFieldName($type) {
    switch ($type) {
        case 'room': return 'raum_id';
        case 'car': return 'dienstwagen';
        case 'bicycle': return 'dienstfahrrad';
        case 'rollup': return 'rollup_id';
        case 'edv': return 'ressource';
        default: return '';
    }
}

// Get the current resource ID from the original reservation
function getCurrentResourceId($reservation, $type) {
    $fieldName = getResourceIdFieldName($type);
    return $reservation[$fieldName] ?? '';
}

// Check if storno token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $errorMessage = "Kein gültiger Änderungslink. Bitte überprüfen Sie den Link oder kontaktieren Sie den Administrator.";
} elseif ($db_conn) {
    $token = $_GET['token'];

    // Try to find the token in each reservation table
    try {
        // Check room reservations
        $stmt = $db_conn->prepare("
            SELECT r.*, rp.name as resource_name, m.nachname, m.vorname, m.id as mitarbeiter_id
            FROM room_reservations r
            JOIN room_pool rp ON r.raum_id = rp.id
            JOIN ma_info m ON r.benutzer_id = m.id
            WHERE r.storno = :token
        ");
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $reservationType = 'room';
            $originalReservation = $row;

            // Get all rooms for dropdown
            $stmt = $db_conn->query("SELECT id, name FROM room_pool ORDER BY name");
            $resourceOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Check car reservations if token not found yet
        if (!$originalReservation) {
            $stmt = $db_conn->prepare("
                SELECT r.*, cp.name as resource_name, cp.reichweite, m.nachname, m.vorname, m.id as mitarbeiter_id
                FROM companycar_reservations r
                JOIN companycar_pool cp ON r.dienstwagen = cp.id
                JOIN ma_info m ON r.benutzer_id = m.id
                WHERE r.storno = :token
            ");
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $reservationType = 'car';
                $originalReservation = $row;

                // Get all cars for dropdown (though we won't allow changing cars)
                $stmt = $db_conn->query("SELECT id, name, reichweite FROM companycar_pool ORDER BY name");
                $resourceOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // Check bicycle reservations if token not found yet
        if (!$originalReservation) {
            $stmt = $db_conn->prepare("
                SELECT r.*, cb.name as resource_name, cb.reichweite, m.nachname, m.vorname, m.id as mitarbeiter_id
                FROM companybicycle_reservations r
                JOIN companybicycle_pool cb ON r.dienstfahrrad = cb.id
                JOIN ma_info m ON r.benutzer_id = m.id
                WHERE r.storno = :token
            ");
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $reservationType = 'bicycle';
                $originalReservation = $row;

                // Get all bicycles for dropdown (though we won't allow changing bicycles)
                $stmt = $db_conn->query("SELECT id, name, reichweite FROM companybicycle_pool ORDER BY name");
                $resourceOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // Check rollup reservations if token not found yet
        if (!$originalReservation) {
            $stmt = $db_conn->prepare("
                SELECT r.*, rp.name as resource_name, m.nachname, m.vorname, m.id as mitarbeiter_id
                FROM rollup_reservations r
                JOIN rollup_pool rp ON r.rollup_id = rp.id
                JOIN ma_info m ON r.benutzer_id = m.id
                WHERE r.storno = :token
            ");
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $reservationType = 'rollup';
                $originalReservation = $row;

                // Get all rollups for dropdown
                $stmt = $db_conn->query("SELECT id, name FROM rollup_pool ORDER BY name");
                $resourceOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // Check EDV reservations if token not found yet
        if (!$originalReservation) {
            $stmt = $db_conn->prepare("
                SELECT r.*, er.name as resource_name, er.typ_id, ert.name as typ_name,
                       m.nachname, m.vorname, m.id as mitarbeiter_id
                FROM edv_reservations r
                JOIN edv_ressourcen er ON r.ressource = er.id
                JOIN edv_ressourcen_typ ert ON er.typ_id = ert.id
                JOIN ma_info m ON r.benutzer_id = m.id
                WHERE r.storno = :token
            ");
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $reservationType = 'edv';
                $originalReservation = $row;

                // Get all resources of the same type for dropdown
                $stmt = $db_conn->prepare("SELECT id, name FROM edv_ressourcen WHERE typ_id = :typ_id ORDER BY name");
                $stmt->bindParam(':typ_id', $row['typ_id']);
                $stmt->execute();
                $resourceOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

    } catch (PDOException $e) {
        $db_error = true;
        error_log("Fehler beim Abrufen der Reservierungsdaten: " . $e->getMessage());
        $errorMessage = "Datenbankfehler beim Abrufen der Reservierung. Bitte kontaktieren Sie den Administrator.";
    }

    // If token is invalid, set error message
    if (!$originalReservation) {
        $errorMessage = "Ungültiger oder abgelaufener Änderungslink. Bitte überprüfen Sie den Link oder kontaktieren Sie den Administrator.";
    }
}

// Process form submission for updating reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_reservation']) && $db_conn && $originalReservation) {
    $isFormSubmitted = true;
    $token = $_POST['token'];
    $datum = filter_input(INPUT_POST, 'datum', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $startZeit = filter_input(INPUT_POST, 'startZeit', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $endZeit = filter_input(INPUT_POST, 'endZeit', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $resourceId = filter_input(INPUT_POST, 'resource_id', FILTER_SANITIZE_NUMBER_INT);
    $bemerkung = filter_input(INPUT_POST, 'bemerkung', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // These fields depend on reservation type
    $anlass = filter_input(INPUT_POST, 'anlass', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $zweck = filter_input(INPUT_POST, 'zweck', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $ziel = filter_input(INPUT_POST, 'ziel', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Room-specific fields
    $anzahlPersonen = filter_input(INPUT_POST, 'anzahlPersonen', FILTER_SANITIZE_NUMBER_INT);
    $externeTeilnehmer = isset($_POST['externeTeilnehmer']) ? 1 : 0;
    $kaffeePersonen = filter_input(INPUT_POST, 'kaffeePersonen', FILTER_SANITIZE_NUMBER_INT) ?: 0;
    $teePersonen = filter_input(INPUT_POST, 'teePersonen', FILTER_SANITIZE_NUMBER_INT) ?: 0;
    $kaltgetraenkePersonen = filter_input(INPUT_POST, 'kaltgetraenkePersonen', FILTER_SANITIZE_NUMBER_INT) ?: 0;
    $leitsystemAnzeige = isset($_POST['leitsystemAnzeige']) ? 1 : 0;

    $errors = [];

    // Validate common fields
    if (empty($datum)) {
        $errors[] = "Bitte geben Sie ein Datum an.";
    }

    if (empty($startZeit)) {
        $errors[] = "Bitte geben Sie eine Startzeit an.";
    }

    if (empty($endZeit)) {
        $errors[] = "Bitte geben Sie eine Endzeit an.";
    }

    if ($startZeit >= $endZeit) {
        $errors[] = "Die Startzeit muss vor der Endzeit liegen.";
    }

    // Check if date and time are in the past
    if (!empty($datum) && !empty($startZeit) && isDateTimeInPast($datum, $startZeit)) {
        $errors[] = "Das Startdatum und die Startzeit dürfen nicht in der Vergangenheit liegen.";
    }

    // Type-specific validations
    switch ($reservationType) {
        case 'room':
            if (empty($anlass)) {
                $errors[] = "Bitte geben Sie einen Anlass an.";
            }
            if (empty($anzahlPersonen) || $anzahlPersonen <= 0) {
                $errors[] = "Bitte geben Sie eine gültige Anzahl an Personen an.";
            }
            break;

        case 'car':
            if (empty($ziel)) {
                $errors[] = "Bitte geben Sie ein Ziel an.";
            }
            break;

        case 'bicycle':
        case 'rollup':
        case 'edv':
            if (empty($zweck)) {
                $errors[] = "Bitte geben Sie einen Zweck an.";
            }
            break;
    }

    // Check availability if date or time has changed
    if (empty($errors) && ($datum != $originalReservation['datum'] || $startZeit != $originalReservation['startZeit'] || $endZeit != $originalReservation['endZeit'])) {
        try {
            // Different SQL queries for different resource types
            switch ($reservationType) {
                case 'room':
                    // Check room availability
                    $resourceFieldName = 'raum_id';
                    $resourceId = $resourceId ?: $originalReservation[$resourceFieldName];

                    $stmt = $db_conn->prepare("
                        SELECT COUNT(*) FROM room_reservations
                        WHERE datum = :datum AND raum_id = :raum_id AND id != :current_id AND (
                            (startZeit <= :startZeit AND endZeit > :startZeit) OR
                            (startZeit < :endZeit AND endZeit >= :endZeit) OR
                            (startZeit >= :startZeit AND endZeit <= :endZeit)
                        )
                    ");
                    $stmt->bindParam(':datum', $datum);
                    $stmt->bindParam(':startZeit', $startZeit);
                    $stmt->bindParam(':endZeit', $endZeit);
                    $stmt->bindParam(':raum_id', $resourceId);
                    $stmt->bindParam(':current_id', $originalReservation['id']);
                    $stmt->execute();

                    if ($stmt->fetchColumn() > 0) {
                        $errors[] = "Der Raum ist im gewählten Zeitraum bereits reserviert.";
                    }
                    break;

                case 'car':
                    // Check car availability
                    $resourceFieldName = 'dienstwagen';
                    $resourceId = $originalReservation[$resourceFieldName]; // Cannot change the car

                    $stmt = $db_conn->prepare("
                        SELECT COUNT(*) FROM companycar_reservations
                        WHERE datum = :datum AND dienstwagen = :car_id AND id != :current_id AND (
                            (startZeit <= :startZeit AND endZeit > :startZeit) OR
                            (startZeit < :endZeit AND endZeit >= :endZeit) OR
                            (startZeit >= :startZeit AND endZeit <= :endZeit)
                        )
                    ");
                    $stmt->bindParam(':datum', $datum);
                    $stmt->bindParam(':startZeit', $startZeit);
                    $stmt->bindParam(':endZeit', $endZeit);
                    $stmt->bindParam(':car_id', $resourceId);
                    $stmt->bindParam(':current_id', $originalReservation['id']);
                    $stmt->execute();

                    if ($stmt->fetchColumn() > 0) {
                        $errors[] = "Der Dienstwagen ist im gewählten Zeitraum bereits reserviert.";
                    }
                    break;

                case 'bicycle':
                    // Check bicycle availability
                    $resourceFieldName = 'dienstfahrrad';
                    $resourceId = $originalReservation[$resourceFieldName]; // Cannot change the bicycle

                    $stmt = $db_conn->prepare("
                        SELECT COUNT(*) FROM companybicycle_reservations
                        WHERE datum = :datum AND dienstfahrrad = :bicycle_id AND id != :current_id AND (
                            (startZeit <= :startZeit AND endZeit > :startZeit) OR
                            (startZeit < :endZeit AND endZeit >= :endZeit) OR
                            (startZeit >= :startZeit AND endZeit <= :endZeit)
                        )
                    ");
                    $stmt->bindParam(':datum', $datum);
                    $stmt->bindParam(':startZeit', $startZeit);
                    $stmt->bindParam(':endZeit', $endZeit);
                    $stmt->bindParam(':bicycle_id', $resourceId);
                    $stmt->bindParam(':current_id', $originalReservation['id']);
                    $stmt->execute();

                    if ($stmt->fetchColumn() > 0) {
                        $errors[] = "Das Dienstfahrrad ist im gewählten Zeitraum bereits reserviert.";
                    }
                    break;

                case 'rollup':
                    // Check rollup availability
                    $resourceFieldName = 'rollup_id';
                    $resourceId = $originalReservation[$resourceFieldName]; // Cannot change the rollup

                    $stmt = $db_conn->prepare("
                        SELECT COUNT(*) FROM rollup_reservations
                        WHERE datum = :datum AND rollup_id = :rollup_id AND id != :current_id AND (
                            (startZeit <= :startZeit AND endZeit > :startZeit) OR
                            (startZeit < :endZeit AND endZeit >= :endZeit) OR
                            (startZeit >= :startZeit AND endZeit <= :endZeit)
                        )
                    ");
                    $stmt->bindParam(':datum', $datum);
                    $stmt->bindParam(':startZeit', $startZeit);
                    $stmt->bindParam(':endZeit', $endZeit);
                    $stmt->bindParam(':rollup_id', $resourceId);
                    $stmt->bindParam(':current_id', $originalReservation['id']);
                    $stmt->execute();

                    if ($stmt->fetchColumn() > 0) {
                        $errors[] = "Das Roll-Up/Präsentationsstand ist im gewählten Zeitraum bereits reserviert.";
                    }
                    break;

                case 'edv':
                    // Check EDV resource availability
                    $resourceFieldName = 'ressource';
                    $resourceId = $originalReservation[$resourceFieldName]; // Cannot change the EDV resource

                    $stmt = $db_conn->prepare("
                        SELECT COUNT(*) FROM edv_reservations
                        WHERE datum = :datum AND ressource = :ressource_id AND id != :current_id AND (
                            (startZeit <= :startZeit AND endZeit > :startZeit) OR
                            (startZeit < :endZeit AND endZeit >= :endZeit) OR
                            (startZeit >= :startZeit AND endZeit <= :endZeit)
                        )
                    ");
                    $stmt->bindParam(':datum', $datum);
                    $stmt->bindParam(':startZeit', $startZeit);
                    $stmt->bindParam(':endZeit', $endZeit);
                    $stmt->bindParam(':ressource_id', $resourceId);
                    $stmt->bindParam(':current_id', $originalReservation['id']);
                    $stmt->execute();

                    if ($stmt->fetchColumn() > 0) {
                        $errors[] = "Die EDV-Ressource ist im gewählten Zeitraum bereits reserviert.";
                    }
                    break;
            }

            // If no errors, update the reservation
            if (empty($errors)) {
                switch ($reservationType) {
                    case 'room':
                        $stmt = $db_conn->prepare("
                            UPDATE room_reservations
                            SET datum = :datum, startZeit = :startZeit, endZeit = :endZeit,
                                anlass = :anlass, anzahl_personen = :anzahlPersonen,
                                externe_teilnehmer = :externeTeilnehmer,
                                kaffee_personen = :kaffeePersonen,
                                tee_personen = :teePersonen,
                                kaltgetraenke_personen = :kaltgetraenkePersonen,
                                leitsystem_anzeige = :leitsystemAnzeige,
                                bemerkung = :bemerkung
                            WHERE storno = :token
                        ");
                        $stmt->bindParam(':datum', $datum);
                        $stmt->bindParam(':startZeit', $startZeit);
                        $stmt->bindParam(':endZeit', $endZeit);
                        $stmt->bindParam(':anlass', $anlass);
                        $stmt->bindParam(':anzahlPersonen', $anzahlPersonen);
                        $stmt->bindParam(':externeTeilnehmer', $externeTeilnehmer);
                        $stmt->bindParam(':kaffeePersonen', $kaffeePersonen);
                        $stmt->bindParam(':teePersonen', $teePersonen);
                        $stmt->bindParam(':kaltgetraenkePersonen', $kaltgetraenkePersonen);
                        $stmt->bindParam(':leitsystemAnzeige', $leitsystemAnzeige);
                        $stmt->bindParam(':bemerkung', $bemerkung);
                        $stmt->bindParam(':token', $token);
                        break;

                    case 'car':
                        $stmt = $db_conn->prepare("
                            UPDATE companycar_reservations
                            SET datum = :datum, startZeit = :startZeit, endZeit = :endZeit,
                                ziel = :ziel, bemerkung = :bemerkung
                            WHERE storno = :token
                        ");
                        $stmt->bindParam(':datum', $datum);
                        $stmt->bindParam(':startZeit', $startZeit);
                        $stmt->bindParam(':endZeit', $endZeit);
                        $stmt->bindParam(':ziel', $ziel);
                        $stmt->bindParam(':bemerkung', $bemerkung);
                        $stmt->bindParam(':token', $token);
                        break;

                    case 'bicycle':
                        $stmt = $db_conn->prepare("
                            UPDATE companybicycle_reservations
                            SET datum = :datum, startZeit = :startZeit, endZeit = :endZeit,
                                zweck = :zweck, bemerkung = :bemerkung
                            WHERE storno = :token
                        ");
                        $stmt->bindParam(':datum', $datum);
                        $stmt->bindParam(':startZeit', $startZeit);
                        $stmt->bindParam(':endZeit', $endZeit);
                        $stmt->bindParam(':zweck', $zweck);
                        $stmt->bindParam(':bemerkung', $bemerkung);
                        $stmt->bindParam(':token', $token);
                        break;

                    case 'rollup':
                        $stmt = $db_conn->prepare("
                            UPDATE rollup_reservations
                            SET datum = :datum, startZeit = :startZeit, endZeit = :endZeit,
                                zweck = :zweck, bemerkung = :bemerkung
                            WHERE storno = :token
                        ");
                        $stmt->bindParam(':datum', $datum);
                        $stmt->bindParam(':startZeit', $startZeit);
                        $stmt->bindParam(':endZeit', $endZeit);
                        $stmt->bindParam(':zweck', $zweck);
                        $stmt->bindParam(':bemerkung', $bemerkung);
                        $stmt->bindParam(':token', $token);
                        break;

                    case 'edv':
                        $stmt = $db_conn->prepare("
                            UPDATE edv_reservations
                            SET datum = :datum, startZeit = :startZeit, endZeit = :endZeit,
                                zweck = :zweck, bemerkung = :bemerkung
                            WHERE storno = :token
                        ");
                        $stmt->bindParam(':datum', $datum);
                        $stmt->bindParam(':startZeit', $startZeit);
                        $stmt->bindParam(':endZeit', $endZeit);
                        $stmt->bindParam(':zweck', $zweck);
                        $stmt->bindParam(':bemerkung', $bemerkung);
                        $stmt->bindParam(':token', $token);
                        break;
                }

                $stmt->execute();
                $successMessage = "Die Reservierung wurde erfolgreich aktualisiert.";

                // Reload the reservation details to show updated information
                // TODO header("Location: " . $_SERVER['PHP_SELF'] . "?token=" . urlencode($token) . "&success=1");
                // TODO exit;
            }

        } catch (PDOException $e) {
            error_log("Fehler beim Aktualisieren der Reservierung: " . $e->getMessage());
            $errors[] = "Es ist ein Datenbankfehler aufgetreten. Bitte versuchen Sie es später erneut.";
        }
    }

    if (!empty($errors)) {
        $errorMessage = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kreis Kleve - Reservierung ändern</title>
    <link rel="stylesheet" href="style.css">
    <style>
        header {
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .ansprechpartner-block {
            background-color: #fff;
            border: 1px solid #cacccf;
            border-radius: 8px;
            padding: 1.5rem;
            max-width: 600px;
            margin: 2rem auto;
            text-align: center;
        }

        .ansprechpartner-block h2 {
            font-size: 1.5rem;
            color: #2d3748;
            margin-bottom: 1rem;
        }

        .ansprechpartner-block table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .ansprechpartner-block th,
        .ansprechpartner-block td {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        .ansprechpartner-block th {
            background-color: #e5e7eb;
            font-weight: 600;
        }


        .edit-form {
            background-color: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 2rem auto;
            max-width: 800px;
        }

        .edit-form h3 {
            color: #2d3748;
            margin-bottom: 1rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #4a5568;
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            background-color: #fff;
        }

        .form-group input[disabled],
        .form-group select[disabled] {
            background-color: #edf2f7;
            cursor: not-allowed;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .button-save {
            background-color: #48bb78;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.2s;
        }

        .button-save:hover {
            background-color: #38a169;
        }

        .button-cancel {
            background-color: #718096;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .button-cancel:hover {
            background-color: #4a5568;
        }

        .message {
            width: 100%;
            max-width: 800px;
            margin: 0 auto 2rem;
        }

        .catering-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .readonly-info {
            background-color: #f7fafc;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .readonly-info p {
            margin: 0.5rem 0;
            color: #4a5568;
        }

        .readonly-info strong {
            color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Kreis Kleve Reservierungssystem</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Raumreservierung</a></li>
                    <li><a href="edv-ressourcen.php">EDV-Ressourcen-Reservierung</a></li>
                    <li><a href="dienstwagen.php">Dienstwagen-Reservierung</a></li>
                    <li><a href="dienstfahrrad.php">Dienstfahrrad-Reservierung</a></li>
                    <li><a href="rollup-praesentationsstand.php">Roll-Ups & Präsentationsstand</a></li>
                    <li><a href="admin/index.php" class="admin-button">Adminbereich</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <h2>Reservierung ändern</h2>

            <?php if ($db_error): ?>
            <div class="message error">
                <span class="icon">!</span>
                <div class="message-content">
                    <h3>Datenbankfehler</h3>
                    <p>Es konnte keine Verbindung zur Datenbank hergestellt werden. Bitte versuchen Sie es später erneut oder kontaktieren Sie den Administrator.</p>
                </div>
            </div>

            <?php elseif ($errorMessage):?>
            <div class="message error">
                <span class="icon">!</span>
                <div class="message-content">
                    <h3>Fehler</h3>
                    <p><?php echo $errorMessage; ?></p>
                    <div class="button-group">
                        <button onclick="window.history.back();" class="button">Zurück zur Reservierung</button>
                    </div>
                </div>
            </div>

            <?php elseif ($successMessage): ?>
            <div class="message success">
                <div class="message-header">
                    <span class="icon">✓</span>
                    <div>
                        <h3>Änderung erfolgreich!</h3>
                        <p><?php echo $successMessage; ?></p>
                    </div>
                </div>
                <button onclick="window.location.href='index.php'" class="button">Zurück zur Übersicht</button>
            </div>

            <?php elseif ($originalReservation): ?>
            <form class="edit-form" method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?token=' . urlencode($token); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="readonly-info">
                    <h3>Reservierungsdetails</h3>
                    <p><strong>Reservierungstyp:</strong> <?php echo getResourceTypeName($reservationType); ?></p>
                    <p><strong>Ressource:</strong> <?php echo htmlspecialchars($originalReservation['resource_name']); ?></p>
                    <p><strong>Benutzer:</strong> <?php echo htmlspecialchars($originalReservation['vorname'] . ' ' . $originalReservation['nachname']); ?></p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="datum">Datum*</label>
                        <input type="date" id="datum" name="datum" required
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo $originalReservation['datum']; ?>">
                    </div>

                    <div class="form-group">
                        <label for="startZeit">Startzeit*</label>
                        <input type="time" id="startZeit" name="startZeit" required
                               value="<?php echo formatTime($originalReservation['startZeit']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="endZeit">Endzeit*</label>
                        <input type="time" id="endZeit" name="endZeit" required
                               value="<?php echo formatTime($originalReservation['endZeit']); ?>">
                    </div>
                </div>

                <?php if ($reservationType === 'room'): ?>
                <div class="form-group">
                    <label for="anlass">Anlass*</label>
                    <input type="text" id="anlass" name="anlass" required
                           value="<?php echo htmlspecialchars($originalReservation['anlass']); ?>">
                </div>

                <div class="form-group">
                    <label for="anzahlPersonen">Anzahl der Personen*</label>
                    <input type="number" id="anzahlPersonen" name="anzahlPersonen" min="1" required
                           value="<?php echo htmlspecialchars($originalReservation['anzahl_personen']); ?>">
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="externeTeilnehmer" name="externeTeilnehmer"
                           <?php echo $originalReservation['externe_teilnehmer'] ? 'checked' : ''; ?>>
                    <label for="externeTeilnehmer">Externe Teilnehmer</label>
                </div>

                <h3>Bewirtung</h3>
                <div class="catering-group">
                    <div class="form-group">
                        <label for="kaffeePersonen">Kaffee für wie viele Personen?</label>
                        <input type="number" id="kaffeePersonen" name="kaffeePersonen" min="0"
                               value="<?php echo htmlspecialchars($originalReservation['kaffee_personen']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="teePersonen">Tee für wie viele Personen?</label>
                        <input type="number" id="teePersonen" name="teePersonen" min="0"
                               value="<?php echo htmlspecialchars($originalReservation['tee_personen']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="kaltgetraenkePersonen">Kalte Getränke für wie viele Personen?</label>
                        <input type="number" id="kaltgetraenkePersonen" name="kaltgetraenkePersonen" min="0"
                               value="<?php echo htmlspecialchars($originalReservation['kaltgetraenke_personen']); ?>">
                    </div>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="leitsystemAnzeige" name="leitsystemAnzeige"
                           <?php echo $originalReservation['leitsystem_anzeige'] ? 'checked' : ''; ?>>
                    <label for="leitsystemAnzeige">Anzeige im Leitsystem</label>
                </div>

                <?php elseif ($reservationType === 'car'): ?>
                <div class="form-group">
                    <label for="ziel">Ziel*</label>
                    <input type="text" id="ziel" name="ziel" required
                           value="<?php echo htmlspecialchars($originalReservation['ziel']); ?>">
                </div>

                <?php else: ?>
                <div class="form-group">
                    <label for="zweck">Zweck*</label>
                    <input type="text" id="zweck" name="zweck" required
                           value="<?php echo htmlspecialchars($originalReservation['zweck']); ?>">
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="bemerkung">Bemerkung</label>
                    <textarea id="bemerkung" name="bemerkung" rows="3"><?php
                        echo htmlspecialchars($originalReservation['bemerkung']);
                    ?></textarea>
                </div>

                <div class="button-group">
                    <button type="submit" name="change_reservation" class="button-save">Änderungen speichern</button>
                    <a href="index.php" class="button-cancel">Abbrechen</a>
                </div>
            </form>
            <?php endif; ?>

            <!-- Ansprechpartner-Block -->
            <div class="ansprechpartner-block">
                <h2>Ansprechpartner</h2>
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#e5e7eb;">
                            <th style="padding:8px; border-bottom:1px solid #ccc;">Name</th>
                            <th style="padding:8px; border-bottom:1px solid #ccc;">Telefon</th>
                            <th style="padding:8px; border-bottom:1px solid #ccc;">E-Mail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding:8px;">Max Mustermann</td>
                            <td style="padding:8px;">01234 567890</td>
                            <td style="padding:8px;">max.mustermann@kreiskleve.de</td>
                        </tr>
                        <tr>
                            <td style="padding:8px;">Erika Musterfrau</td>
                            <td style="padding:8px;">09876 543210</td>
                            <td style="padding:8px;">erika.musterfrau@kreiskleve.de</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>

        <footer>
            <p>© <?php echo date('Y'); ?> Kreis Kleve Intranet | Reservierungssystem</p>
        </footer>
    </div>
</body>
</html>