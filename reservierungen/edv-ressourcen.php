<?php

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

// AJAX-Anfrage für Ressourcen nach Typ
if (isset($_GET['action']) && $_GET['action'] === 'load_resources' && isset($_GET['type_id']) && $db_conn) {
    $typ_id = $_GET['type_id'];
    
    try {
        $stmt = $db_conn->prepare("SELECT id, name FROM edv_ressourcen WHERE typ_id = :typ_id");
        $stmt->bindParam(':typ_id', $typ_id);
        $stmt->execute();
        $ressourcen = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
        header('Content-Type: application/json');
        echo json_encode($ressourcen);
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim AJAX Abruf: " . $e->getMessage());
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
$ressourcen_typ=[];
$edv_ressourcen = [];
$reservations = [];

if($db_conn){
	try {
	$stmt=$db_conn->query("SELECT id, nachname, vorname FROM ma_info ORDER BY nachname");
	$ma_name=$stmt->fetchAll(PDO::FETCH_ASSOC);
	
	// Ressourcentypen aus der korrekten Tabelle abrufen
	$stmt=$db_conn->query("SELECT id, name FROM edv_ressourcen_typ ORDER BY name");
	$ressourcen_typ=$stmt->fetchALL(PDO::FETCH_ASSOC);
    
    // Alle EDV-Ressourcen mit Typinformationen abrufen
    $stmt=$db_conn->query("SELECT r.id, r.name, r.typ_id, t.name as typ_name 
                           FROM edv_ressourcen r
                           JOIN edv_ressourcen_typ t ON r.typ_id = t.id
                           ORDER BY t.name, r.name");
    $edv_ressourcen=$stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Alle Reservierungen für die nächsten 14 Tage abrufen
    $stmt = $db_conn->prepare("
        SELECT r.id, r.datum, r.startZeit, r.endZeit, r.ressource
        FROM edv_reservations r
        WHERE r.datum >= CURDATE() AND r.datum <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        AND r.storno = '' 
        ORDER BY r.datum, r.startZeit
    ");
    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
}catch (PDOException $e) {
	$db_error=true;
	error_log("Es ist ein Fehler beim Abruf der Ressourcendaten aufgetreten" . $e->getMessage());
}
}

//POST Bearbeitung
if($_SERVER['REQUEST_METHOD']==='POST'){
	$datum = filter_input(INPUT_POST, 'datum', FILTER_SANITIZE_STRING);
    $startZeit = filter_input(INPUT_POST, 'startZeit', FILTER_SANITIZE_STRING);
    $endZeit = filter_input(INPUT_POST, 'endZeit', FILTER_SANITIZE_STRING);
    $benutzerId = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_NUMBER_INT);
	$ressourcentyp = filter_input(INPUT_POST, 'ressourcentyp', FILTER_SANITIZE_NUMBER_INT);
	$ressource = filter_input(INPUT_POST, 'ressource', FILTER_SANITIZE_NUMBER_INT);
    $zweck = filter_input(INPUT_POST, 'zweck', FILTER_SANITIZE_STRING);
    $bemerkung = filter_input(INPUT_POST, 'bemerkung', FILTER_SANITIZE_STRING);
	$errors = [];
	
	if (empty($datum)) $errors[] = "Bitte geben Sie ein Datum an.";
    if (empty($startZeit)) $errors[] = "Bitte geben Sie eine Startzeit an.";
    if (empty($endZeit)) $errors[] = "Bitte geben Sie eine Endzeit an.";
    if ($startZeit >= $endZeit) $errors[] = "Startzeit muss vor Endzeit liegen.";
	
    if (empty($benutzerId)) $errors[] = "Bitte wählen Sie einen Namen.";
    if (empty($ressourcentyp)) $errors[] = "Bitte wählen Sie einen Ressourcentyp.";
    if (empty($ressource)) $errors[] = "Bitte wählen Sie eine Ressource.";
    if (empty($zweck)) $errors[] = "Bitte geben Sie einen Zweck an.";
    
    // Wenn keine Fehler, dann Verfügbarkeit prüfen
    if (empty($errors) && $db_conn) {
        try {
            // Prüfen, ob EDV-Ressource für den Zeitraum verfügbar ist
            $stmt = $db_conn->prepare("
                SELECT COUNT(*) FROM edv_reservations 
                WHERE datum = :datum AND (
                    (startZeit <= :startZeit AND endZeit > :startZeit) OR
                    (startZeit < :endZeit AND endZeit >= :endZeit) OR
                    (startZeit >= :startZeit AND endZeit <= :endZeit)
                ) AND ressource = :ressource AND storno = ''
            ");
            
            $stmt->bindParam(':datum', $datum);
            $stmt->bindParam(':startZeit', $startZeit);
            $stmt->bindParam(':endZeit', $endZeit);
			$stmt->bindParam(':ressource', $ressource);
            $stmt->execute();
            
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                // EDV-Ressource ist bereits reserviert
                $errors[] = "Die EDV-Ressource ist im gewählten Zeitraum leider nicht verfügbar.";
            } else {
                // Unique storno ID generieren
                $storno = uniqid();
                
                // EDV-Ressource ist verfügbar, Reservierung eintragen
                $stmt = $db_conn->prepare("
                    INSERT INTO edv_reservations (datum, startZeit, endZeit, benutzer_id, ressource, zweck, bemerkung, storno)
                    VALUES (:datum, :startZeit, :endZeit, :benutzerId, :ressource, :zweck, :bemerkung, :storno)
                ");
                
                $stmt->bindParam(':datum', $datum);
                $stmt->bindParam(':startZeit', $startZeit);
                $stmt->bindParam(':endZeit', $endZeit);
                $stmt->bindParam(':benutzerId', $benutzerId);
				$stmt->bindParam(':ressource', $ressource);
                $stmt->bindParam(':zweck', $zweck);
                $stmt->bindParam(':bemerkung', $bemerkung);
                $stmt->bindParam(':storno', $storno);
                
                $stmt->execute();
                
                // Benutzerdetails für Erfolgsmeldung abrufen
                $stmt = $db_conn->prepare("SELECT nachname, vorname FROM ma_info WHERE id = :id");
                $stmt->bindParam(':id', $benutzerId);
                $stmt->execute();
                $benutzer = $stmt->fetch(PDO::FETCH_ASSOC);
                $benutzerName = $benutzer['nachname'] . ', ' . $benutzer['vorname'];
                
                // Ressourcenname für Erfolgsmeldung abrufen
                $stmt = $db_conn->prepare("SELECT name FROM edv_ressourcen WHERE id = :id");
                $stmt->bindParam(':id', $ressource);
                $stmt->execute();
                $ressourceName = $stmt->fetchColumn();
                
                // Ressourcentyp-Name für Erfolgsmeldung abrufen
                $stmt = $db_conn->prepare("SELECT name FROM edv_ressourcen_typ WHERE id = :id");
                $stmt->bindParam(':id', $ressourcentyp);
                $stmt->execute();
                $ressourcentypName = $stmt->fetchColumn();
                
                // Daten für Erfolgsmeldung vorbereiten
                $reservationData = [
                    'datum' => $datum,
                    'startZeit' => $startZeit,
                    'endZeit' => $endZeit,
                    'benutzerName' => $benutzerName,
                    'ressourcenTyp' => $ressourcentypName,
                    'ressource' => $ressourceName,
                    'zweck' => $zweck,
                    'bemerkung' => $bemerkung
                ];
                
                $_SESSION['success_message'] = "Die EDV-Ressource wurde erfolgreich reserviert.";
                $_SESSION['reservation_data'] = $reservationData;
                
                // Seite neu laden, um POST-Daten zu löschen
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        } catch (PDOException $e) {
            error_log("Fehler bei der EDV-Reservierung: " . $e->getMessage());
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

// Reservierungen nach Ressource und Datum gruppieren für die Übersichtstabelle
$reservationsByResourceDate = [];
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
    
    foreach ($edv_ressourcen as $resource) {
        $reservationsByResourceDate[$resource['id']][$dateStr] = [];
    }
}

// Ressourcen nach Typ gruppieren
$resourcesByType = [];
foreach ($edv_ressourcen as $resource) {
    if (!isset($resourcesByType[$resource['typ_id']])) {
        $resourcesByType[$resource['typ_id']] = [
            'name' => $resource['typ_name'],
            'resources' => []
        ];
    }
    $resourcesByType[$resource['typ_id']]['resources'][] = $resource;
}

// Reservierungen in die Struktur einfügen
foreach ($reservations as $reservation) {
    $resourceId = $reservation['ressource'];
    $dateStr = $reservation['datum'];
    
    if (isset($reservationsByResourceDate[$resourceId][$dateStr])) {
        $reservationsByResourceDate[$resourceId][$dateStr][] = [
            'id' => $reservation['id'],
            'start' => $reservation['startZeit'],
            'end' => $reservation['endZeit']
        ];
    }
}

// Farben für die Ressourcentypen
$typeColors = [
    '1' => '#4299e1', // Blau
    '2' => '#48bb78', // Grün
    '3' => '#ed8936', // Orange
    '4' => '#9f7aea', // Lila
    '5' => '#f56565', // Rot
    '6' => '#667eea', // Indigo
    '7' => '#f6ad55', // Hell-Orange
    '8' => '#68d391', // Hell-Grün
];
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kreis Kleve - EDV-Reservierung</title>
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
            color: white;
            padding: 0.25rem;
            margin: 0.125rem 0;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        
        .today {
            background-color: #fef3c7;
        }
        
        .resource-type-header {
            font-weight: bold;
            background-color: #e5e7eb;
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
                    <li><a href="index.php">Raumreservierung</a></li>
                    <li><a href="edv-ressourcen.php" class="active">EDV-Ressourcen-Reservierung</a></li>
                    <li><a href="dienstwagen.php">Dienstwagen-Reservierung</a></li>
					<li><a href="dienstfahrrad.php">Dienstfahrrad-Reservierung</a></li>
					<li><a href="rollup-praesentationsstand.php">Roll-Ups & Präsentationsstand</a></li>
					<li><a href="admin/index.php" class="admin-button">Adminbereich</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <h2>EDV-Ressourcen-Reservierung</h2>
            <?php if ($successMessage): ?>
            <div id="success-message" class="message success";">
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
							<span class="label">Ressourcentyp:</span>
							<span><?php echo htmlspecialchars($reservationData['ressourcenTyp']); ?></span>
						</div>
						<div class="detail-item">
							<span class="label">Ressource:</span>
							<span><?php echo htmlspecialchars($reservationData['ressource']); ?></span>
						</div>
                        <div class="detail-item">
                            <span class="label">Zweck:</span>
                            <span><?php echo htmlspecialchars($reservationData['zweck']); ?></span>
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
                    <label for="ressourcentyp">Ressourcentyp*</label>
                    <select id="ressourcentyp" name="ressourcentyp" required>
                        <option value="">Bitte auswählen</option>
                        <?php foreach ($ressourcen_typ as $typ): ?>
							<option value="<?= $typ['id'] ?>"><?= htmlspecialchars($typ['name']) ?></option>
						<?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <button type="button" id="suchenButton" class="button secondary">Verfügbare Ressourcen suchen</button>
                </div>

                <div class="form-group">
                    <label for="ressource">Ressource*</label>
                    <select id="ressource" name="ressource" required disabled>
                        <option value="">Bitte zuerst Ressourcentyp wählen und suchen</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="zweck">Zweck*</label>
                    <input type="text" id="zweck" name="zweck" required>
                </div>

                <div class="form-group">
                    <label for="bemerkung">Bemerkung</label>
                    <textarea id="bemerkung" name="bemerkung" rows="3"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button">Reservierung abschicken</button>
                </div>
            </form>
			
            <!-- EDV-Ressourcen-Übersicht -->
            <div class="timeline-container">
                <h3>EDV-Ressourcen-Übersicht</h3>
                <div class="timeline">
                    <table class="timeline-table">
                        <thead>
                            <tr>
                                <th>Ressource</th>
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
                            <?php foreach ($resourcesByType as $typeId => $typeData): ?>
                                <tr class="resource-type-header">
                                    <td colspan="<?= count($dateRange) + 1 ?>">
                                        <?= htmlspecialchars($typeData['name']) ?>
                                    </td>
                                </tr>
                                <?php foreach ($typeData['resources'] as $resource): ?>
                                <tr>
                                    <td><?= htmlspecialchars($resource['name']) ?></td>
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
                                        if (isset($reservationsByResourceDate[$resource['id']][$date])) {
                                            foreach ($reservationsByResourceDate[$resource['id']][$date] as $reservation) {
                                                echo '<div class="reservation-block" style="background-color: ' . 
                                                     ($typeColors[$typeId] ?? '#4299e1') . '">';
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
    document.getElementById('suchenButton').addEventListener('click', function() {
        const typeId = document.getElementById('ressourcentyp').value;
        const ressourceSelect = document.getElementById('ressource');
        
        // Zurücksetzen des Dropdowns
        ressourceSelect.innerHTML = '';
        ressourceSelect.disabled = true;

        if (!typeId) {
            alert("Bitte wählen Sie einen Ressourcentyp.");
            return;
        }

        // Datumsangaben für die Verfügbarkeitsprüfung
        const datum = document.getElementById('datum').value;
        const startZeit = document.getElementById('startZeit').value;
        const endZeit = document.getElementById('endZeit').value;

        if (!datum || !startZeit || !endZeit) {
            alert("Bitte geben Sie Datum, Startzeit und Endzeit an.");
            return;
        }

        if (startZeit >= endZeit) {
            alert("Die Startzeit muss vor der Endzeit liegen.");
            return;
        }

        // Ressourcen vom Server abrufen
        fetch(`${window.location.pathname}?action=load_resources&type_id=${encodeURIComponent(typeId)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Netzwerkantwort war nicht ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.length === 0) {
                    ressourceSelect.innerHTML = '<option value="">Keine Ressourcen verfügbar</option>';
                    return;
                }

                // Options zum Dropdown hinzufügen
                ressourceSelect.innerHTML = '<option value="">Bitte Ressource auswählen</option>';
                data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.name;
                    ressourceSelect.appendChild(option);
                });

                // Dropdown aktivieren
                ressourceSelect.disabled = false;
            })
            .catch(error => {
                console.error('Fehler beim Abrufen der Ressourcen:', error);
                alert('Es ist ein Fehler beim Laden der Ressourcen aufgetreten. Bitte versuchen Sie es später erneut.');
            });
    });
    </script>
</body>
</html>