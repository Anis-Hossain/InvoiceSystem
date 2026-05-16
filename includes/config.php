<?php
// =====================================================
// Database Configuration
// =====================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Default XAMPP user
define('DB_PASS', '');            // Default XAMPP password (empty)
define('DB_NAME', 'invoice_management');

define('APP_NAME', 'Invoice Manager');
define('CURRENCY', '$');
define('CURRENCY_CODE', 'USD');

// ---- Connect ----
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('
    <div style="font-family:sans-serif;padding:40px;background:#fff0f0;border:1px solid #f00;border-radius:8px;max-width:500px;margin:80px auto;">
        <h2 style="color:#c00">⚠ Database Connection Failed</h2>
        <p>' . htmlspecialchars($conn->connect_error) . '</p>
        <hr>
        <small>Make sure XAMPP MySQL is running and you have imported <b>database.sql</b> via phpMyAdmin.</small>
    </div>');
}

$conn->set_charset('utf8mb4');

// ---- Helpers ----
function money($amount) {
    return CURRENCY . number_format((float)$amount, 2);
}

function statusBadge($status) {
    $map = [
        'draft'     => ['#6c757d', '📝 Draft'],
        'sent'      => ['#0d6efd', '📤 Sent'],
        'paid'      => ['#198754', '✅ Paid'],
        'overdue'   => ['#dc3545', '⏰ Overdue'],
        'cancelled' => ['#6c757d', '🚫 Cancelled'],
    ];
    [$color, $label] = $map[$status] ?? ['#999', ucfirst($status)];
    return "<span class='badge' style='background:{$color}'>{$label}</span>";
}

function sanitize($conn, $val) {
    return $conn->real_escape_string(trim($val));
}

function nextInvoiceNumber($conn) {
    $year = date('Y');
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM invoices WHERE invoice_number LIKE 'INV-{$year}-%'");
    $row = $res->fetch_assoc();
    $next = str_pad($row['cnt'] + 1, 3, '0', STR_PAD_LEFT);
    return "INV-{$year}-{$next}";
}

// Auto-update overdue invoices
$conn->query("UPDATE invoices SET status='overdue' WHERE due_date < CURDATE() AND status='sent'");

// =====================================================
// Session & Authentication
// =====================================================
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Redirect to login if not authenticated.
 * Call at the top of every protected page.
 */
function requireLogin() {
    global $conn;
    if (empty($_SESSION['user_id'])) {
        $root = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2);
        header('Location: ' . $root . 'login.php?timeout=1');
        exit;
    }
    // Re-sync client IDs if not yet loaded into this session
    if (!isset($_SESSION['client_ids'])) {
        loadUserClientIds($conn);
    }
}

/** Returns true if the logged-in user is an admin. */
function isAdmin() {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

/** Returns true if the logged-in user is a company user. */
function isCompany() {
    return ($_SESSION['user_role'] ?? '') === 'company';
}

/**
 * Returns the array of client IDs the current user is linked to.
 * Admins get an empty array (meaning: no restriction).
 */
function getUserClientIds() {
    return $_SESSION['client_ids'] ?? [];
}

/**
 * For company users: returns a SQL WHERE clause fragment that
 * restricts queries to the user's linked client IDs.
 * For admins: returns empty string (no restriction).
 *
 * Usage:  "WHERE 1=1 " . clientScope('i')
 */
function clientScope($tableAlias = 'i') {
    if (isAdmin()) return '';
    $ids = getUserClientIds();
    if (empty($ids)) return ' AND 1=0'; // no linked clients → see nothing
    $in = implode(',', array_map('intval', $ids));
    return " AND {$tableAlias}.client_id IN ({$in})";
}

/**
 * Load and cache the linked client IDs for the logged-in user
 * into $_SESSION['client_ids']. Call once after login or when needed.
 */
function loadUserClientIds($conn) {
    if (isAdmin()) { $_SESSION['client_ids'] = []; return; }
    $uid = (int)$_SESSION['user_id'];
    $res = $conn->query("SELECT client_id FROM user_clients WHERE user_id=$uid");
    $ids = [];
    while ($r = $res->fetch_assoc()) $ids[] = (int)$r['client_id'];
    $_SESSION['client_ids'] = $ids;
}

/** Redirect admin-only pages for company users. */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ../index.php?error=unauthorized');
        exit;
    }
}
?>
