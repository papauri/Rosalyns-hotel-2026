<?php
/**
 * Admin Login Page
 * Simple session-based authentication
 */

// Include base URL override (if configured) before auto-detection
$override_file = __DIR__ . '/../config/base-url-override.php';
if (file_exists($override_file)) {
    require_once $override_file;
}

// Include base URL configuration for proper redirects
require_once __DIR__ . '/../config/base-url.php';

// Start session
session_start();

// Check if already logged in
if (isset($_SESSION['admin_user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once '../config/database.php';

$error_message = '';

// Ensure admin_activity_log table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        username VARCHAR(100) NULL,
        action VARCHAR(50) NOT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(500) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // Table likely already exists
}

// Max failed attempts before temporary lockout
$max_attempts = 5;
$lockout_minutes = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    if ($username && $password) {
        try {
            // Check for IP-based rate limiting (too many failed attempts from this IP)
            $rate_stmt = $pdo->prepare("
                SELECT COUNT(*) FROM admin_activity_log 
                WHERE ip_address = ? AND action = 'login_failed' 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ");
            $rate_stmt->execute([$ip, $lockout_minutes]);
            $recent_ip_failures = $rate_stmt->fetchColumn();
            
            if ($recent_ip_failures >= ($max_attempts * 2)) {
                $error_message = 'Too many login attempts from this location. Please try again in ' . $lockout_minutes . ' minutes.';
                
                // Log the blocked attempt
                $log_stmt = $pdo->prepare("INSERT INTO admin_activity_log (username, action, details, ip_address, user_agent) VALUES (?, 'login_blocked', ?, ?, ?)");
                $log_stmt->execute([$username, 'IP rate limit exceeded (' . $recent_ip_failures . ' attempts)', $ip, $ua]);
            } else {
                $stmt = $pdo->prepare("SELECT id, username, password_hash, role, full_name, email, failed_login_attempts, is_active FROM admin_users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && !$user['is_active']) {
                    $error_message = 'This account has been deactivated. Contact your administrator.';
                    
                    $log_stmt = $pdo->prepare("INSERT INTO admin_activity_log (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, 'login_failed', ?, ?, ?)");
                    $log_stmt->execute([$user['id'], $username, 'Account deactivated', $ip, $ua]);
                    
                } elseif ($user && $user['failed_login_attempts'] >= $max_attempts) {
                    // Check if lockout period has passed by looking at last failed attempt
                    $last_fail = $pdo->prepare("
                        SELECT created_at FROM admin_activity_log 
                        WHERE user_id = ? AND action = 'login_failed' 
                        ORDER BY created_at DESC LIMIT 1
                    ");
                    $last_fail->execute([$user['id']]);
                    $last_fail_time = $last_fail->fetchColumn();
                    
                    if ($last_fail_time && strtotime($last_fail_time) > strtotime("-{$lockout_minutes} minutes")) {
                        $remaining = $lockout_minutes - floor((time() - strtotime($last_fail_time)) / 60);
                        $error_message = 'Account temporarily locked due to too many failed attempts. Try again in ' . max(1, $remaining) . ' minute(s).';
                        
                        $log_stmt = $pdo->prepare("INSERT INTO admin_activity_log (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, 'login_blocked', ?, ?, ?)");
                        $log_stmt->execute([$user['id'], $username, 'Account locked (' . $user['failed_login_attempts'] . ' failed attempts)', $ip, $ua]);
                    } else {
                        // Lockout expired, reset counter and allow attempt
                        $pdo->prepare("UPDATE admin_users SET failed_login_attempts = 0 WHERE id = ?")->execute([$user['id']]);
                        $user['failed_login_attempts'] = 0;
                        // Fall through to normal verification below
                        goto verify_password;
                    }
                } else {
                    verify_password:
                    if ($user && password_verify($password, $user['password_hash'])) {
                        // Successful login
                        $_SESSION['admin_user_id'] = $user['id'];
                        $_SESSION['admin_username'] = $user['username'];
                        $_SESSION['admin_role'] = $user['role'];
                        $_SESSION['admin_full_name'] = $user['full_name'];
                        
                        $_SESSION['admin_user'] = [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'role' => $user['role'],
                            'full_name' => $user['full_name']
                        ];
                        
                        // Reset failed attempts and update last_login
                        $pdo->prepare("UPDATE admin_users SET failed_login_attempts = 0, last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                        
                        // Log successful login
                        $log_stmt = $pdo->prepare("INSERT INTO admin_activity_log (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, 'login_success', ?, ?, ?)");
                        $log_stmt->execute([$user['id'], $user['username'], 'Role: ' . $user['role'], $ip, $ua]);

                        header('Location: dashboard.php');
                        exit;
                    } else {
                        // Failed login
                        $attempts = 0;
                        if ($user) {
                            $attempts = $user['failed_login_attempts'] + 1;
                            $pdo->prepare("UPDATE admin_users SET failed_login_attempts = ? WHERE id = ?")->execute([$attempts, $user['id']]);
                            
                            $remaining = $max_attempts - $attempts;
                            $detail = 'Wrong password (attempt ' . $attempts . '/' . $max_attempts . ')';
                            
                            $log_stmt = $pdo->prepare("INSERT INTO admin_activity_log (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, 'login_failed', ?, ?, ?)");
                            $log_stmt->execute([$user['id'], $username, $detail, $ip, $ua]);
                            
                            if ($remaining > 0 && $remaining <= 2) {
                                $error_message = 'Invalid username or password. ' . $remaining . ' attempt(s) remaining before lockout.';
                            } elseif ($remaining <= 0) {
                                $error_message = 'Account locked for ' . $lockout_minutes . ' minutes due to too many failed attempts.';
                            } else {
                                $error_message = 'Invalid username or password.';
                            }
                        } else {
                            // Unknown username
                            $log_stmt = $pdo->prepare("INSERT INTO admin_activity_log (username, action, details, ip_address, user_agent) VALUES (?, 'login_failed', 'Unknown username', ?, ?)");
                            $log_stmt->execute([$username, $ip, $ua]);
                            
                            $error_message = 'Invalid username or password.';
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = 'Login error. Please try again.';
        }
    } else {
        $error_message = 'Please enter both username and password.';
    }
}

$site_name = getSetting('site_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | <?php echo htmlspecialchars($site_name); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-auth.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-hotel"></i>
                </div>
                <h1>Admin Portal</h1>
                <p><?php echo htmlspecialchars($site_name); ?></p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['reset']) && $_GET['reset'] === 'sent'): ?>
                <div class="alert-success">
                    Password reset link sent to your email.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                <div class="alert-success">
                    Password reset successfully. Please log in.
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" class="form-control" 
                               placeholder="Enter your username" required autofocus
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="login-footer">
                <a href="forgot-password.php">
                    <i class="fas fa-key"></i> Forgot Password?
                </a>
                <a href="../index.php">
                    <i class="fas fa-arrow-left"></i> Back to Website
                </a>
            </div>
        </div>
    </div>
    
    <script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    </script>
</body>
</html>
