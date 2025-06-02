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

$token = $_GET['token'] ?? null;
$confirm = $_GET['confirm'] ?? false;

$reservierungTabellen = [
    'room_reservations' => [
        'name' => 'Raum',
        'query' => "SELECT r.*, rp.name as resource_name, m.vorname, m.nachname, m.email 
                   FROM room_reservations r 
                   JOIN room_pool rp ON r.raum_id = rp.id 
                   JOIN ma_info m ON r.benutzer_id = m.id 
                   WHERE r.storno = ?"
    ],
    'edv_reservations' => [
        'name' => 'EDV',
        'query' => "SELECT r.*, e.name as resource_name, et.name as typ_name, m.vorname, m.nachname, m.email 
                   FROM edv_reservations r 
                   JOIN edv_ressourcen e ON r.ressource = e.id 
                   JOIN edv_ressourcen_typ et ON e.typ_id = et.id 
                   JOIN ma_info m ON r.benutzer_id = m.id 
                   WHERE r.storno = ?"
    ],
    'companycar_reservations' => [
        'name' => 'Dienstwagen',
        'query' => "SELECT r.*, c.name as resource_name, c.kennzeichen, c.reichweite, m.vorname, m.nachname, m.email 
                   FROM companycar_reservations r 
                   JOIN companycar_pool c ON r.dienstwagen = c.id 
                   JOIN ma_info m ON r.benutzer_id = m.id 
                   WHERE r.storno = ?"
    ],
    'companybicycle_reservations' => [
        'name' => 'Dienstfahrrad',
        'query' => "SELECT r.*, b.name as resource_name, b.reichweite, m.vorname, m.nachname, m.email 
                   FROM companybicycle_reservations r 
                   JOIN companybicycle_pool b ON r.dienstfahrrad = b.id 
                   JOIN ma_info m ON r.benutzer_id = m.id 
                   WHERE r.storno = ?"
    ],
    'rollup_reservations' => [
        'name' => 'Roll-Up & Präsentationsstand',
        'query' => "SELECT r.*, rp.name as resource_name, m.vorname, m.nachname, m.email 
                   FROM rollup_reservations r 
                   JOIN rollup_pool rp ON r.rollup_id = rp.id 
                   JOIN ma_info m ON r.benutzer_id = m.id 
                   WHERE r.storno = ?"
    ]
];

$typName = null;
$reservierungsInfo = null;
$reservierungsDetails = null;

if ($db_conn && $token) {
    foreach ($reservierungTabellen as $tabelle => $info) {
        $stmt = $db_conn->prepare($info['query']);
        $stmt->execute([$token]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($details) {
            $typName = $info['name'];
            $reservierungsDetails = $details;

            if ($confirm) {
                $delete = $db_conn->prepare("DELETE FROM $tabelle WHERE storno = ?");
                $delete->execute([$token]);
                break;
            }
        }
    }
}

function formatDateTime($date, $time) {
    return date('d.m.Y', strtotime($date)) . ' ' . substr($time, 0, 5) . ' Uhr';
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kreis Kleve - Reservierung stornieren</title>
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

        .confirmation-box {
            background-color: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 2rem auto;
            max-width: 600px;
        }

        .confirmation-box h3 {
            color: #2d3748;
            margin-bottom: 1rem;
        }

        .confirmation-box .details {
            margin: 1rem 0;
            padding: 1rem;
            background-color: #f7fafc;
            border-radius: 4px;
        }

        .confirmation-box .details p {
            margin: 0.5rem 0;
            color: #4a5568;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .button-confirm {
            background-color: #e53e3e;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .button-confirm:hover {
            background-color: #c53030;
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

        main {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
        }

        main h2 {
            text-align: center;
            width: 100%;
            margin-bottom: 2rem;
        }

        .ansprechpartner-block {
            margin: 40px auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background: #f9f9f9;
            max-width: 600px;
            width: 100%;
        }

        .message {
            width: 100%;
            max-width: 600px;
            margin: 0 auto 2rem;
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
            <h2>Reservierung stornieren</h2>
            
            <?php if ($db_error): ?>
            <div class="message error">
                <span class="icon">!</span>
                <div class="message-content">
                    <h3>Datenbankfehler</h3>
                    <p>Es konnte keine Verbindung zur Datenbank hergestellt werden. Bitte versuchen Sie es später erneut oder kontaktieren Sie den Administrator.</p>
                </div>
            </div>
            <?php elseif (!$token): ?>
            <div class="message error">
                <span class="icon">!</span>
                <div class="message-content">
                    <h3>Fehler</h3>
                    <p>Kein Storno-Token angegeben. Bitte überprüfen Sie den Link zum Stornieren.</p>
                </div>
            </div>
            <?php elseif (!$reservierungsDetails): ?>
            <div class="message error">
                <span class="icon">!</span>
                <div class="message-content">
                    <h3>Fehler</h3>
                    <p>Es wurde keine Reservierung zum angegebenen Storno-Token gefunden.</p>
                </div>
            </div>
            <?php elseif ($confirm): ?>
            <div class="message success">
                <div class="message-header">
                    <span class="icon">✓</span>
                    <div>
                        <h3>Reservierung erfolgreich storniert!</h3>
                        <p>Die Reservierung vom Typ <b><?php echo htmlspecialchars($typName); ?></b> wurde erfolgreich storniert.</p>
                    </div>
                </div>
                <button onclick="window.location.href='index.php'" class="button">Zurück zur Übersicht</button>
            </div>
            <?php else: ?>
            <div class="confirmation-box">
                <h3>Möchten Sie diese Reservierung wirklich stornieren?</h3>
                <div class="details">
                    <p><strong>Reservierungstyp:</strong> <?php echo htmlspecialchars($typName); ?></p>
                    <p><strong>Ressource:</strong> <?php echo htmlspecialchars($reservierungsDetails['resource_name']); ?></p>
                    <p><strong>Datum:</strong> <?php echo formatDateTime($reservierungsDetails['datum'], $reservierungsDetails['startZeit']); ?> - <?php echo substr($reservierungsDetails['endZeit'], 0, 5); ?> Uhr</p>
                    <p><strong>Benutzer:</strong> <?php echo htmlspecialchars($reservierungsDetails['vorname'] . ' ' . $reservierungsDetails['nachname']); ?></p>
                    
                    <?php if (isset($reservierungsDetails['kennzeichen'])): ?>
                    <p><strong>Kennzeichen:</strong> <?php echo htmlspecialchars($reservierungsDetails['kennzeichen']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (isset($reservierungsDetails['reichweite'])): ?>
                    <p><strong>Reichweite:</strong> <?php echo htmlspecialchars($reservierungsDetails['reichweite']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (isset($reservierungsDetails['typ_name'])): ?>
                    <p><strong>Gerätetyp:</strong> <?php echo htmlspecialchars($reservierungsDetails['typ_name']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (isset($reservierungsDetails['zweck'])): ?>
                    <p><strong>Zweck:</strong> <?php echo htmlspecialchars($reservierungsDetails['zweck']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (isset($reservierungsDetails['ziel'])): ?>
                    <p><strong>Ziel:</strong> <?php echo htmlspecialchars($reservierungsDetails['ziel']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (isset($reservierungsDetails['anlass'])): ?>
                    <p><strong>Anlass:</strong> <?php echo htmlspecialchars($reservierungsDetails['anlass']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($reservierungsDetails['bemerkung'])): ?>
                    <p><strong>Bemerkung:</strong> <?php echo htmlspecialchars($reservierungsDetails['bemerkung']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="button-group">
                    <a href="?token=<?php echo urlencode($token); ?>&confirm=1" class="button-confirm">Ja, Reservierung stornieren</a>
                    <a href="index.php" class="button-cancel">Nein, zurück zur Übersicht</a>
                </div>
            </div>
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