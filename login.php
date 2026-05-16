<?php
// =====================================================
// login.php  — Authentication entry point
// =====================================================
require_once 'includes/config.php';

// Already logged in → go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($conn, $_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, password, role, client_id, is_active FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'No account found with that email address.';
        } elseif (!$user['is_active']) {
            $error = 'Your account has been deactivated. Please contact the administrator.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Incorrect password. Please try again.';
        } else {
            // Successful login
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];

            // Load all linked client IDs into session
            loadUserClientIds($conn);

            header('Location: index.php'); exit;
        }
    }
}

$timeout = isset($_GET['timeout']);
$unauth  = isset($_GET['error']) && $_GET['error'] === 'unauthorized';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= APP_NAME ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:   #4f46e5;
            --primary-d: #3730a3;
            --danger:    #dc2626;
            --success:   #16a34a;
            --gray-100:  #f1f5f9;
            --gray-200:  #e2e8f0;
            --gray-400:  #94a3b8;
            --gray-600:  #475569;
            --gray-800:  #1e293b;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #312e81 60%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrap {
            display: flex;
            width: 100%;
            max-width: 900px;
            min-height: 520px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,.4);
        }

        /* ---- Left panel (branding) ---- */
        .login-brand {
            flex: 1;
            background: linear-gradient(160deg, #4f46e5 0%, #7c3aed 100%);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .login-brand::before {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            background: rgba(255,255,255,.05);
            border-radius: 50%;
            top: -80px; right: -80px;
        }
        .login-brand::after {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            background: rgba(255,255,255,.05);
            border-radius: 50%;
            bottom: -40px; left: -40px;
        }
        .brand-logo { font-size: 40px; margin-bottom: 12px; }
        .brand-name { font-size: 26px; font-weight: 800; margin-bottom: 6px; }
        .brand-tagline { font-size: 14px; opacity: .75; line-height: 1.5; }
        .brand-features { list-style: none; margin-top: 30px; display: flex; flex-direction: column; gap: 12px; }
        .brand-features li { display: flex; align-items: center; gap: 10px; font-size: 13px; opacity: .85; }
        .brand-features li span { font-size: 18px; }
        .brand-footer { font-size: 11px; opacity: .5; }

        /* ---- Right panel (form) ---- */
        .login-form-panel {
            width: 380px;
            background: #fff;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .form-heading { font-size: 22px; font-weight: 800; color: var(--gray-800); margin-bottom: 6px; }
        .form-subheading { font-size: 13px; color: var(--gray-400); margin-bottom: 28px; }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
        label { font-size: 12px; font-weight: 700; color: var(--gray-600); letter-spacing: .3px; }
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 15px; pointer-events: none; }
        input[type=email], input[type=password] {
            width: 100%; padding: 10px 12px 10px 36px;
            border: 1.5px solid var(--gray-200); border-radius: 9px;
            font-size: 14px; color: var(--gray-800); background: #fafafa;
            transition: border .2s, box-shadow .2s; outline: none;
            font-family: inherit;
        }
        input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(79,70,229,.1); }

        .toggle-pw {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; font-size: 16px; color: var(--gray-400);
        }

        .btn-login {
            width: 100%; padding: 12px;
            background: var(--primary); color: #fff;
            border: none; border-radius: 9px; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: background .2s, transform .1s;
            margin-top: 6px; font-family: inherit;
        }
        .btn-login:hover { background: var(--primary-d); }
        .btn-login:active { transform: scale(.98); }

        .alert {
            padding: 10px 14px; border-radius: 8px; font-size: 13px;
            margin-bottom: 18px; border-left: 4px solid;
        }
        .alert-error   { background: #fef2f2; border-color: var(--danger);  color: var(--danger); }
        .alert-warning { background: #fffbeb; border-color: #d97706; color: #92400e; }
        .alert-info    { background: #eff6ff; border-color: var(--primary);  color: #1d4ed8; }

        .divider { border: none; border-top: 1px solid var(--gray-200); margin: 22px 0; }

        .demo-creds { background: var(--gray-100); border-radius: 10px; padding: 14px 16px; font-size: 12px; color: var(--gray-600); }
        .demo-creds strong { color: var(--gray-800); display: block; margin-bottom: 8px; font-size: 12px; }
        .demo-row { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px dashed var(--gray-200); }
        .demo-row:last-child { border-bottom: none; }
        .demo-pill { display: inline-block; padding: 1px 7px; border-radius: 20px; font-size: 10px; font-weight: 700; color: #fff; margin-left: 4px; }
        .pill-admin { background: #7c3aed; }
        .pill-company { background: var(--primary); }

        .form-footer { text-align: center; margin-top: 20px; font-size: 12px; color: var(--gray-400); }

        @media (max-width: 680px) {
            .login-brand { display: none; }
            .login-form-panel { width: 100%; border-radius: 20px; }
        }
    </style>
</head>
<body>

<div class="login-wrap">

    <!-- Brand Side -->
    <div class="login-brand">
        <div>
            <div class="brand-logo">📄</div>
            <div class="brand-name"><?= APP_NAME ?></div>
            <div class="brand-tagline">Manage invoices, track payments, and stay on top of your finances — all in one place.</div>
            <ul class="brand-features">
                <li><span>📋</span> Create & manage invoices</li>
                <li><span>💳</span> Track payments in real-time</li>
                <li><span>👥</span> Client portal access</li>
                <li><span>📊</span> Dashboard analytics</li>
                <li><span>🔍</span> Invoice status lookup</li>
            </ul>
        </div>
        <div class="brand-footer">Invoice Manager v1.0 · Powered by PHP + MySQL</div>
    </div>

    <!-- Form Side -->
    <div class="login-form-panel">
        <div class="form-heading">Welcome back 👋</div>
        <div class="form-subheading">Sign in to your account to continue</div>

        <?php if ($timeout): ?>
        <div class="alert alert-warning">⏱ Your session expired. Please sign in again.</div>
        <?php endif; ?>

        <?php if ($unauth): ?>
        <div class="alert alert-warning">🔒 You don't have permission to access that page.</div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrap">
                    <span class="input-icon">✉️</span>
                    <input type="email" id="email" name="email" autocomplete="off" placeholder="you@company.com" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <span class="input-icon">🔑</span>
                    <input type="password" id="password" name="password" autocomplete="new-password" placeholder="••••••••" required>
                    <button type="button" class="toggle-pw" onclick="togglePw()" title="Show/hide password">👁</button>
                </div>
            </div>

            <button type="submit" class="btn-login">Sign In →</button>
        </form>

        <hr class="divider">

        <!-- Demo credentials hint -->
        <div class="demo-creds">
            <strong>🧪 Demo Credentials</strong>
            <div class="demo-row">
                <span>admin@invoice.com<span class="demo-pill pill-admin">Admin</span></span>
                <span>Admin1234!</span>
            </div>
            <div class="demo-row">
                <span>john@example.com<span class="demo-pill pill-company">Company</span></span>
                <span>Company123!</span>
            </div>
            <div class="demo-row">
                <span>sara@techbd.com<span class="demo-pill pill-company">Company</span></span>
                <span>Company123!</span>
            </div>
        </div>

        <div class="form-footer">
            &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
        </div>
    </div>
</div>

<script>
function togglePw() {
    const inp = document.getElementById('password');
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
