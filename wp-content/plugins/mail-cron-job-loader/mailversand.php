<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Load config
$config = require __DIR__ . '/config_mail.php';

try {
    $db = new PDO("mysql:host=localhost;dbname=kleve", "admin", "iris");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("SELECT * FROM mail_queue WHERE send = 0");
    $stmt->execute();
    $mails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[Mail Service] Database connection established");

    foreach ($mails as $mail) {
        $typ = (int)$mail['reservierungsTyp'];
        $mailTyp = $mail['mailTyp'];
        $resId = null;
        $benutzerId = (int)$mail['benutzer_id'];

        // Determine which ID to use
        foreach (['raum_id', 'edv_id', 'dienstwagen_id', 'dienstfahrrad_id', 'rollup_id'] as $feld) {
            error_log("Feld das gerade geprüft wird: " . $feld . " inhalt: " . $mail[$feld]);
            if (!empty($mail[$feld])) {
                $resId = (int)$mail[$feld];
                error_log("[Mail Service] Found reservation ID: $resId for type: $feld");
                break;
            }
        }

        if (!$resId) {
            error_log("[Mail Service] No reservation ID found, skipping");
            continue;
        }

        // Initialize variables
        $datum = "";
        $startZeit = "";
        $endZeit = "";
        $additionalInfo = "";
        $betreff = "";
        $bookingExists = false;

        // Prepare appropriate query based on reservation type
        switch ($typ) {
            case 0: // Room
                $res = $db->prepare("SELECT r.datum, r.startZeit, r.endZeit, r.anzahl_personen, r.anlass, p.name AS raumname, r.storno
                                   FROM room_reservations r
                                   JOIN room_pool p ON r.raum_id = p.id
                                   WHERE r.id = ?");
                break;
            case 1: // EDV
                $res = $db->prepare("SELECT e.datum, e.startZeit, e.endZeit, e.zweck, r.name AS ressourcename, t.name AS typname, e.storno
                                   FROM edv_reservations e
                                   JOIN edv_ressourcen r ON e.ressource = r.id
                                   JOIN edv_ressourcen_typ t ON r.typ_id = t.id
                                   WHERE e.id = ?");
                break;
            case 2: // Company Car
                $res = $db->prepare("SELECT c.datum, c.startZeit, c.endZeit, c.ziel, p.kennzeichen, p.reichweite, p.name AS wagenname, c.storno
                                   FROM companycar_reservations c
                                   JOIN companycar_pool p ON c.dienstwagen = p.id
                                   WHERE c.id = ?");
                break;
            case 3: // Bicycle
                $res = $db->prepare("SELECT d.datum, d.startZeit, d.endZeit, d.zweck, p.name AS fahrradname, p.reichweite, d.storno
                                   FROM companybicycle_reservations d
                                   JOIN companybicycle_pool p ON d.dienstfahrrad = p.id
                                   WHERE d.id = ?");
                break;
            case 4: // Roll-up
                $res = $db->prepare("SELECT r.datum, r.startZeit, r.endZeit, r.zweck, p.name AS rollupname, r.storno
                                   FROM rollup_reservations r
                                   JOIN rollup_pool p ON r.rollup_id = p.id
                                   WHERE r.id = ?");
                break;
            default:
                error_log("[Mail Service] Unknown reservation type: $typ");
                continue 2;
        }

        $res->execute([$resId]);
        $data = $res->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $bookingExists = true;
            $datum = date('d.m.Y', strtotime($data['datum']));
            $startZeit = substr($data['startZeit'], 0, 5);
            $endZeit = substr($data['endZeit'], 0, 5);
            $stornoToken = $data['storno'];

            // Build type-specific information
            switch ($typ) {
                case 0:
                    $additionalInfo = "Raum: " . $data['raumname'] . "\n" .
                                    "Anlass: " . $data['anlass'] . "\n" .
                                    "Anzahl Personen: " . $data['anzahl_personen'];
                    break;
                case 1:
                    $additionalInfo = "EDV-Ressource: " . $data['ressourcename'] . "\n" .
                                    "Typ: " . $data['typname'] . "\n" .
                                    "Zweck: " . $data['zweck'];
                    break;
                case 2:
                    $additionalInfo = "Fahrzeug: " . $data['wagenname'] . "\n" .
                                    "Kennzeichen: " . $data['kennzeichen'] . "\n" .
                                    "Reichweite: " . $data['reichweite'] . "\n" .
                                    "Ziel: " . $data['ziel'];
                    break;
                case 3:
                    $additionalInfo = "Fahrrad: " . $data['fahrradname'] . "\n" .
                                    "Reichweite: " . $data['reichweite'] . "\n" .
                                    "Zweck: " . $data['zweck'];
                    break;
                case 4:
                    $additionalInfo = "Roll-Up: " . $data['rollupname'] . "\n" .
                                    "Zweck: " . $data['zweck'];
                    break;
            }
        }

        // Get user email
        $stmt2 = $db->prepare("SELECT email, vorname, nachname FROM ma_info WHERE id = ?");
        $stmt2->execute([$benutzerId]);
        $userData = $stmt2->fetch(PDO::FETCH_ASSOC);

        if (!$userData || !$userData['email']) {
            error_log("[Mail Service] No email found for user ID: $benutzerId");
            continue;
        }

        // Build email content
        $body = "Sehr geehrte(r) " . $userData['vorname'] . " " . $userData['nachname'] . ",\n\n";

        if ($mailTyp === 'D' || !$bookingExists) {
            $betreff = "Reservierung storniert - Kreis Kleve";
            $body .= "Ihre Reservierung wurde erfolgreich storniert.\n\n";
            $body .= "Datum: $datum\nUhrzeit: $startZeit - $endZeit\n$additionalInfo";
        } elseif ($mailTyp === 'U') {
            $betreff = "Reservierung aktualisiert - Kreis Kleve";
            $body .= "Ihre Reservierung wurde erfolgreich aktualisiert.\n\n";
            $body .= "Datum: $datum\nUhrzeit: $startZeit - $endZeit\n\n$additionalInfo\n\n";
            $body .= "Sie können Ihre Reservierung über folgenden Link ändern (zum ändern der Ressource/des Raumes muss die Buchung stoniert werden und neu buchen):\n";
            $body .= "http://localhost/kreiskleveprojektvinf23/reservierungen/change.php?token=" . $stornoToken;
            $body .= "\n\n";
            $body .= "Sie können Ihre Reservierung über folgenden Link stornieren:\n";
            $body .= "http://localhost/kreiskleveprojektvinf23/storno.php?token=" . $stornoToken;
        } elseif ($mailTyp === 'I') {
            $betreff = "Reservierungsbestätigung - Kreis Kleve";
            $body .= "Ihre Reservierung wurde erfolgreich erstellt.\n\n";
            $body .= "Datum: $datum\nUhrzeit: $startZeit - $endZeit\n\n$additionalInfo\n\n";
            $body .= "Sie können Ihre Reservierung über folgenden Link ändern (zum ändern der Ressource/des Raumes muss die Buchung stoniert werden und neu buchen):\n";
            $body .= "http://localhost/kreiskleveprojektvinf23/reservierungen/change.php?token=" . $stornoToken;
            $body .= "\n\n";
            $body .= "Sie können Ihre Reservierung über folgenden Link stornieren:\n";
            $body .= "http://localhost/kreiskleveprojektvinf23/reservierungen/storno.php?token=" . $stornoToken;
        }

        $body .= "\n\nMit freundlichen Grüßen\nIhr Kreis Kleve Reservierungssystem";

        // Configure and send email
        $mailer = new PHPMailer(true);
        try {
            // Server settings
            $mailer->isSMTP();
            $mailer->Host = $config['smtp_host'];
            $mailer->Port = $config['smtp_port'];
            $mailer->SMTPAuth = $config['smtp_auth'];
            $mailer->SMTPSecure = $config['smtp_secure'] ? 'tls' : false;
            $mailer->SMTPAutoTLS = $config['smtp_autotls'];

            // Recipients
            $mailer->setFrom($config['from_email'], $config['from_name']);
            $mailer->addAddress($userData['email']);

            // Content
            $mailer->CharSet = 'UTF-8';
            $mailer->isHTML(false);
            $mailer->Subject = $betreff;
            $mailer->Body = $body;

            error_log("[Mail Service] Attempting to send email to: " . $userData['email']);

            if ($mailer->send()) {
                $stmt3 = $db->prepare("UPDATE mail_queue SET send = 1 WHERE id = ?");
                $stmt3->execute([$mail['id']]);
                error_log("[Mail Service] Email sent successfully to: " . $userData['email']);
            }
        } catch (Exception $e) {
            error_log("[Mail Service] Mail sending failed: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log("[Mail Service] Error: " . $e->getMessage());
}