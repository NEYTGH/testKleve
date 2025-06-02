<?php
return [
    'ldap_server' => 'ldap://###',
    'ldap_domain' => '###', 
    'ldap_base_dn' => 'OU=###,DC=###,DC=local',
    'ldap_group_mapping' => [
        'ageg' => ['dienstfahrrad', 'dienstwagen'],
        'tuiv' => ['edv'],
        'kreistagsbuero' => ['room'],
        'pressestelle' => ['rollup'],
    ] /** Gruppen Namen bei bedarf ändern, berechtigungsnamen werden so in der auth verwendet.
	   *  Es wird empfohlen diese Config datei nicht im WordPress Ordner zu lagern, um einen Externen Zugriff zu verhindern.
	   *  Dann muss der Pfad in auth_function.php geändert werden
	   */
];


/**
 * LDAP-Konfiguration für die automatische Benutzer-Authentifizierung
 * ---------------------------------------------------------------
 * 
 * Diese Datei wird für die automatische Authentifizierung von Windows-Benutzern (Active Directory)
 * im internen Netzwerk verwendet. Sie ermöglicht es, Benutzer **automatisch** anhand ihrer Windows-
 * Anmeldedaten zu erkennen und mit den richtigen Berechtigungen zu versehen.
 * 
 * WICHTIG: Diese Authentifizierung gilt nur für den Admin-Bereich der Anwendung.
 *     Der Admin-Bereich befindet sich unter dem Pfad:
 *         /reservierungen/admin/
 * 
 * Alle anderen Bereiche der Anwendung, einschließlich der Reservierungsseiten,
 * bleiben weiterhin ohne Authentifizierung zugänglich und können von allen Benutzern ohne Anmeldung genutzt werden.
 * 
 * ---------------------------------------------------------------
 * Anleitung für die Einrichtung:
 * 
 * 1. Fügen Sie die Adresse des LDAP-Servers ein:
 *    - Format: ldap://<Server-Adresse>
 *    - Beispiel: ldap://ad.kommune.local
 * 
 * 2️. Tragen Sie den Domainnamen des Active Directory ein:
 *    - Beispiel: MEINEDOMAIN
 * 
 * 3️. Definieren Sie den Basis-DN der Gruppen (OU = Organisationseinheit):
 *    - Beispiel: OU=Gruppen,DC=meinefirma,DC=local
 *    - Dieser DN muss auf die Organisationseinheit verweisen, in der sich die relevanten Gruppen befinden.
 * 
 * 4️. Passen Sie die Gruppennamen und die zugehörigen Berechtigungen an:
 *    - Die Gruppennamen müssen exakt so geschrieben werden, wie sie im Active Directory vorkommen.
 *    - Die Berechtigungen müssen mit den Reservierungstypen aus der Anwendung übereinstimmen:
 *        ('room'), ('rollup'), ('edv'), ('dienstfahrrad'), ('dienstwagen')
 * 
 * Beispielkonfiguration:
 * 
 * 'ldap_group_mapping' => [
 *     'ageg' => ['dienstfahrrad', 'dienstwagen'],
 *     'tuiv' => ['edv'],
 *     'kreistagsbuero' => ['room'],
 *     'pressestelle' => ['rollup'],
 * ]

 * 5. Servereinstellungen:
 * 		Im IIS-Manager:
 *  		Öffnen Sie Ihre Website
 *  		Navigieren Sie zu dem Unterordner reservierungen/admin/
 *  
 *  	Im Admin-Ordner:
 *  		Doppelklicken Sie auf Authentifizierung
 *  		Windows-Authentifizierung aktivieren
 *  		Anonyme Authentifizierung deaktivieren
 * 
 *    - Für automatische Anmeldung per Windows-User muss die Website auf einem Windows-Server (IIS) laufen.
 *    - Im IIS es Admin Ordners die Windows-Authentifizierung aktivieren und die Anonyme Authentifizierung deaktivieren.
 *    - Damit werden Benutzer automatisch anhand ihrer Windows-Session authentifiziert.
 */