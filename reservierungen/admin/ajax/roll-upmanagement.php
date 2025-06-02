<?php
// Start session and include required files
session_start();
require_once('../../../wp-load.php');
require_once('../db-config.php');
require_once('../auth_functions.php');

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
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
        case 'add_rollup':
            $name = trim($_POST['name'] ?? '');
            
            try {
                $stmt = $db_conn->prepare("INSERT INTO rollup_pool (name) VALUES (?)");
                $stmt->execute([$name]);
                $_SESSION['success_message'] = "Roll-Up erfolgreich hinzugefügt.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Hinzufügen des Roll-Ups.";
            }
            break;
            
        case 'edit_rollup':
            $id = $_POST['id'];
            $name = trim($_POST['name'] ?? '');
            
            try {
                $stmt = $db_conn->prepare("UPDATE rollup_pool SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                $_SESSION['success_message'] = "Roll-Up erfolgreich aktualisiert.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Aktualisieren des Roll-Ups.";
            }
            break;
            
        case 'delete_rollup':
            $id = $_POST['id'];
            
            try {
                // Check if rollup has active reservations
                $stmt = $db_conn->prepare("SELECT COUNT(*) FROM rollup_reservations WHERE rollup_id = ? AND datum >= CURDATE()");
                $stmt->execute([$id]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $_SESSION['error_message'] = "Roll-Up kann nicht gelöscht werden, da noch aktive Reservierungen vorhanden sind.";
                } else {
                    $stmt = $db_conn->prepare("DELETE FROM rollup_pool WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success_message'] = "Roll-Up erfolgreich gelöscht.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Löschen des Roll-Ups.";
            }
            break;
    }
    
    header('Location: roll-upmanagement.php');
    exit;
}

// Fetch roll-ups
try {
    $rollups = $db_conn->query("SELECT * FROM rollup_pool ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Fehler beim Abrufen der Roll-Ups: " . $e->getMessage());
}

// Get success/error messages
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roll-Up Verwaltung - Kreis Kleve</title>
    <link rel="stylesheet" href="../../style.css">
    <style>
        .rollup-section {
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .btn-add {
            background-color: #48bb78;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background-color 0.2s;
        }
        
        .btn-add:hover {
            background-color: #38a169;
        }
        
        .rollup-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .rollup-table th {
            text-align: left;
            padding: 0.75rem;
            background-color: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .rollup-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .rollup-table tr:hover td {
            background-color: #f7fafc;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-edit {
            background-color: #4299e1;
            color: white;
        }
        
        .btn-edit:hover {
            background-color: #3182ce;
        }
        
        .btn-delete {
            background-color: #f56565;
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #e53e3e;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            border: none;
            cursor: pointer;
            font-size: 0.75rem;
            transition: background-color 0.2s;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            width: 100%;
            max-width: 500px;
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #718096;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.25rem;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        
        .btn-secondary {
            background-color: #e2e8f0;
            color: #2d3748;
        }
        
        .btn-secondary:hover {
            background-color: #cbd5e0;
        }
        
        /* Message styles */
        .message {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0.375rem;
        }
        
        .message.success {
            background-color: #c6f6d5;
            color: #2f855a;
        }
        
        .message.error {
            background-color: #fed7d7;
            color: #c53030;
        }
        
        @media (max-width: 640px) {
            .rollup-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .btn-add {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Roll-Up Verwaltung</h1>
            <nav>
                <ul>
                    <li><a href="../dashboard.php">Zurück zum Dashboard</a></li>
                </ul>
            </nav>
        </header>

        <main>
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

            <!-- Roll-Ups Section -->
            <section class="rollup-section">
                <div class="section-header">
                    <h2>Roll-Ups & Präsentationsstände</h2>
                    <button class="btn-add" onclick="openAddRollupModal()">+ Roll-Up hinzufügen</button>
                </div>
                
                <table class="rollup-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rollups)): ?>
                        <tr>
                            <td colspan="2">Keine Roll-Ups gefunden.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($rollups as $rollup): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rollup['name']); ?></td>
                            <td class="actions">
                                <button class="btn-action btn-edit" 
                                        onclick="openEditRollupModal(<?php echo $rollup['id']; ?>, 
                                            '<?php echo addslashes(htmlspecialchars($rollup['name'])); ?>')">
                                    Bearbeiten
                                </button>
                                <button class="btn-action btn-delete" 
                                        onclick="openDeleteRollupModal(<?php echo $rollup['id']; ?>)">
                                    Löschen
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>

        <footer>
            <p>© <?php echo date('Y'); ?> Kreis Kleve | Reservierungssystem</p>
        </footer>
    </div>

    <!-- Add Roll-Up Modal -->
    <div id="addRollupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Roll-Up hinzufügen</h3>
                <button class="modal-close" onclick="closeModal('addRollupModal')">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="add_rollup">
                
                <div class="form-group">
                    <label for="rollup_name">Name*</label>
                    <input type="text" id="rollup_name" name="name" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('addRollupModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-add">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Roll-Up Modal -->
    <div id="editRollupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Roll-Up bearbeiten</h3>
                <button class="modal-close" onclick="closeModal('editRollupModal')">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="edit_rollup">
                <input type="hidden" id="edit_rollup_id" name="id">
                
                <div class="form-group">
                    <label for="edit_rollup_name">Name*</label>
                    <input type="text" id="edit_rollup_name" name="name" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('editRollupModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-edit">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Roll-Up Modal -->
    <div id="deleteRollupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Roll-Up löschen</h3>
                <button class="modal-close" onclick="closeModal('deleteRollupModal')">&times;</button>
            </div>
            
            <p>Möchten Sie diesen Roll-Up wirklich löschen?</p>
            <p><small>Hinweis: Roll-Ups mit aktiven Reservierungen können nicht gelöscht werden.</small></p>
            
            <form method="post">
                <input type="hidden" name="action" value="delete_rollup">
                <input type="hidden" id="delete_rollup_id" name="id">
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('deleteRollupModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-delete">Löschen</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Roll-Up modal functions
        function openAddRollupModal() {
            document.getElementById('rollup_name').value = '';
            openModal('addRollupModal');
        }
        
        function openEditRollupModal(id, name) {
            document.getElementById('edit_rollup_id').value = id;
            document.getElementById('edit_rollup_name').value = name;
            openModal('editRollupModal');
        }
        
        function openDeleteRollupModal(id) {
            document.getElementById('delete_rollup_id').value = id;
            openModal('deleteRollupModal');
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target === modals[i]) {
                    closeModal(modals[i].id);
                }
            }
        });
        
        // Auto-dismiss messages after 5 seconds
        window.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.message');
            if (messages.length > 0) {
                setTimeout(function() {
                    messages.forEach(function(message) {
                        message.style.opacity = '0';
                        message.style.transform = 'translateY(-10px)';
                        message.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        
                        setTimeout(function() {
                            message.style.display = 'none';
                        }, 500);
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html>