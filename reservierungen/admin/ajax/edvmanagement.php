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
        case 'add_ressource_typ':
            $name = trim($_POST['name'] ?? '');
            
            try {
                $stmt = $db_conn->prepare("INSERT INTO edv_ressourcen_typ (name) VALUES (?)");
                $stmt->execute([$name]);
                $_SESSION['success_message'] = "Ressourcentyp erfolgreich hinzugefügt.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Hinzufügen des Ressourcentyps.";
            }
            break;
            
        case 'edit_ressource_typ':
            $id = $_POST['id'];
            $name = trim($_POST['name'] ?? '');
            
            try {
                $stmt = $db_conn->prepare("UPDATE edv_ressourcen_typ SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                $_SESSION['success_message'] = "Ressourcentyp erfolgreich aktualisiert.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Aktualisieren des Ressourcentyps.";
            }
            break;
            
        case 'delete_ressource_typ':
            $id = $_POST['id'];
            
            try {
                // Check if type has resources
                $stmt = $db_conn->prepare("SELECT COUNT(*) FROM edv_ressourcen WHERE typ_id = ?");
                $stmt->execute([$id]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $_SESSION['error_message'] = "Ressourcentyp kann nicht gelöscht werden, da noch Ressourcen diesem Typ zugeordnet sind.";
                } else {
                    $stmt = $db_conn->prepare("DELETE FROM edv_ressourcen_typ WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success_message'] = "Ressourcentyp erfolgreich gelöscht.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Löschen des Ressourcentyps.";
            }
            break;
            
        case 'add_ressource':
            $name = trim($_POST['name'] ?? '');
            $typ_id = $_POST['typ_id'];
            
            try {
                $stmt = $db_conn->prepare("INSERT INTO edv_ressourcen (name, typ_id) VALUES (?, ?)");
                $stmt->execute([$name, $typ_id]);
                $_SESSION['success_message'] = "EDV-Ressource erfolgreich hinzugefügt.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Hinzufügen der EDV-Ressource.";
            }
            break;
            
        case 'edit_ressource':
            $id = $_POST['id'];
            $name = trim($_POST['name'] ?? '');
            $typ_id = $_POST['typ_id'];
            
            try {
                $stmt = $db_conn->prepare("UPDATE edv_ressourcen SET name = ?, typ_id = ? WHERE id = ?");
                $stmt->execute([$name, $typ_id, $id]);
                $_SESSION['success_message'] = "EDV-Ressource erfolgreich aktualisiert.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Aktualisieren der EDV-Ressource.";
            }
            break;
            
        case 'delete_ressource':
            $id = $_POST['id'];
            
            try {
                // Check if resource has active reservations
                $stmt = $db_conn->prepare("SELECT COUNT(*) FROM edv_reservations WHERE ressource = ? AND datum >= CURDATE()");
                $stmt->execute([$id]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $_SESSION['error_message'] = "EDV-Ressource kann nicht gelöscht werden, da noch aktive Reservierungen vorhanden sind.";
                } else {
                    $stmt = $db_conn->prepare("DELETE FROM edv_ressourcen WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success_message'] = "EDV-Ressource erfolgreich gelöscht.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Löschen der EDV-Ressource.";
            }
            break;
    }
    
    header('Location: edvmanagement.php');
    exit;
}

// Fetch resource types and resources
try {
    $ressourcen_typen = $db_conn->query("SELECT * FROM edv_ressourcen_typ ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $ressourcen = $db_conn->query("SELECT r.*, t.name as typ_name FROM edv_ressourcen r 
                                  JOIN edv_ressourcen_typ t ON r.typ_id = t.id 
                                  ORDER BY t.name, r.name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Fehler beim Abrufen der EDV-Ressourcen: " . $e->getMessage());
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
    <title>EDV-Ressourcenverwaltung - Kreis Kleve</title>
    <link rel="stylesheet" href="../../style.css">
    <style>
        .resource-section {
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
        
        .resource-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .resource-table th {
            text-align: left;
            padding: 0.75rem;
            background-color: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .resource-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .resource-table tr:hover td {
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
        
        .form-group input, .form-group select {
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
            .resource-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>EDV-Ressourcenverwaltung</h1>
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

            <!-- Ressourcentypen Section -->
            <section class="resource-section">
                <div class="section-header">
                    <h2>Ressourcentypen</h2>
                    <button class="btn-add" onclick="openAddTypModal()">+ Ressourcentyp hinzufügen</button>
                </div>
                
                <table class="resource-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ressourcen_typen)): ?>
                        <tr>
                            <td colspan="2">Keine Ressourcentypen gefunden.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($ressourcen_typen as $typ): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($typ['name']); ?></td>
                            <td class="actions">
                                <button class="btn-action btn-edit" 
                                        onclick="openEditTypModal(<?php echo $typ['id']; ?>, 
                                            '<?php echo addslashes($typ['name']); ?>')">
                                    Bearbeiten
                                </button>
                                <button class="btn-action btn-delete" 
                                        onclick="openDeleteTypModal(<?php echo $typ['id']; ?>)">
                                    Löschen
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <!-- EDV-Ressourcen Section -->
            <section class="resource-section">
                <div class="section-header">
                    <h2>EDV-Ressourcen</h2>
                    <button class="btn-add" onclick="openAddRessourceModal()">+ EDV-Ressource hinzufügen</button>
                </div>
                
                <table class="resource-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Typ</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ressourcen)): ?>
                        <tr>
                            <td colspan="3">Keine EDV-Ressourcen gefunden.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($ressourcen as $ressource): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ressource['name']); ?></td>
                            <td><?php echo htmlspecialchars($ressource['typ_name']); ?></td>
                            <td class="actions">
                                <button class="btn-action btn-edit" 
                                        onclick="openEditRessourceModal(<?php echo $ressource['id']; ?>, 
                                            '<?php echo addslashes($ressource['name']); ?>', 
                                            <?php echo $ressource['typ_id']; ?>)">
                                    Bearbeiten
                                </button>
                                <button class="btn-action btn-delete" 
                                        onclick="openDeleteRessourceModal(<?php echo $ressource['id']; ?>)">
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

    <!-- Add Resource Type Modal -->
    <div id="addTypModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ressourcentyp hinzufügen</h3>
                <button class="modal-close" onclick="closeModal('addTypModal')">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="add_ressource_typ">
                
                <div class="form-group">
                    <label for="typ_name">Name*</label>
                    <input type="text" id="typ_name" name="name" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('addTypModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-add">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Resource Type Modal -->
    <div id="editTypModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ressourcentyp bearbeiten</h3>
                <button class="modal-close" onclick="closeModal('editTypModal')">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="edit_ressource_typ">
                <input type="hidden" id="edit_typ_id" name="id">
                
                <div class="form-group">
                    <label for="edit_typ_name">Name*</label>
                    <input type="text" id="edit_typ_name" name="name" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('editTypModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-edit">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Resource Type Modal -->
    <div id="deleteTypModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ressourcentyp löschen</h3>
                <button class="modal-close" onclick="closeModal('deleteTypModal')">&times;</button>
            </div>
            
            <p>Möchten Sie diesen Ressourcentyp wirklich löschen?</p>
            
            <form method="post">
                <input type="hidden" name="action" value="delete_ressource_typ">
                <input type="hidden" id="delete_typ_id" name="id">
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('deleteTypModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-delete">Löschen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Resource Modal -->
    <div id="addRessourceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>EDV-Ressource hinzufügen</h3>
                <button class="modal-close" onclick="closeModal('addRessourceModal')">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="add_ressource">
                
                <div class="form-group">
                    <label for="ressource_name">Name*</label>
                    <input type="text" id="ressource_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="ressource_typ">Typ*</label>
                    <select id="ressource_typ" name="typ_id" required>
                        <?php if (empty($ressourcen_typen)): ?>
                        <option value="" disabled>Keine Ressourcentypen verfügbar</option>
                        <?php else: ?>
                        <option value="">-- Bitte wählen --</option>
                        <?php foreach ($ressourcen_typen as $typ): ?>
                        <option value="<?php echo $typ['id']; ?>"><?php echo htmlspecialchars($typ['name']); ?></option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('addRessourceModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-add" <?php echo empty($ressourcen_typen) ? 'disabled' : ''; ?>>Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Resource Modal -->
    <div id="editRessourceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>EDV-Ressource bearbeiten</h3>
                <button class="modal-close" onclick="closeModal('editRessourceModal')">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="edit_ressource">
                <input type="hidden" id="edit_ressource_id" name="id">
                
                <div class="form-group">
                    <label for="edit_ressource_name">Name*</label>
                    <input type="text" id="edit_ressource_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_ressource_typ">Typ*</label>
                    <select id="edit_ressource_typ" name="typ_id" required>
                        <?php if (empty($ressourcen_typen)): ?>
                        <option value="" disabled>Keine Ressourcentypen verfügbar</option>
                        <?php else: ?>
                        <?php foreach ($ressourcen_typen as $typ): ?>
                        <option value="<?php echo $typ['id']; ?>"><?php echo htmlspecialchars($typ['name']); ?></option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('editRessourceModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-edit">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Resource Modal -->
    <div id="deleteRessourceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>EDV-Ressource löschen</h3>
                <button class="modal-close" onclick="closeModal('deleteRessourceModal')">&times;</button>
            </div>
            
            <p>Möchten Sie diese EDV-Ressource wirklich löschen?</p>
            
            <form method="post">
                <input type="hidden" name="action" value="delete_ressource">
                <input type="hidden" id="delete_ressource_id" name="id">
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('deleteRessourceModal')">Abbrechen</button>
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
        
        // Resource Type modal functions
        function openAddTypModal() {
            document.getElementById('typ_name').value = '';
            openModal('addTypModal');
        }
        
        function openEditTypModal(id, name) {
            document.getElementById('edit_typ_id').value = id;
            document.getElementById('edit_typ_name').value = name;
            openModal('editTypModal');
        }
        
        function openDeleteTypModal(id) {
            document.getElementById('delete_typ_id').value = id;
            openModal('deleteTypModal');
        }
        
        // Resource modal functions
        function openAddRessourceModal() {
            document.getElementById('ressource_name').value = '';
            document.getElementById('ressource_typ').selectedIndex = 0;
            openModal('addRessourceModal');
        }
        
        function openEditRessourceModal(id, name, typId) {
            document.getElementById('edit_ressource_id').value = id;
            document.getElementById('edit_ressource_name').value = name;
            document.getElementById('edit_ressource_typ').value = typId;
            openModal('editRessourceModal');
        }
        
        function openDeleteRessourceModal(id) {
            document.getElementById('delete_ressource_id').value = id;
            openModal('deleteRessourceModal');
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
    </script>
</body>
</html>