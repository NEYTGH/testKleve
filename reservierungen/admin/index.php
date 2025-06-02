<?php
// Start session
session_start();

// Include required files
require_once('../../wp-load.php'); //Anpassen richtiger Pfad
require_once('auth_functions.php');

if (!isset($_SESSION['admin_logged_in'])) {
    if (auto_login_via_ldap()) {
        header('Location: dashboard.php');
        exit;
    }
}

// Check if user is trying to log out
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Clear admin session data
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_display_name']);
    unset($_SESSION['admin_permissions']);
    
    // Redirect to login page
    header('Location: index.php');
    exit;
}

// Check if user is already logged in
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if ($is_logged_in) {
    // Redirect to dashboard if already logged in
    header('Location: dashboard.php');
    exit;
}

// Process login form
$error_message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Try to authenticate with our admin system
    $user = authenticate_admin($username, $password);
    
    if ($user) {
        // Set session variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_display_name'] = $user['display_name'];
        $_SESSION['admin_permissions'] = $user['permissions'];
        
        // Redirect to admin dashboard
        header('Location: dashboard.php');
        exit;
    } else {
        $error_message = 'Ungültiger Benutzername oder Passwort.';
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Kreis Kleve</title>
    <link rel="stylesheet" href="../style.css">
    <style>
		header {
			position: sticky;
			top: 0;
			z-index: 1000; /* Damit er über anderen Elementen liegt */
		}
        .login-container {
            max-width: 400px;
            margin: 0 auto;
            padding: 2rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h2 {
            margin-bottom: 0.5rem;
        }
        
        .login-form .form-group {
            margin-bottom: 1.5rem;
        }
        
        .login-form label {
            font-weight: 600;
        }
        
        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--color-gray-300);
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .login-form input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        .login-form button {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            background-color: var(--color-primary);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .login-form button:hover {
            background-color: var(--color-primary-dark);
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--color-primary);
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Kreis Kleve - Administration</h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Raumreservierung</a></li>
                    <li><a href="../edv-ressourcen.php">EDV-Ressourcen-Reservierung</a></li>
                    <li><a href="../dienstwagen.php">Dienstwagen-Reservierung</a></li>
                    <li><a href="../dienstfahrrad.php">Dienstfahrrad-Reservierung</a></li>
                    <li><a href="../rollup-praesentationsstand.php">Roll-Ups & Präsentationsstand</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <div class="login-container">
                <div class="login-header">
                    <h2>Admin-Bereich</h2>
                    <p>Bitte melden Sie sich an, um fortzufahren</p>
                </div>
                
                <?php if ($error_message): ?>
                <div class="message error">
                    <span class="icon">!</span>
                    <div class="message-content">
                        <h3>Fehler</h3>
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <form class="login-form" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="username">Benutzername</label>
                        <input type="text" id="username" name="username" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Passwort</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit">Anmelden</button>
                    </div>
                </form>
                
                <a href="../index.php" class="back-link">Zurück zur Hauptseite</a>
            </div>
        </main>

        <footer>
            <p>© <?php echo date('Y'); ?> Kreis Kleve | Reservierungssystem</p>
        </footer>
    </div>
</body>
</html>