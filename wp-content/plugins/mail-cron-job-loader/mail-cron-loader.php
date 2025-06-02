<?php
/*
Plugin Name: Mail Cron Loader
Description: Führt alle 5 Minuten das Mailversand-Skript aus.
Version: 1.1
Author: Deine Agentur
*/

defined('ABSPATH') or die('Kein Zugriff erlaubt');

// Cron-Intervall hinzufügen (alle 5 Minuten)
add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['alle_5_minuten'])) {
        $schedules['alle_5_minuten'] = [
            'interval' => 300,
            'display'  => __('Alle 5 Minuten')
        ];
    }
    return $schedules;
});

// Cronjob registrieren
add_action('init', function() {
    if (!wp_next_scheduled('mailversand_event')) {
        wp_schedule_event(time(), 'alle_5_minuten', 'mailversand_event');
    }
});

// Mailversand-Event ausführen
add_action('mailversand_event', function() {
    $script = plugin_dir_path(__FILE__) . 'mailversand.php';
    error_log("Versuche mailversand.php auszuführen: " . $script);

    if (file_exists($script)) {
        include $script;
        error_log("mailversand.php wurde erfolgreich eingebunden.");
    } else {
        error_log("Mailversand-Datei NICHT gefunden: $script");
    }
});
