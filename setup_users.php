<?php
// =====================================================
// setup_users.php
// Run this ONCE in your browser after importing database.sql
// Then DELETE this file from your server.
//   http://localhost/invoice_system/setup_users.php
// =====================================================
require_once 'includes/config.php';

$existing = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
if ($existing > 0) {
    die('<div style="font-family:sans-serif;padding:40px;max-width:500px;margin:60px auto;background:#fef9c3;border:1px solid #fde047;border-radius:12px;">
        <h2>⚠️ Already set up</h2>
        <p>Users already exist. This script has already been run.</p>
        <p><strong>Please delete this file: <code>setup_users.php</code></strong></p>
        <a href="login.php" style="color:#4f46e5;">→ Go to Login</a>
    </div>');
}

// user_name, email, password, role, linked_client_ids[]
$users = [
    ['Administrator', 'admin@invoice.com',  'Admin1234!',  'admin',   []],
    ['John Smith',    'john@example.com',   'Company123!', 'company', [1]],
    ['Sara Ahmed',    'sara@techbd.com',    'Company123!', 'company', [2]],
    ['James Lee',     'james@leeco.io',     'Company123!', 'company', [3]],
    // Example: a user linked to multiple clients:
    // ['Multi User', 'multi@example.com', 'Company123!', 'company', [1, 2]],
];

$ok = 0;
foreach ($users as [$name, $email, $pass, $role, $client_ids]) {
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $conn->prepare("INSERT IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $name, $email, $hash, $role);
    if ($stmt->execute() && $conn->insert_id) {
        $uid = $conn->insert_id;
        foreach ($client_ids as $cid) {
            $conn->query("INSERT IGNORE INTO user_clients (user_id, client_id) VALUES ($uid, $cid)");
        }
        $ok++;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Setup Complete</title>
<style>
body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; display:flex; align-items:center; justify-content:center; min-height:100vh; }
.card { background:#fff; border-radius:16px; padding:40px; max-width:560px; width:100%; box-shadow:0 8px 30px rgba(0,0,0,.1); }
h2 { color:#16a34a; margin-bottom:10px; }
table { width:100%; border-collapse:collapse; margin:20px 0; font-size:13px; }
th { background:#f8fafc; padding:8px 12px; text-align:left; border-bottom:2px solid #e2e8f0; }
td { padding:8px 12px; border-bottom:1px solid #f1f5f9; }
.warn { background:#fef2f2; border:1px solid #fca5a5; border-radius:10px; padding:14px; color:#dc2626; margin-top:20px; font-size:13px; }
.btn { display:inline-block; margin-top:20px; padding:12px 24px; background:#4f46e5; color:#fff; border-radius:8px; text-decoration:none; font-weight:700; }
</style>
</head>
<body>
<div class="card">
    <h2>✅ Setup Complete</h2>
    <p><?= $ok ?> user(s) created successfully.</p>
    <table>
        <tr><th>Email</th><th>Password</th><th>Role</th><th>Linked Clients</th></tr>
        <tr><td>admin@invoice.com</td><td>Admin1234!</td><td>Admin</td><td>All</td></tr>
        <tr><td>john@example.com</td><td>Company123!</td><td>Company</td><td>Client #1</td></tr>
        <tr><td>sara@techbd.com</td><td>Company123!</td><td>Company</td><td>Client #2</td></tr>
        <tr><td>james@leeco.io</td><td>Company123!</td><td>Company</td><td>Client #3</td></tr>
    </table>
    <div class="warn">
        🔒 <strong>Security:</strong> Delete <code>setup_users.php</code> from your server now.
    </div>
    <a href="login.php" class="btn">→ Go to Login</a>
</div>
</body>
</html>
