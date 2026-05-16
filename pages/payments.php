<?php
require_once '../includes/config.php';
$page_title = 'Payments';
requireLogin();
$base = '../';

// ---- DELETE ----
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM payments WHERE id=$id");
    header('Location: payments.php?success=deleted'); exit;
}

// ---- SAVE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id    = (int)$_POST['invoice_id'];
    $amount        = (float)$_POST['amount'];
    $payment_date  = sanitize($conn, $_POST['payment_date']);
    $method        = sanitize($conn, $_POST['method']);
    $reference     = sanitize($conn, $_POST['reference'] ?? '');
    $notes         = sanitize($conn, $_POST['notes'] ?? '');

    $conn->query("INSERT INTO payments (invoice_id,amount,payment_date,method,reference,notes)
                  VALUES ($invoice_id,$amount,'$payment_date','$method','$reference','$notes')");

    // Check if fully paid
    $inv = $conn->query("SELECT total FROM invoices WHERE id=$invoice_id")->fetch_assoc();
    $paid = $conn->query("SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE invoice_id=$invoice_id")->fetch_assoc()['s'];
    if ($paid >= $inv['total']) {
        $conn->query("UPDATE invoices SET status='paid' WHERE id=$invoice_id");
    }

    header('Location: payments.php?success=saved'); exit;
}

// Pre-fill invoice if coming from invoice view
$preselect_invoice = (int)($_GET['invoice_id'] ?? 0);

// Invoices for dropdown
$inv_role_scope = clientScope('i');
$invoices = $conn->query("SELECT i.id, i.invoice_number, i.total, c.name AS client_name
                           FROM invoices i JOIN clients c ON c.id=i.client_id
                           WHERE i.status IN ('sent','overdue','draft') $inv_role_scope
                           ORDER BY i.invoice_number DESC");

// All payments
$search = sanitize($conn, $_GET['search'] ?? '');
$where  = $search ? "AND (i.invoice_number LIKE '%$search%' OR c.name LIKE '%$search%')" : '';
// scope by role (uses multi-client IN(...) for company users)
$role_scope = clientScope('i');
$payments = $conn->query("SELECT p.*, i.invoice_number, c.name AS client_name
                           FROM payments p
                           JOIN invoices i ON i.id=p.invoice_id
                           JOIN clients c ON c.id=i.client_id
                           WHERE 1=1 $where $role_scope
                           ORDER BY p.payment_date DESC, p.id DESC");

// Total collected
if (isAdmin()) {
    $total_collected = $conn->query("SELECT COALESCE(SUM(amount),0) AS s FROM payments")->fetch_assoc()['s'];
} else {
    $tc_scope = clientScope('i');
    $total_collected = $conn->query("SELECT COALESCE(SUM(p.amount),0) AS s FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE 1=1 $tc_scope")->fetch_assoc()['s'];
}

require_once '../includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">✅ Payment <?= $_GET['success']==='deleted'?'deleted':'recorded' ?> successfully.</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:22px;">
    <div class="stat-card green">
        <div class="stat-icon">💰</div>
        <div class="stat-label">Total Collected</div>
        <div class="stat-value"><?= money($total_collected) ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">📋</div>
        <div class="stat-label">Payment Records</div>
        <div class="stat-value"><?= $payments->num_rows ?></div>
    </div>
</div>

<!-- Form -->
<div class="panel mb-20">
    <div class="panel-header"><span class="panel-title">➕ Record Payment</span></div>
    <div class="panel-body">
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>Invoice *</label>
                <select name="invoice_id" required>
                    <option value="">— Select Invoice —</option>
                    <?php while ($inv = $invoices->fetch_assoc()): ?>
                    <option value="<?= $inv['id'] ?>" <?= $preselect_invoice===$inv['id']?'selected':'' ?>>
                        <?= htmlspecialchars($inv['invoice_number']) ?> — <?= htmlspecialchars($inv['client_name']) ?> (<?= money($inv['total']) ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Amount (<?= CURRENCY ?>) *</label>
                <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label>Payment Date *</label>
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Payment Method *</label>
                <select name="method">
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="cheque">Cheque</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reference / Transaction ID</label>
                <input type="text" name="reference" placeholder="e.g. TXN-12345">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <input type="text" name="notes" placeholder="Optional notes">
            </div>
        </div>
        <div class="mt-20">
            <button type="submit" class="btn btn-success">💳 Record Payment</button>
        </div>
    </form>
    </div>
</div>

<!-- Payments List -->
<div class="panel">
    <div class="panel-header"><span class="panel-title">💳 Payment History</span></div>
    <div class="panel-body" style="padding-bottom:0;">
        <form method="GET" class="search-bar">
            <input type="text" name="search" placeholder="🔍 Search invoice or client…" value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary">Search</button>
            <a href="payments.php" class="btn btn-outline">Clear</a>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Date</th><th>Invoice</th><th>Client</th><th>Amount</th><th>Method</th><th>Reference</th><th>Notes</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php
            $payments->data_seek(0);
            if ($payments->num_rows === 0): ?>
                <tr><td colspan="8"><div class="empty-state"><div class="empty-icon">💸</div>No payments recorded yet.</div></td></tr>
            <?php else: while ($p = $payments->fetch_assoc()): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                    <td><a href="view_invoice.php?id=<?= $p['invoice_id'] ?>" style="color:var(--primary);font-weight:600;"><?= htmlspecialchars($p['invoice_number']) ?></a></td>
                    <td><?= htmlspecialchars($p['client_name']) ?></td>
                    <td style="font-weight:700;color:var(--success);"><?= money($p['amount']) ?></td>
                    <td><?= ucfirst(str_replace('_',' ',$p['method'])) ?></td>
                    <td><?= htmlspecialchars($p['reference'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($p['notes'] ?: '—') ?></td>
                    <td><a href="payments.php?delete=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Delete this payment record?')">🗑</a></td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
