<?php
// File: login.php
// IMPORTANT: Absolutely NO whitespace or empty lines before this <?php tag

require_once __DIR__ . '/config/init.php';

// Already logged in? Redirect
if (isLoggedIn()) {
    if (isSuperAdmin()) {
        redirect(APP_URL . '/superadmin/dashboard.php');
    } else {
        redirect(APP_URL . '/center/dashboard.php');
    }
}

$error = '';
$loginUsername = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginUsername = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($loginUsername) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, name, username, password, role, center_id, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $loginUsername);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = 'Invalid username or password.';
        } else {
            $user = $result->fetch_assoc();
            
            if ($user['status'] === 'blocked') {
                $error = 'Your account is blocked. Contact Super Admin.';
            } elseif (!password_verify($password, $user['password'])) {
                $error = 'Invalid username or password.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_email'] = '';
                $_SESSION['role'] = $user['role'];
                $_SESSION['center_id'] = $user['center_id'];
                $_SESSION['last_activity'] = time();
                
                if ($user['role'] === 'super_admin') {
                    redirect(APP_URL . '/superadmin/dashboard.php');
                } else {
                    redirect(APP_URL . '/center/dashboard.php');
                }
            }
        }
        
        $stmt->close();
        $conn->close();
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e3a5f 0%, #2596be 50%, #1abc9c 100%);
            padding: 20px;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 440px;
            padding: 48px 40px;
            animation: slideUp 0.6s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-logo { text-align: center; margin-bottom: 36px; }
        .logo-icon {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, #2596be, #1e3a5f);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 800; font-size: 28px;
            margin: 0 auto 16px;
        }
        .login-logo h1 { font-size: 32px; font-weight: 800; color: #2596be; letter-spacing: 4px; margin-bottom: 6px; }
        .login-logo p { font-size: 13px; color: #6c757d; }
        .form-label { font-weight: 600; font-size: 12px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .input-group { margin-bottom: 20px; }
        .input-group-text { background: #f4f6f9; border: 2px solid #e0e0e0; border-right: none; color: #6c757d; }
        .form-control { padding: 12px 16px; border: 2px solid #e0e0e0; border-left: none; font-size: 14px; }
        .form-control:focus { border-color: #2596be; box-shadow: 0 0 0 3px rgba(37,150,190,0.15); }
        .input-group:focus-within .input-group-text { border-color: #2596be; color: #2596be; }
        .btn-login {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, #2596be, #1e7a9e);
            border: none; border-radius: 8px; color: #fff;
            font-size: 15px; font-weight: 600; letter-spacing: 1px;
            text-transform: uppercase; cursor: pointer; transition: all 0.3s ease; margin-top: 10px;
        }
        .btn-login:hover { background: linear-gradient(135deg, #1e7a9e, #1e3a5f); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(37,150,190,0.4); }
        .alert-box { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: 500; }
        .alert-danger-box { background: rgba(220,53,69,0.1); border-left: 4px solid #dc3545; color: #dc3545; }
        .alert-success-box { background: rgba(40,167,69,0.1); border-left: 4px solid #28a745; color: #28a745; }
        .alert-warning-box { background: rgba(255,193,7,0.1); border-left: 4px solid #ffc107; color: #cc9a00; }
        .credentials-box { background: #f4f6f9; border-radius: 8px; padding: 16px; margin-top: 24px; border-top: 1px solid #e0e0e0; }
        .credentials-box p { font-size: 12px; color: #6c757d; margin-bottom: 8px; text-align: center; }
        .cred-item { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; border-bottom: 1px solid #e9ecef; }
        .cred-item:last-child { border-bottom: none; }
        .cred-item strong { color: #2596be; }
        .cred-item code { background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
        @media (max-width: 575px) { .login-card { padding: 32px 24px; } .login-logo h1 { font-size: 26px; } }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-logo">
        <div class="logo-icon">O</div>
        <h1><?php echo APP_NAME; ?></h1>
        <p><?php echo APP_TAGLINE; ?></p>
    </div>
    
    <?php if ($flash): ?>
    <div class="alert-box alert-<?php echo $flash['type']; ?>-box">
        <i class="fas fa-info-circle me-2"></i>
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert-box alert-danger-box">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <label class="form-label">Username</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-user"></i></span>
            <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus value="<?php echo htmlspecialchars($loginUsername); ?>">
        </div>
        
        <label class="form-label">Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" name="password" class="form-control" placeholder="Enter password" required id="loginPassword">
            <span class="input-group-text" style="cursor:pointer;border-left:none;" onclick="togglePwd()">
                <i class="fas fa-eye" id="eyeIcon"></i>
            </span>
        </div>
        
        <button type="submit" class="btn-login">
            <i class="fas fa-sign-in-alt me-2"></i> Sign In
        </button>
    </form>
    
    <div class="text-center mt-4">
        <small style="color:#999;">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</small>
    </div>
</div>

<script>
function togglePwd() {
    var p = document.getElementById('loginPassword');
    var i = document.getElementById('eyeIcon');
    if (p.type === 'password') { p.type = 'text'; i.className = 'fas fa-eye-slash'; }
    else { p.type = 'password'; i.className = 'fas fa-eye'; }
}
</script>
</body>
</html>