<?php
// Start session and include required files
session_start();
require_once('../../../wp-load.php'); // Pfad anpassen, falls notwendig
require_once('../db-config.php');    // Pfad anpassen, falls notwendig
require_once('../auth_functions.php'); // Pfad anpassen, falls notwendig

// Check if user is logged in as admin and has room permissions
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php'); // Zurück zum Admin-Login, falls nicht eingeloggt
    exit;
}
// Zusätzlich prüfen, ob der Admin die Berechtigung für Räume hat
if (!in_array('room', $_SESSION['admin_permissions'])) {
    // Optional: Umleiten zum Dashboard oder eine Fehlermeldung anzeigen
    $_SESSION['admin_error_message'] = "Sie haben keine Berechtigung, Räume zu verwalten.";
    header('Location: ../dashboard.php'); // Pfad zum Dashboard anpassen
    exit;
}

// Database connection
try {
    $db_conn = new PDO("mysql:host=$db_hostname;dbname=$db_datenbankname;charset=utf8", $db_username, $db_passwort);
    $db_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_room':
            $name = trim($_POST['name'] ?? '');
            if (!empty($name)) {
                try {
                    $stmt = $db_conn->prepare("INSERT INTO room_pool (name) VALUES (?)");
                    $stmt->execute([$name]);
                    $_SESSION['room_success_message'] = "Raum erfolgreich hinzugefügt.";
                } catch (PDOException $e) {
                    $_SESSION['room_error_message'] = "Fehler beim Hinzufügen des Raums: " . $e->getMessage();
                }
            } else {
                $_SESSION['room_error_message'] = "Raumname darf nicht leer sein.";
            }
            break;
            
        case 'edit_room':
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            
            if (!empty($id) && !empty($name)) {
                try {
                    $stmt = $db_conn->prepare("UPDATE room_pool SET name = ? WHERE id = ?");
                    $stmt->execute([$name, $id]);
                    $_SESSION['room_success_message'] = "Raum erfolgreich aktualisiert.";
                } catch (PDOException $e) {
                    $_SESSION['room_error_message'] = "Fehler beim Aktualisieren des Raums: " . $e->getMessage();
                }
            } else {
                 $_SESSION['room_error_message'] = "Raum-ID oder Name ungültig.";
            }
            break;
            
        case 'delete_room':
            $id = $_POST['id'] ?? '';
            if (!empty($id)) {
                try {
                    // Check if room has active reservations
                    $stmt_check = $db_conn->prepare("SELECT COUNT(*) FROM room_reservations WHERE raum_id = ? AND datum >= CURDATE()");
                    $stmt_check->execute([$id]);
                    $count = $stmt_check->fetchColumn();
                    
                    if ($count > 0) {
                        $_SESSION['room_error_message'] = "Raum kann nicht gelöscht werden, da noch aktive Reservierungen vorhanden sind.";
                    } else {
                        $stmt = $db_conn->prepare("DELETE FROM room_pool WHERE id = ?");
                        $stmt->execute([$id]);
                        $_SESSION['room_success_message'] = "Raum erfolgreich gelöscht.";
                    }
                } catch (PDOException $e) {
                    // Prüfen auf Foreign Key Constraint Fehler explizit, falls DB so konfiguriert ist
                    if ($e->getCode() == '23000') {
                        $_SESSION['room_error_message'] = "Raum kann nicht gelöscht werden, da noch Reservierungen (auch vergangene) darauf verweisen. Bitte prüfen Sie die Datenbankintegrität oder kontaktieren Sie einen Administrator.";
                    } else {
                        $_SESSION['room_error_message'] = "Fehler beim Löschen des Raums: " . $e->getMessage();
                    }
                }
            } else {
                $_SESSION['room_error_message'] = "Raum-ID ungültig.";
            }
            break;
    }
    
    header('Location: roommanagement.php'); // Redirect zur selben Seite, um POST-Resubmission zu vermeiden
    exit;
}

// Fetch rooms
try {
    $rooms = $db_conn->query("SELECT * FROM room_pool ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Fehler beim Abrufen der Räume: " . $e->getMessage());
}

// Get success/error messages from session
$success_message = $_SESSION['room_success_message'] ?? null;
$error_message = $_SESSION['room_error_message'] ?? null;
unset($_SESSION['room_success_message'], $_SESSION['room_error_message']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raumverwaltung - Kreis Kleve</title>
    <link rel="stylesheet" href="../../style.css"> <style>
        /* Übernehmen Sie hier relevante Styles von vehiclemanagement.php oder definieren Sie neue */
        .container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        header { margin-bottom: 2rem; padding-bottom:1rem; border-bottom: 1px solid #ccc;}
        header h1 { margin-bottom: 0.5rem; }
        nav ul { list-style: none; padding: 0; }
        nav ul li { display: inline; margin-right: 1rem; }
        .main-content { background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .btn-add { background-color: #48bb78; color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; border: none; cursor: pointer; font-size: 0.875rem; transition: background-color 0.2s; text-decoration: none; }
        .btn-add:hover { background-color: #38a169; }
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .data-table th { text-align: left; padding: 0.75rem; background-color: #f7fafc; border-bottom: 2px solid #e2e8f0; }
        .data-table td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; }
        .data-table tr:hover td { background-color: #f7fafc; }
        .actions { display: flex; gap: 0.5rem; }
        .btn-action { padding: 0.25rem 0.5rem; border-radius: 0.25rem; border: none; cursor: pointer; font-size: 0.75rem; transition: background-color 0.2s; text-decoration: none; color:white !important; display:inline-block;}
        .btn-edit { background-color: #4299e1; }
        .btn-edit:hover { background-color: #3182ce; }
        .btn-delete { background-color: #f56565; }
        .btn-delete:hover { background-color: #e53e3e; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 1.5rem; border-radius: 0.5rem; width: 100%; max-width: 500px; position: relative; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #718096; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input { width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.25rem; box-sizing: border-box;}
        .modal-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
        .btn-secondary { background-color: #e2e8f0; color: #2d3748 !important; }
        .btn-secondary:hover { background-color: #cbd5e0; }

        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.375rem; }
        .message.success { background-color: #c6f6d5; color: #2f855a; }
        .message.error { background-color: #fed7d7; color: #c53030; }
        footer { margin-top: 2rem; text-align: center; color: #777; padding-top: 1rem; border-top: 1px solid #ccc;}
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Raumverwaltung</h1>
            <nav>
                <ul>
                    <li><a href="../dashboard.php">Zurück zum Dashboard</a></li> </ul>
            </nav>
        </header>

        <main class="main-content">
            <?php if ($success_message): ?>
            <div class="message success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="message error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <div class="section-header">
                <h2>Bestehende Räume</h2>
                <button class="btn-add" onclick="openAddRoomModal()">+ Raum hinzufügen</button>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rooms)): ?>
                        <tr>
                            <td colspan="2">Keine Räume gefunden.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($room['name']); ?></td>
                        <td class="actions">
                            <button class="btn-action btn-edit" 
                                    onclick="openEditRoomModal(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars(addslashes($room['name'])); ?>')">
                                Bearbeiten
                            </button>
                            <button class="btn-action btn-delete" 
                                    onclick="openDeleteRoomModal(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars(addslashes($room['name'])); ?>')">
                                Löschen
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>

        <footer>
            <p>© <?php echo date('Y'); ?> Kreis Kleve | Reservierungssystem</p>
        </footer>
    </div>

    <div id="addRoomModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Raum hinzufügen</h3>
                <button type="button" class="modal-close" onclick="closeModal('addRoomModal')">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="add_room">
                <div class="form-group">
                    <label for="add_room_name">Raumname*</label>
                    <input type="text" id="add_room_name" name="name" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('addRoomModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-add">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editRoomModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Raum bearbeiten</h3>
                <button type="button" class="modal-close" onclick="closeModal('editRoomModal')">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="edit_room">
                <input type="hidden" id="edit_room_id" name="id">
                <div class="form-group">
                    <label for="edit_room_name_input">Raumname*</label>
                    <input type="text" id="edit_room_name_input" name="name" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('editRoomModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-edit">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteRoomModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Raum löschen</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteRoomModal')">&times;</button>
            </div>
            <p>Möchten Sie den Raum "<span id="delete_room_name_span"></span>" wirklich löschen?</p>
            <form method="post">
                <input type="hidden" name="action" value="delete_room">
                <input type="hidden" id="delete_room_id_input" name="id">
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('deleteRoomModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-delete">Löschen</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function openAddRoomModal() {
            document.getElementById('add_room_name').value = ''; // Feld leeren
            openModal('addRoomModal');
        }
        
        function openEditRoomModal(id, name) {
            document.getElementById('edit_room_id').value = id;
            document.getElementById('edit_room_name_input').value = name;
            openModal('editRoomModal');
        }
        
        function openDeleteRoomModal(id, name) {
            document.getElementById('delete_room_id_input').value = id;
            document.getElementById('delete_room_name_span').textContent = name;
            openModal('deleteRoomModal');
        }
        
        // Close modals when clicking outside (optional, aber gute UX)
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