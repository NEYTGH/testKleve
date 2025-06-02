<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Load config
$config = require __DIR__ . '/config_mail.php';

try {
    $db = new PDO("mysql:host=localhost;dbname=buchungssystem", "buchung_user", "passwort1234");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all unsent mails
    $stmt = $db->prepare("SELECT * FROM MailQueue WHERE mailqueue_sent = 0");
    $stmt->execute();
    $mails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[Mail Service] Database connection established");

    foreach ($mails as $mail) {
        // Determine reservation type and get details
        $reservationType = $mail['mailqueue_reservierungstyp'];
        $bookingDetails = null;

        switch ($reservationType) {
            case 1: // Room
                $stmt = $db->prepare("
                    SELECT rb.*, r.ressource_name, m.mitarbeiter_email, m.mitarbeiter_vorname, m.mitarbeiter_nachname 
                    FROM RaumBuchung rb
                    JOIN Raum r ON rb.ressource_id = r.ressource_id
                    JOIN Mitarbeiter m ON rb.mitarbeiter_id = m.mitarbeiter_id
                    WHERE rb.RaumBuchung_id = ?
                ");
                $stmt->execute([$mail['RaumBuchung_id']]);
                break;

            case 2: // Bike
                $stmt = $db->prepare("
                    SELECT rb.*, r.ressource_name, m.mitarbeiter_email, m.mitarbeiter_vorname, m.mitarbeiter_nachname 
                    FROM RadBuchung rb
                    JOIN Rad r ON rb.ressource_id = r.ressource_id
                    JOIN Mitarbeiter m ON rb.mitarbeiter_id = m.mitarbeiter_id
                    WHERE rb.RadBuchung_id = ?
                ");
                $stmt->execute([$mail['RadBuchung_id']]);
                break;

            case 3: // Car
                $stmt = $db->prepare("
                    SELECT wb.*, w.ressource_name, w.wagen_numernschild, m.mitarbeiter_email, 
                           m.mitarbeiter_vorname, m.mitarbeiter_nachname 
                    FROM WagenBuchung wb
                    JOIN Wagen w ON wb.ressource_id = w.ressource_id
                    JOIN Mitarbeiter m ON wb.mitarbeiter_id = m.mitarbeiter_id
                    WHERE wb.WagenBuchung_id = ?
                ");
                $stmt->execute([$mail['WagenBuchung_id']]);
                break;

            case 4: // EDV
                $stmt = $db->prepare("
                    SELECT eb.*, e.ressource_name, m.mitarbeiter_email, m.mitarbeiter_vorname, m.mitarbeiter_nachname 
                    FROM EDVBuchung eb
                    JOIN EDVGeraet e ON eb.ressource_id = e.ressource_id
                    JOIN Mitarbeiter m ON eb.mitarbeiter_id = m.mitarbeiter_id
                    WHERE eb.EDVBuchung_id = ?
                ");
                $stmt->execute([$mail['EDVBuchung_id']]);
                break;

            case 5: // Exhibition
                $stmt = $db->prepare("
                    SELECT mb.*, mo.ressource_name, m.mitarbeiter_email, m.mitarbeiter_vorname, m.mitarbeiter_nachname 
                    FROM MesseBuchung mb
                    JOIN MesseObjekt mo ON mb.ressource_id = mo.ressource_id
                    JOIN Mitarbeiter m ON mb.mitarbeiter_id = m.mitarbeiter_id
                    WHERE mb.MesseBuchung_id = ?
                ");
                $stmt->execute([$mail['MesseBuchung_id']]);
                break;
        }

        $bookingDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bookingDetails) {
            error_log("[Mail Service] No booking details found for mail queue ID: " . $mail['MailQueue_id']);
            continue;
        }

        // Build email content
        $subject = "Buchungsbestätigung - Kreis Kleve";
        $body = "Sehr geehrte(r) " . $bookingDetails['mitarbeiter_vorname'] . " " . $bookingDetails['mitarbeiter_nachname'] . ",\n\n";
        
        // Add booking specific details
        $body .= getBookingDetailsText($bookingDetails, $reservationType);
        
        // Send email
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
            $mailer->addAddress($bookingDetails['mitarbeiter_email']);

            // Content
            $mailer->CharSet = 'UTF-8';
            $mailer->isHTML(false);
            $mailer->Subject = $subject;
            $mailer->Body = $body;

            if ($mailer->send()) {
                // Mark mail as sent
                $updateStmt = $db->prepare("UPDATE MailQueue SET mailqueue_sent = 1 WHERE MailQueue_id = ?");
                $updateStmt->execute([$mail['MailQueue_id']]);
                error_log("[Mail Service] Email sent successfully to: " . $bookingDetails['mitarbeiter_email']);
            }
        } catch (Exception $e) {
            error_log("[Mail Service] Mail sending failed: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log("[Mail Service] Error: " . $e->getMessage());
}

// Helper function to generate booking details text
function getBookingDetailsText($details, $type) {
    $text = "Ihre Buchung wurde erfolgreich ";
    $text .= $details['buchung_storniert'] ? "storniert" : "bestätigt";
    $text .= ".\n\n";

    $text .= "Ressource: " . $details['ressource_name'] . "\n";
    
    if ($type !== 5) { // Not MesseBuchung
        $text .= "Datum: " . date('d.m.Y', strtotime($details['buchung_datum'])) . "\n";
        $text .= "Zeit: " . substr($details['buchung_startzeit'], 0, 5) . " - " . 
                substr($details['buchung_endzeit'], 0, 5) . " Uhr\n";
    } else {
        $text .= "Von: " . date('d.m.Y', strtotime($details['buchung_startdatum'])) . "\n";
        $text .= "Bis: " . date('d.m.Y', strtotime($details['buchung_enddatum'])) . "\n";
    }

    $text .= "Zweck: " . $details['buchung_zweck'] . "\n";

    if (!empty($details['buchung_bemerkungen'])) {
        $text .= "Bemerkungen: " . $details['buchung_bemerkungen'] . "\n";
    }

    if (!$details['buchung_storniert']) {
        $text .= "\nSie können Ihre Buchung über folgenden Link stornieren:\n";
        $text .= "http://localhost/buchungssystem/storno.php?token=" . $details['buchung_stornolink'] . "\n";
    }

    $text .= "\nMit freundlichen Grüßen\nIhr Buchungssystem";

    return $text;
}