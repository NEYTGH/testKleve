<?php

require_once('db-config.php');

/**
 * Check if a user has permission to manage a specific reservation type
 * 
 * @param string $username The username
 * @param string $type The reservation type
 * @return bool True if user has permission, false otherwise
 */
function user_has_permission($username, $type) {
    global $db_hostname, $db_username, $db_passwort, $db_datenbankname;
    
    try {
        $db_conn = new PDO("mysql:host=$db_hostname;dbname=$db_datenbankname;charset=utf8", $db_username, $db_passwort);
        $db_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get all permissions for the user
        $query = "SELECT p.name 
                  FROM admin_users u
                  JOIN admin_user_permissions up ON u.id = up.user_id
                  JOIN permissions p ON up.permission_id = p.id
                  WHERE u.username = :username";
        
        $stmt = $db_conn->prepare($query);
        $stmt->execute(['username' => $username]);
        
        $permissions = array_column($stmt->fetchAll(), 'name');
        
        // Check if user has the specific permission
        return in_array($type, $permissions);
    } catch (PDOException $e) {
        error_log("Error checking permissions: " . $e->getMessage());
        return false;
    }
}

/**
 * Authenticate admin user and retrieve all their permissions
 * 
 * @param string $username The username
 * @param string $password The password
 * @return bool|array False on failure, user data with permissions on success
 */
function authenticate_admin($username, $password) {
    global $db_hostname, $db_username, $db_passwort, $db_datenbankname;
    
    try {
        $db_conn = new PDO("mysql:host=$db_hostname;dbname=$db_datenbankname;charset=utf8", $db_username, $db_passwort);
        $db_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get user data
        $query = "SELECT id, username, password_hash, display_name 
                  FROM admin_users 
                  WHERE username = :username";
        
        $stmt = $db_conn->prepare($query);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            // Get all user permissions
            $query = "SELECT p.name 
                      FROM permissions p
                      JOIN admin_user_permissions up ON p.id = up.permission_id
                      WHERE up.user_id = :user_id";
            
            $stmt = $db_conn->prepare($query);
            $stmt->execute(['user_id' => $user['id']]);
            $permissions = array_column($stmt->fetchAll(), 'name');
            
            return [
                'username' => $user['username'],
                'display_name' => $user['display_name'],
                'permissions' => $permissions
            ];
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

function auto_login_via_ldap() {
    $config = require __DIR__ . '/ldap-config.php';
    $ldap_server = $config['ldap_server'];
    $ldap_domain = $config['ldap_domain'];
    $ldap_base_dn = $config['ldap_base_dn'];
    $ldap_group_mapping = $config['ldap_group_mapping'];

    $username = $_SERVER['REMOTE_USER'] ?? null;
    if (!$username) {
        return false;
    }

    if (strpos($username, '\\') !== false) {
        $parts = explode('\\', $username);
        $username = $parts[1];
    } elseif (strpos($username, '@') !== false) {
        $parts = explode('@', $username);
        $username = $parts[0];
    }

    $ldap_conn = ldap_connect($ldap_server);
    if (!$ldap_conn) {
        return false;
    }

    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    if (!@ldap_bind($ldap_conn)) {
        return false;
    }

    $user_found = false;
    foreach ($ldap_group_mapping as $group => $permissions) {
        $group_dn = "CN=$group," . $ldap_base_dn;
        $filter = "(&(objectClass=user)(sAMAccountName=$username)(memberOf=$group_dn))";
        $search = ldap_search($ldap_conn, $ldap_base_dn, $filter);
        if ($search && ldap_count_entries($ldap_conn, $search) > 0) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_display_name'] = $username;
            $_SESSION['admin_permissions'] = $permissions;

            $user_found = true;
            break;
        }
    }

    ldap_unbind($ldap_conn);
    return $user_found;
}
