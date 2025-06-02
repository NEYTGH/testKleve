<?php
ini_set('error_log', 'C:/xampp/htdocs/kreiskleveprojektvinf23/wp-content/debug.log');
session_start();

//Datenbankinformation
require_once('admin/db-config.php');

$db_conn=null;
$db_error=false;

try {
	$db_conn= new PDO("mysql:host=$db_hostname;dbname=$db_datenbankname;charset=utf8",$db_username, $db_passwort);
	$db_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}catch (PDOException $e) {
	$db_error=true;
	error_log("Es ist ein Fehler bei der Verbindung mit der Datenbank aufgetreten" . $e->getMessage());
}

// AJAX-Endpoint für das Laden der verfügbaren Räume
if (isset($_GET['action']) && $_GET['action'] === 'check_rooms' && $db_conn) {
    $datum = $_GET['datum'] ?? date('Y-m-d');
    $startZeit = $_GET['startzeit'] ?? '';
    $endZeit = $_GET['endzeit'] ?? '';
    error_log("=== AJAX Request check_rooms gestartet ===");
    error_log("Parameter empfangen: datum={$datum}, startZeit={$startZeit}, endZeit={$endZeit}");

    try {
        // Alle Räume holen
        $stmt = $db_conn->query("SELECT id, name FROM room_pool ORDER BY name");
        $allRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Alle Räume aus room_pool geladen: " . json_encode($allRooms));

        // Wenn Start- und Endzeit angegeben wurden, Verfügbarkeit prüfen
        if (!empty($startZeit) && !empty($endZeit)) {
            $availableRooms = [];
            error_log("Starte Verfügbarkeitsprüfung für alle Räume...");

            foreach ($allRooms as $room) {
                error_log("Prüfe Raum: ID=" . $room['id'] . ", Name=" . $room['name']);

                // Prüfen, ob Raum für den Zeitraum verfügbar ist
                $stmt = $db_conn->prepare("
                    SELECT COUNT(*) FROM room_reservations 
                    WHERE datum = :datum AND raum_id = :raum_id AND (
                        (startZeit <= :startZeit AND endZeit > :startZeit) OR
                        (startZeit < :endZeit AND endZeit >= :endZeit) OR
                        (startZeit >= :startZeit AND endZeit <= :endZeit)
                    )
                ");
                
                $stmt->bindParam(':datum', $datum);
                $stmt->bindParam(':startZeit', $startZeit);
                $stmt->bindParam(':endZeit', $endZeit);
                $stmt->bindParam(':raum_id', $room['id']);

                $stmt->execute();
                $count = $stmt->fetchColumn();
                
                error_log("Raum ID " . $room['id'] . " belegt? (count=$count)");

                if ($count == 0) {
                    $availableRooms[] = $room;
                    error_log("Raum ID " . $room['id'] . " ist verfügbar und wird hinzugefügt.");
                } else {
                    error_log("Raum ID " . $room['id'] . " ist NICHT verfügbar.");
                }
            }

            error_log("Verfügbare Räume: " . json_encode($availableRooms));

            header('Content-Type: application/json');
            echo json_encode($availableRooms);
        } else {
            error_log("Keine Start-/Endzeit angegeben, gebe alle Räume zurück.");
            header('Content-Type: application/json');
            echo json_encode($allRooms);
        }
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim AJAX Abruf der Räume: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Datenbankfehler']);
        exit;
    }
}


// Erfolgsmeldung aus Session holen
$successMessage = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$errorMessage = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
$reservationData = isset($_SESSION['reservation_data']) ? $_SESSION['reservation_data'] : null;

// Nachrichten aus Session löschen
unset($_SESSION['success_message'], $_SESSION['error_message']);

//Nutzer aus der Datenbank abrufen
$ma_name=[];
$room_pool=[];
$room_reservations = [];

if($db_conn){
	try {
	$stmt=$db_conn->query("SELECT id, nachname, vorname FROM ma_info ORDER BY nachname");
	$ma_name=$stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Alle Räume aus dem Raumpool abrufen
    $stmt=$db_conn->query("SELECT id, name FROM room_pool ORDER BY name");
    $room_pool=$stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Alle Raumreservierungen für die nächsten 14 Tage abrufen
    $stmt = $db_conn->prepare("
        SELECT r.id, r.datum, r.startZeit, r.endZeit, r.raum_id, r.benutzer_id, r.anlass, 
               m.nachname as benutzer_name, rp.name as raum_name
        FROM room_reservations r
        LEFT JOIN ma_info m ON r.benutzer_id = m.id
        LEFT JOIN room_pool rp ON r.raum_id = rp.id
        WHERE r.datum >= CURDATE() AND r.datum <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        ORDER BY r.datum, r.startZeit
    ");
    $stmt->execute();
    $room_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}catch (PDOException $e) {
	$db_error=true;
	error_log("Es ist ein Fehler beim Abruf der Mitarbeiterdaten aufgetreten" . $e->getMessage());
}
}

//POST Bearbeitung
if($_SERVER['REQUEST_METHOD']==='POST'){
	$datum = filter_input(INPUT_POST, 'datum', FILTER_SANITIZE_STRING);
    $startZeit = filter_input(INPUT_POST, 'startZeit', FILTER_SANITIZE_STRING);
    $endZeit = filter_input(INPUT_POST, 'endZeit', FILTER_SANITIZE_STRING);
    $benutzerId = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_NUMBER_INT);
    $anlass = filter_input(INPUT_POST, 'anlass', FILTER_SANITIZE_STRING);
    $anzahlPersonen = filter_input(INPUT_POST, 'anzahlPersonen', FILTER_SANITIZE_NUMBER_INT);
    $externeTeilnehmer = isset($_POST['externeTeilnehmer']) ? 1 : 0;
    $kaffeePersonen = filter_input(INPUT_POST, 'kaffeePersonen', FILTER_SANITIZE_NUMBER_INT) ?: 0;
    $teePersonen = filter_input(INPUT_POST, 'teePersonen', FILTER_SANITIZE_NUMBER_INT) ?: 0;
    $kaltgetraenkePersonen = filter_input(INPUT_POST, 'kaltgetraenkePersonen', FILTER_SANITIZE_NUMBER_INT) ?: 0;
    $leitsystemAnzeige = isset($_POST['leitsystemAnzeige']) ? 1 : 0;
    $bemerkung = filter_input(INPUT_POST, 'bemerkung', FILTER_SANITIZE_STRING);
    $raumId = filter_input(INPUT_POST, 'raum', FILTER_SANITIZE_NUMBER_INT);
	$errors = [];
	
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
	
    if (empty($benutzerId)) {
        $errors[] = "Bitte wählen Sie einen Namen aus.";
    }

    if (empty($raumId)) {
        $errors[] = "Bitte wählen Sie einen Raum aus.";
    }
    
    if (empty($anlass)) {
        $errors[] = "Bitte geben Sie einen Anlass an.";
    }
    
    if (empty($anzahlPersonen) || $anzahlPersonen <= 0) {
        $errors[] = "Bitte geben Sie die Anzahl der Personen an.";
    }
    
    // Wenn keine Fehler, dann Verfügbarkeit prüfen
    if (empty($errors) && $db_conn) {
        try {
            // Prüfen, ob Raum für den Zeitraum verfügbar ist
            $stmt = $db_conn->prepare("
                SELECT COUNT(*) FROM room_reservations 
                WHERE datum = :datum AND raum_id = :raum_id AND (
                    (startZeit <= :startZeit AND endZeit > :startZeit) OR
                    (startZeit < :endZeit AND endZeit >= :endZeit) OR
                    (startZeit >= :startZeit AND endZeit <= :endZeit)
                )
            ");
            
            $stmt->bindParam(':datum', $datum);
            $stmt->bindParam(':startZeit', $startZeit);
            $stmt->bindParam(':endZeit', $endZeit);
            $stmt->bindParam(':raum_id', $raumId);
            $stmt->execute();
            
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                // Raum ist bereits reserviert
                $errors[] = "Der ausgewählte Raum ist im gewählten Zeitraum leider nicht verfügbar.";
            } else {
                // Raum ist verfügbar, Reservierung eintragen
                // Generiere eine UUID für das Storno-Feld
                $storno = uniqid('', true);
                
                $stmt = $db_conn->prepare("
                    INSERT INTO room_reservations (
                        datum, startZeit, endZeit, benutzer_id, raum_id, anlass, 
                        anzahl_personen, externe_teilnehmer, kaffee_personen, 
                        tee_personen, kaltgetraenke_personen, leitsystem_anzeige, 
                        bemerkung, storno
                    )
                    VALUES (
                        :datum, :startZeit, :endZeit, :benutzerId, :raumId, :anlass, 
                        :anzahlPersonen, :externeTeilnehmer, :kaffeePersonen, 
                        :teePersonen, :kaltgetraenkePersonen, :leitsystemAnzeige, 
                        :bemerkung, :storno
                    )
                ");
                
                $stmt->bindParam(':datum', $datum);
                $stmt->bindParam(':startZeit', $startZeit);
                $stmt->bindParam(':endZeit', $endZeit);
                $stmt->bindParam(':benutzerId', $benutzerId);
                $stmt->bindParam(':raumId', $raumId);
                $stmt->bindParam(':anlass', $anlass);
                $stmt->bindParam(':anzahlPersonen', $anzahlPersonen);
                $stmt->bindParam(':externeTeilnehmer', $externeTeilnehmer);
                $stmt->bindParam(':kaffeePersonen', $kaffeePersonen);
                $stmt->bindParam(':teePersonen', $teePersonen);
                $stmt->bindParam(':kaltgetraenkePersonen', $kaltgetraenkePersonen);
                $stmt->bindParam(':leitsystemAnzeige', $leitsystemAnzeige);
                $stmt->bindParam(':bemerkung', $bemerkung);
                $stmt->bindParam(':storno', $storno);
                
                $stmt->execute();
                
                // Benutzerdetails für Erfolgsmeldung abrufen
                $stmt = $db_conn->prepare("SELECT nachname FROM ma_info WHERE id = :id");
                $stmt->bindParam(':id', $benutzerId);
                $stmt->execute();
                $benutzerName = $stmt->fetchColumn();
                
                // Raumdetails für Erfolgsmeldung abrufen
                $stmt = $db_conn->prepare("SELECT name FROM room_pool WHERE id = :id");
                $stmt->bindParam(':id', $raumId);
                $stmt->execute();
                $raumName = $stmt->fetchColumn();
                
                // Daten für Erfolgsmeldung vorbereiten
                $reservationData = [
                    'datum' => $datum,
                    'startZeit' => $startZeit,
                    'endZeit' => $endZeit,
                    'benutzerName' => $benutzerName,
                    'raumName' => $raumName,
                    'anlass' => $anlass,
                    'anzahlPersonen' => $anzahlPersonen,
                    'externeTeilnehmer' => $externeTeilnehmer,
                    'kaffeePersonen' => $kaffeePersonen,
                    'teePersonen' => $teePersonen,
                    'kaltgetraenkePersonen' => $kaltgetraenkePersonen,
                    'leitsystemAnzeige' => $leitsystemAnzeige,
                    'bemerkung' => $bemerkung
                ];
                
                $_SESSION['success_message'] = "Der Raum wurde erfolgreich reserviert.";
                $_SESSION['reservation_data'] = $reservationData;
                
                // Seite neu laden, um POST-Daten zu löschen
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        } catch (PDOException $e) {
            error_log("Fehler bei der Raumreservierung: " . $e->getMessage());
            $errors[] = "Es ist ein Datenbankfehler aufgetreten. Bitte versuchen Sie es später erneut.";
        }
    }
    
    // Wenn Fehler aufgetreten sind
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Hilfsfunktionen
function formatDate($date) {
    return date("d.m.Y", strtotime($date));
}

function formatTime($time) {
    return substr($time, 0, 5);
}

// Deutsche Wochentage für die Anzeige
function getDeutscherWochentag($dateObj) {
    $englishDayNumber = $dateObj->format('N'); // 1 (Montag) bis 7 (Sonntag)
    $deutscheWochentage = [
        1 => 'Mo',
        2 => 'Di',
        3 => 'Mi',
        4 => 'Do',
        5 => 'Fr',
        6 => 'Sa',
        7 => 'So'
    ];
    return $deutscheWochentage[$englishDayNumber];
}

// Daten für die Raumübersicht vorbereiten
$dateRange = [];
$currentDate = new DateTime();
$endDate = clone $currentDate;
$endDate->modify('+14 days');
$interval = new DateInterval('P1D');
$period = new DatePeriod($currentDate, $interval, $endDate);

// Kalenderdaten für die nächsten 14 Tage initialisieren
foreach ($period as $date) {
    $dateStr = $date->format('Y-m-d');
    $dateRange[] = $dateStr;
}

// Reservierungen nach Raum und Datum gruppieren
$reservationsByRoomDate = [];
foreach ($room_pool as $room) {
    foreach ($dateRange as $date) {
        $reservationsByRoomDate[$room['id']][$date] = [];
    }
}

// Reservierungen in die Struktur einfügen
foreach ($room_reservations as $reservation) {
    $raumId = $reservation['raum_id'];
    $dateStr = $reservation['datum'];
    
    if (isset($reservationsByRoomDate[$raumId]) && isset($reservationsByRoomDate[$raumId][$dateStr])) {
        $reservationsByRoomDate[$raumId][$dateStr][] = [
            'id' => $reservation['id'],
            'start' => $reservation['startZeit'],
            'end' => $reservation['endZeit'],
            'anlass' => $reservation['anlass'],
            'benutzer' => $reservation['benutzer_name']
        ];
    }
}

// Farbe für die Reservierungen
$reservationColor = '#4299e1'; // Blau
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kreis Kleve - Raumreservierung</title>
    <link rel="stylesheet" href="style.css">
    <style>
		header {
			position: sticky;
			top: 0;
			z-index: 1000; /* Damit er über anderen Elementen liegt */
		}
        .timeline-container {
            margin-top: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            overflow-x: auto;
        }
        
        .timeline-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 1rem;
        }
        
        .timeline-table th,
        .timeline-table td {
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            text-align: center;
            min-width: 120px;
        }
        
        .timeline-table th {
            background-color: #f9fafb;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        .timeline-table th:first-child {
            text-align: left;
            min-width: 150px;
        }
        
        .timeline-table td:first-child {
            text-align: left;
            font-weight: 500;
            background-color: #f9fafb;
            position: sticky;
            left: 0;
            z-index: 1;
        }
        
        .timeline-table .weekend {
            background-color: #f3f4f6;
        }
        
        .reservation-block {
            background-color: #4299e1;
            color: white;
            padding: 0.25rem;
            margin: 0.125rem 0;
            border-radius: 4px;
            font-size: 0.875rem;
            transition: transform 0.15s ease;
        }
        
        .reservation-block:hover {
            transform: scale(1.02);
        }
        
        .today {
            background-color: #fef3c7;
        }
        
        .section-heading {
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.5rem;
        }
        
        .catering-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .catering-group {
                grid-template-columns: 1fr;
            }
        }
        
        .leitsystem-info {
            margin-top: 0.5rem;
            margin-left: 1.75rem;
            font-size: 0.9rem;
            color: #4a5568;
        }
        
        .leitsystem-info strong {
            font-weight: 700;
        }
        
        .preview-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 0.5rem;
            margin-left: 1.75rem;
            font-size: 0.9rem;
        }
        
        .preview-box .preview-content {
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            color: #334155;
        }
		
		.admin-button {
            background-color: #00de46;
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background-color 0.2s;
            margin-left: auto;
        }

        .admin-button:hover {
            background-color: #13a842;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Kreis Kleve Reservierungssystem</h1>
            <nav>
                <ul>
                    <li><a href="index.php" class="active">Raumreservierung</a></li>
                    <li><a href="edv-ressourcen.php">EDV-Ressourcen-Reservierung</a></li>
                    <li><a href="dienstwagen.php">Dienstwagen-Reservierung</a></li>
					<li><a href="dienstfahrrad.php">Dienstfahrrad-Reservierung</a></li>
					<li><a href="rollup-praesentationsstand.php">Roll-Ups & Präsentationsstand</a></li>
					<li><a href="admin/index.php" class="admin-button">Adminbereich</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <h2>Raumreservierung</h2>
            <?php if ($successMessage): ?>
            <div id="success-message" class="message success">
                <div class="message-header">
                    <span class="icon">✓</span>
                    <div>
                        <h3>Reservierung erfolgreich!</h3>
                        <p><?php echo htmlspecialchars($successMessage); ?></p>
                    </div>
                </div>
				<?php if ($reservationData): ?>
                <div class="reservation-details">
                    <h4>Reservierungsdetails:</h4>
                    <div id="reservation-info">
					    <div class="detail-item">
                            <span class="label">Datum:</span>
                            <span><?php echo formatDate($reservationData['datum']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Zeit:</span>
                            <span><?php echo formatTime($reservationData['startZeit']) . ' - ' . formatTime($reservationData['endZeit']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Name:</span>
                            <span><?php echo htmlspecialchars($reservationData['benutzerName']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Raum:</span>
                            <span><?php echo htmlspecialchars($reservationData['raumName']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Anlass:</span>
                            <span><?php echo htmlspecialchars($reservationData['anlass']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Anzahl Personen:</span>
                            <span><?php echo htmlspecialchars($reservationData['anzahlPersonen']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Externe Teilnehmer:</span>
                            <span><?php echo $reservationData['externeTeilnehmer'] ? 'Ja' : 'Nein'; ?></span>
                        </div>
                        <?php if ($reservationData['kaffeePersonen'] > 0 || $reservationData['teePersonen'] > 0 || $reservationData['kaltgetraenkePersonen'] > 0): ?>
                        <div class="detail-item">
                            <span class="label">Bewirtung:</span>
                            <span>
                                <?php if ($reservationData['kaffeePersonen'] > 0): ?>
                                    Kaffee: <?php echo htmlspecialchars($reservationData['kaffeePersonen']); ?> Personen<br>
                                <?php endif; ?>
                                
                                <?php if ($reservationData['teePersonen'] > 0): ?>
                                    Tee: <?php echo htmlspecialchars($reservationData['teePersonen']); ?> Personen<br>
                                <?php endif; ?>
                                
                                <?php if ($reservationData['kaltgetraenkePersonen'] > 0): ?>
                                    Kalte Getränke: <?php echo htmlspecialchars($reservationData['kaltgetraenkePersonen']); ?> Personen
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="detail-item">
                            <span class="label">Anzeige im Leitsystem:</span>
                            <span><?php echo $reservationData['leitsystemAnzeige'] ? 'Ja' : 'Nein'; ?></span>
                        </div>
                        <?php if (!empty($reservationData['bemerkung'])): ?>
                        <div class="detail-item">
                            <span class="label">Bemerkung:</span>
                            <span><?php echo htmlspecialchars($reservationData['bemerkung']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <button onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>'" class="button">Neue Reservierung erstellen</button>
            </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
            <div id="error-message" class="message error">
                <span class="icon">!</span>
                <div class="message-content">
                    <h3>Fehler</h3>
                    <p id="error-text"><?php echo $errorMessage; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($db_error): ?>
            <div class="message error">
                <span class="icon">!</span>
                <div class="message-content">
                    <h3>Datenbankfehler</h3>
                    <p>Es konnte keine Verbindung zur Datenbank hergestellt werden. Bitte versuchen Sie es später erneut oder kontaktieren Sie den Administrator.</p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$successMessage): ?>
            <form id="reservierungForm" class="reservation-form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="datum">Datum*</label>
                        <input type="date" id="datum" name="datum" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
                    </div>

					<div class="form-group">
						<label for="name">Name*</label>
						<select id="name" name="name" required>
							<option value="">Bitte auswählen</option>
							<?php foreach ($ma_name as $user): ?>
								<option value="<?= htmlspecialchars($user['id']) ?>">
									<?= htmlspecialchars($user['nachname'] . ', ' . $user['vorname'] . ', ' . $user['id']) ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
                    
                    <div class="form-group">
                        <label for="startZeit">Startzeit*</label>
                        <input type="time" id="startZeit" name="startZeit" required>
                    </div>

                    <div class="form-group">
                        <label for="endZeit">Endzeit*</label>
                        <input type="time" id="endZeit" name="endZeit" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="button" id="verfuegbareRaeumeBtn" class="button secondary">Verfügbare Räume prüfen</button>
                </div>

                <div class="form-group">
                    <label for="raum">Raum*</label>
                    <select id="raum" name="raum" required disabled>
                        <option value="">Bitte erst verfügbare Räume prüfen</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="anlass">Anlass*</label>
                    <input type="text" id="anlass" name="anlass" required>
                </div>
                
                <div class="form-group">
                    <label for="anzahlPersonen">Anzahl der Personen*</label>
                    <input type="number" id="anzahlPersonen" name="anzahlPersonen" min="1" required>
                </div>

                <div class="form-group checkbox">
                    <input type="checkbox" id="externeTeilnehmer" name="externeTeilnehmer">
                    <label for="externeTeilnehmer">Externe Teilnehmer</label>
                </div>
                
                <h3 class="section-heading">Bewirtung</h3>
                <div class="catering-group">
                    <div class="form-group">
                        <label for="kaffeePersonen">Kaffee für wie viele Personen?</label>
                        <input type="number" id="kaffeePersonen" name="kaffeePersonen" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="teePersonen">Tee für wie viele Personen?</label>
                        <input type="number" id="teePersonen" name="teePersonen" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="kaltgetraenkePersonen">Kalte Getränke für wie viele Personen?</label>
                        <input type="number" id="kaltgetraenkePersonen" name="kaltgetraenkePersonen" min="0">
                    </div>
                </div>
                
                <h3 class="section-heading">Leitsystem</h3>
                <div class="form-group checkbox">
                    <label for="leitsystemAnzeige">Anzeige im Leitsystem</label>
					<input type="checkbox" id="leitsystemAnzeige" name="leitsystemAnzeige">
						<div class="leitsystem-info">
						Ja der Termin wird mit Zweck/Anlass auf den Displays auf den Fluren veröffentlicht.<strong>Bitte achten Sie auf die Einhaltung des Datenschutzes!</strong>
						</div>   
                </div>
                
                
                <div id="leitsystemPreview" style="display: none;">
                    <h4 style="margin-top: 1rem; margin-bottom: 0.5rem;">Vorschau Leitsystem:</h4>
                    <div class="preview-box">
                        <div class="preview-content" id="previewContent">
                            <!-- Leitsystem Preview wird hier dynamisch eingefügt -->
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="bemerkung">Bemerkung</label>
                    <textarea id="bemerkung" name="bemerkung" rows="3"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button">Reservierung abschicken</button>
                </div>
            </form>

            <!-- Besprechungsraum-Übersicht -->
            <div class="timeline-container">
                <h3>Besprechungsraum-Übersicht</h3>
                <div class="timeline">
                    <table class="timeline-table">
                        <thead>
                            <tr>
                                <th>Raum</th>
                                <?php 
                                foreach ($dateRange as $date): 
                                    $dateObj = new DateTime($date);
                                    $isWeekend = ($dateObj->format('N') >= 6);
                                    $isToday = ($date === date('Y-m-d'));
                                    $class = $isWeekend ? 'weekend' : '';
                                    $class .= $isToday ? ' today' : '';
                                ?>
                                <th class="<?= $class ?>"><?= $dateObj->format('d.m.') ?><br><?= getDeutscherWochentag($dateObj) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($room_pool as $room): ?>
                            <tr>
                                <td><?= htmlspecialchars($room['name']) ?></td>
                                <?php 
                                foreach ($dateRange as $date): 
                                    $dateObj = new DateTime($date);
                                    $isWeekend = ($dateObj->format('N') >= 6);
                                    $isToday = ($date === date('Y-m-d'));
                                    $class = $isWeekend ? 'weekend' : '';
                                    $class .= $isToday ? ' today' : '';
                                ?>
                                <td class="<?= $class ?>">
                                    <?php
                                    if (isset($reservationsByRoomDate[$room['id']][$date])) {
                                        foreach ($reservationsByRoomDate[$room['id']][$date] as $reservation) {
                                            // Modified: Removed tooltip structure, keeping only the reservation block
                                            echo '<div class="reservation-block" style="background-color: ' . $reservationColor . '">';
                                            echo htmlspecialchars(formatTime($reservation['start']) . '-' . 
                                                 formatTime($reservation['end']));
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
			<?php endif; ?>
			
			<!-- Ansprechpartner-Block -->
			<div class="ansprechpartner-block" style="text-align: center; margin-top:40px; margin-left: 100px; padding:20px; border:1px solid #ccc; border-radius:8px; background:#f9f9f9; max-width:600px;">
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
    
    <script>
    document.getElementById('verfuegbareRaeumeBtn').addEventListener('click', function() {
        const datum = document.getElementById('datum').value;
        const startZeit = document.getElementById('startZeit').value;
        const endZeit = document.getElementById('endZeit').value;
        const raumSelect = document.getElementById('raum');
        
        // Validierung der Eingaben
        if (!datum) {
            alert("Bitte wählen Sie ein Datum.");
            return;
        }
        
        if (!startZeit) {
            alert("Bitte geben Sie eine Startzeit an.");
            return;
        }
        
        if (!endZeit) {
            alert("Bitte geben Sie eine Endzeit an.");
            return;
        }
        
        if (startZeit >= endZeit) {
            alert("Die Startzeit muss vor der Endzeit liegen.");
            return;
        }
        
        // Räume laden
        raumSelect.innerHTML = '<option value="">Lädt verfügbare Räume...</option>';
        raumSelect.disabled = true;
        
        fetch(`${window.location.pathname}?action=check_rooms&datum=${encodeURIComponent(datum)}&startzeit=${encodeURIComponent(startZeit)}&endzeit=${encodeURIComponent(endZeit)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Netzwerkfehler beim Laden der Räume');
                }
                return response.json();
            })
            .then(data => {
                raumSelect.innerHTML = '';
                
                if (data.length === 0) {
                    raumSelect.innerHTML = '<option value="">Keine Räume verfügbar</option>';
                } else {
                    data.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = room.name;
                        raumSelect.appendChild(option);
                    });
                    
                    raumSelect.disabled = false;
                }
            })
            .catch(error => {
                console.error('Fehler:', error);
                raumSelect.innerHTML = '<option value="">Fehler beim Laden der Räume</option>';
            });
    });
    
    // Leitsystem Vorschau aktualisieren
    function updateLeitsystemPreview() {
        const leitsystemAnzeige = document.getElementById('leitsystemAnzeige').checked;
        const previewDiv = document.getElementById('leitsystemPreview');
        const previewContent = document.getElementById('previewContent');
        
        if (leitsystemAnzeige) {
            // Daten aus dem Formular holen
            const startZeit = document.getElementById('startZeit').value;
            const endZeit = document.getElementById('endZeit').value;
            const anlass = document.getElementById('anlass').value;
            const raumSelect = document.getElementById('raum');
            const raumName = raumSelect.options[raumSelect.selectedIndex]?.text || '';
            
            // Formatierte Zeiten
            const formatZeit = (zeit) => zeit ? zeit.substring(0, 5) : '';
            
            // Preview anzeigen
            if (startZeit && endZeit && anlass && raumName) {
                previewContent.textContent = `${formatZeit(startZeit)} - ${formatZeit(endZeit)} - ${anlass} - ${raumName}`;
                previewDiv.style.display = 'block';
            } else {
                previewContent.textContent = 'Bitte füllen Sie die Felder Startzeit, Endzeit, Anlass und Raum aus.';
                previewDiv.style.display = 'block';
            }
        } else {
            previewDiv.style.display = 'none';
        }
    }
    
    // Event-Listener für Formularänderungen
    document.getElementById('leitsystemAnzeige').addEventListener('change', updateLeitsystemPreview);
    document.getElementById('startZeit').addEventListener('input', function() {
        if (document.getElementById('leitsystemAnzeige').checked) updateLeitsystemPreview();
    });
    document.getElementById('endZeit').addEventListener('input', function() {
        if (document.getElementById('leitsystemAnzeige').checked) updateLeitsystemPreview();
    });
    document.getElementById('anlass').addEventListener('input', function() {
        if (document.getElementById('leitsystemAnzeige').checked) updateLeitsystemPreview();
    });
    document.getElementById('raum').addEventListener('change', function() {
        if (document.getElementById('leitsystemAnzeige').checked) updateLeitsystemPreview();
    });
    </script>
</body>
</html>