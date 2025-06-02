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
        case 'add_car':
            $name = $_POST['name'];
            $kennzeichen = $_POST['kennzeichen'];
            $reichweite = $_POST['reichweite'];
            
            try {
                $stmt = $db_conn->prepare("INSERT INTO companycar_pool (name, kennzeichen, reichweite) VALUES (?, ?, ?)");
                $stmt->execute([$name, $kennzeichen, $reichweite]);
                $_SESSION['success_message'] = "Fahrzeug erfolgreich hinzugefügt.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Hinzufügen des Fahrzeugs.";
            }
            break;
            
        case 'add_bicycle':
            $name = $_POST['name'];
            $reichweite = $_POST['reichweite'];
            
            try {
                $stmt = $db_conn->prepare("INSERT INTO companybicycle_pool (name, reichweite) VALUES (?, ?)");
                $stmt->execute([$name, $reichweite]);
                $_SESSION['success_message'] = "Fahrrad erfolgreich hinzugefügt.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Hinzufügen des Fahrrads.";
            }
            break;
            
        case 'edit_car':
            $id = $_POST['id'];
            $name = $_POST['name'];
            $kennzeichen = $_POST['kennzeichen'];
            $reichweite = $_POST['reichweite'];
            
            try {
                $stmt = $db_conn->prepare("UPDATE companycar_pool SET name = ?, kennzeichen = ?, reichweite = ? WHERE id = ?");
                $stmt->execute([$name, $kennzeichen, $reichweite, $id]);
                $_SESSION['success_message'] = "Fahrzeug erfolgreich aktualisiert.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Aktualisieren des Fahrzeugs.";
            }
            break;
            
        case 'edit_bicycle':
            $id = $_POST['id'];
            $name = $_POST['name'];
            $reichweite = $_POST['reichweite'];
            
            try {
                $stmt = $db_conn->prepare("UPDATE companybicycle_pool SET name = ?, reichweite = ? WHERE id = ?");
                $stmt->execute([$name, $reichweite, $id]);
                $_SESSION['success_message'] = "Fahrrad erfolgreich aktualisiert.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Aktualisieren des Fahrrads.";
            }
            break;
            
        case 'delete_car':
            $id = $_POST['id'];
            
            try {
                // Check if vehicle has active reservations
                $stmt = $db_conn->prepare("SELECT COUNT(*) FROM companycar_reservations WHERE dienstwagen = ? AND datum >= CURDATE()");
                $stmt->execute([$id]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $_SESSION['error_message'] = "Fahrzeug kann nicht gelöscht werden, da noch aktive Reservierungen vorhanden sind.";
                } else {
                    $stmt = $db_conn->prepare("DELETE FROM companycar_pool WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success_message'] = "Fahrzeug erfolgreich gelöscht.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Löschen des Fahrzeugs.";
            }
            break;
            
        case 'delete_bicycle':
            $id = $_POST['id'];
            
            try {
                // Check if bicycle has active reservations
                $stmt = $db_conn->prepare("SELECT COUNT(*) FROM companybicycle_reservations WHERE dienstfahrrad = ? AND datum >= CURDATE()");
                $stmt->execute([$id]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $_SESSION['error_message'] = "Fahrrad kann nicht gelöscht werden, da noch aktive Reservierungen vorhanden sind.";
                } else {
                    $stmt = $db_conn->prepare("DELETE FROM companybicycle_pool WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success_message'] = "Fahrrad erfolgreich gelöscht.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Fehler beim Löschen des Fahrrads.";
            }
            break;
    }
    
    header('Location: vehiclemanagement.php');
    exit;
}

// Fetch vehicles
try {
    $cars = $db_conn->query("SELECT * FROM companycar_pool ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $bicycles = $db_conn->query("SELECT * FROM companybicycle_pool ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Fehler beim Abrufen der Fahrzeuge: " . $e->getMessage());
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
    <title>Fahrzeugverwaltung - Kreis Kleve</title>
    <link rel="stylesheet" href="../../style.css">
    <style>
        .vehicle-section {
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
        
        .vehicle-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .vehicle-table th {
            text-align: left;
            padding: 0.75rem;
            background-color: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .vehicle-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .vehicle-table tr:hover td {
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
            .vehicle-table {
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
            <h1>Fahrzeugverwaltung</h1>
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

            <!-- Dienstwagen Section -->
            <section class="vehicle-section">
                <div class="section-header">
                    <h2>Dienstwagen</h2>
                    <button class="btn-add" onclick="openAddCarModal()">+ Dienstwagen hinzufügen</button>
                </div>
                
                <table class="vehicle-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Kennzeichen</th>
                            <th>Reichweite</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cars as $car): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($car['name']); ?></td>
                            <td><?php echo htmlspecialchars($car['kennzeichen']); ?></td>
                            <td><?php echo htmlspecialchars($car['reichweite']); ?></td>
                            <td class="actions">
                                <button class="btn-action btn-edit" 
                                        onclick="openEditCarModal(<?php echo $car['id']; ?>, 
                                            '<?php echo addslashes($car['name']); ?>', 
                                            '<?php echo addslashes($car['kennzeichen']); ?>', 
                                            '<?php echo addslashes($car['reichweite']); ?>')">
                                    Bearbeiten
                                </button>
                                <button class="btn-action btn-delete" 
                                        onclick="openDeleteCarModal(<?php echo $car['id']; ?>)">
                                    Löschen
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <!-- Dienstfahrrad Section -->
            <section class="vehicle-section">
                <div class="section-header">
                    <h2>Dienstfahrräder</h2>
                    <button class="btn-add" onclick="openAddBicycleModal()">+ Dienstfahrrad hinzufügen</button>
                </div>
                
                <table class="vehicle-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Reichweite</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bicycles as $bicycle): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($bicycle['name']); ?></td>
                            <td><?php echo htmlspecialchars($bicycle['reichweite']); ?></td>
                            <td class="actions">
                                <button class="btn-action btn-edit" 
                                        onclick="openEditBicycleModal(<?php echo $bicycle['id']; ?>, 
                                            '<?php echo addslashes($bicycle['name']); ?>', 
                                            '<?php echo addslashes($bicycle['reichweite']); ?>')">
                                    Bearbeiten
                                </button>
                                <button class="btn-action btn-delete" 
                                        onclick="openDeleteBicycleModal(<?php echo $bicycle['id']; ?>)">
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

    <!-- Add Car Modal -->
    <div id="addCarModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Dienstwagen hinzufügen</h3>
                <button class="modal-close" onclick="closeModal('addCarModal')">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="add_car">
                
                <div class="form-group">
                    <label for="car_name">Name*</label>
                    <input type="text" id="car_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="car_kennzeichen">Kennzeichen*</label>
                    <input type="text" id="car_kennzeichen" name="kennzeichen" required>
                </div>
                
                <div class="form-group">
                    <label for="car_reichweite">Reichweite*</label>
                    <input type="text" id="car_reichweite" name="reichweite" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('addCarModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-add">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Car Modal -->
    <div id="editCarModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Dienstwagen bearbeiten</h3>
                <button class="modal-close" onclick="closeModal('editCarModal')">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="edit_car">
                <input type="hidden" id="edit_car_id" name="id">
                
                <div class="form-group">
                    <label for="edit_car_name">Name*</label>
                    <input type="text" id="edit_car_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_car_kennzeichen">Kennzeichen*</label>
                    <input type="text" id="edit_car_kennzeichen" name="kennzeichen" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_car_reichweite">Reichweite*</label>
                    <input type="text" id="edit_car_reichweite" name="reichweite" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('editCarModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-edit">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Car Modal -->
    <div id="deleteCarModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Dienstwagen löschen</h3>
                <button class="modal-close" onclick="closeModal('deleteCarModal')">&times;</button>
            </div>
            
            <p>Möchten Sie diesen Dienstwagen wirklich löschen?</p>
            
            <form method="post">
                <input type="hidden" name="action" value="delete_car">
                <input type="hidden" id="delete_car_id" name="id">
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('deleteCarModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-delete">Löschen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Bicycle Modal -->
    <div id="addBicycleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Dienstfahrrad hinzufügen</h3>
                <button class="modal-close" onclick="closeModal('addBicycleModal')">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="add_bicycle">
                
                <div class="form-group">
                    <label for="bicycle_name">Name*</label>
                    <input type="text" id="bicycle_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="bicycle_reichweite">Reichweite*</label>
                    <input type="text" id="bicycle_reichweite" name="reichweite" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('addBicycleModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-add">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Bicycle Modal -->
    <div id="editBicycleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Dienstfahrrad bearbeiten</h3>
                <button class="modal-close" onclick="closeModal('editBicycleModal')">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="edit_bicycle">
                <input type="hidden" id="edit_bicycle_id" name="id">
                
                <div class="form-group">
                    <label for="edit_bicycle_name">Name*</label>
                    <input type="text" id="edit_bicycle_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_bicycle_reichweite">Reichweite*</label>
                    <input type="text" id="edit_bicycle_reichweite" name="reichweite" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('editBicycleModal')">Abbrechen</button>
                    <button type="submit" class="btn-action btn-edit">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Bicycle Modal -->
    <div id="deleteBicycleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Dienstfahrrad löschen</h3>
                <button class="modal-close" onclick="closeModal('deleteBicycleModal')">&times;</button>
            </div>
            
            <p>Möchten Sie dieses Dienstfahrrad wirklich löschen?</p>
            
            <form method="post">
                <input type="hidden" name="action" value="delete_bicycle">
                <input type="hidden" id="delete_bicycle_id" name="id">
                
                <div class="modal-actions">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('deleteBicycleModal')">Abbrechen</button>
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
        
        // Car modal functions
        function openAddCarModal() {
            openModal('addCarModal');
        }
        
        function openEditCarModal(id, name, kennzeichen, reichweite) {
            document.getElementById('edit_car_id').value = id;
            document.getElementById('edit_car_name').value = name;
            document.getElementById('edit_car_kennzeichen').value = kennzeichen;
            document.getElementById('edit_car_reichweite').value = reichweite;
            openModal('editCarModal');
        }
        
        function openDeleteCarModal(id) {
            document.getElementById('delete_car_id').value = id;
            openModal('deleteCarModal');
        }
        
        // Bicycle modal functions
        function openAddBicycleModal() {
            openModal('addBicycleModal');
        }
        
        function openEditBicycleModal(id, name, reichweite) {
            document.getElementById('edit_bicycle_id').value = id;
            document.getElementById('edit_bicycle_name').value = name;
            document.getElementById('edit_bicycle_reichweite').value = reichweite;
            openModal('editBicycleModal');
        }
        
        function openDeleteBicycleModal(id) {
            document.getElementById('delete_bicycle_id').value = id;
            openModal('deleteBicycleModal');
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