<?php
// Mail-Server Konfiguration
/*return [
    'smtp_host' => 'm50003',              // Dein SMTP-Server
    'smtp_port' => 25,                    // Port
    'smtp_auth' => false,                 // Keine Authentifizierung nötig
    'smtp_username' => '',                // Leer lassen
    'smtp_password' => '',                // Leer lassen
    'smtp_secure' => false,               // Keine Verschlüsselung
    'smtp_autotls' => false,              // Automatisches TLS deaktiviert
    'from_email' => 'absender@localhost', // Absender (anpassen!)
    'from_name' => 'Kreis Kleve Projekt', // Absender-Name
];
*/

return [
    'smtp_host' => '127.0.0.1',               // Mercury-Server (lokal)
    'smtp_port' => 25,                         // Standardport Mercury
    'smtp_auth' => false,                      // Keine Authentifizierung bei Mercury
    'smtp_username' => '',                     // Nicht nötig
    'smtp_password' => '',                     // Nicht nötig
    'smtp_secure' => false,                    // Kein TLS/SSL
    'smtp_autotls' => false,                   // Kein automatisches TLS
    'from_email' => 'noreply@localhost.localdomain',          // Absender-Adresse
    'from_name' => 'Kreis Kleve Projekt',      // Absender-Name
];
