<?php
require_once '../includes/config.php';
$page_title = 'Invoices';
requireLogin();
$scope_join = clientScope('i');
$base = '../';
$msg = '';

// ---- DELETE ----
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM invoices WHERE id=$id");
    header('Location: invoices.php?success=deleted'); exit;
}

// ---- SAVE (create/edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    if (isAdmin()) {
        $client_id = (int)$_POST['client_id'];
    } else {
        $posted_cid = (int)$_POST['client_id'];
        $allowed    = getUserClientIds();
        // Security: ensure company user can only submit a client they're linked to
        $client_id  = in_array($posted_cid, $allowed) ? $posted_cid : (int)($allowed[0] ?? 0);
    }
    $inv_no      = sanitize($conn, $_POST['invoice_number']);
    $issue_date  = sanitize($conn, $_POST['issue_date']);
    $due_date    = sanitize($conn, $_POST['due_date']);
    $status      = sanitize($conn, $_POST['status']);
    $tax_rate    = (float)$_POST['tax_rate'];
    $discount    = (float)$_POST['discount'];
    $subtotal    = (float)$_POST['subtotal_hidden'];
    $tax_amount  = (float)$_POST['tax_amount_hidden'];
    $total       = (float)$_POST['total_hidden'];
    $notes       = sanitize($conn, $_POST['notes'] ?? '');

    if ($id === 0) {
        $conn->query("INSERT INTO invoices (invoice_number,client_id,issue_date,due_date,status,subtotal,tax_rate,tax_amount,discount,total,notes)
                      VALUES ('$inv_no',$client_id,'$issue_date','$due_date','$status',$subtotal,$tax_rate,$tax_amount,$discount,$total,'$notes')");
        $id = $conn->insert_id;
    } else {
        $conn->query("UPDATE invoices SET invoice_number='$inv_no',client_id=$client_id,issue_date='$issue_date',
                      due_date='$due_date',status='$status',subtotal=$subtotal,tax_rate=$tax_rate,tax_amount=$tax_amount,
                      discount=$discount,total=$total,notes='$notes' WHERE id=$id");
        $conn->query("DELETE FROM invoice_items WHERE invoice_id=$id");
    }

    // Items
    if (!empty($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            $desc  = sanitize($conn, $item['description']);
            $qty   = (float)$item['quantity'];
            $price = (float)$item['unit_price'];
            $itot  = (float)$item['total'];
            $conn->query("INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,total)
                          VALUES ($id,'$desc',$qty,$price,$itot)");
        }
    }

    header("Location: view_invoice.php?id=$id&success=saved"); exit;
}

// ---- FILTERS ----
$search = sanitize($conn, $_GET['search'] ?? '');
$filter_status = sanitize($conn, $_GET['status'] ?? '');
$where = "WHERE 1=1";
if ($search) $where .= " AND (i.invoice_number LIKE '%$search%' OR c.name LIKE '%$search%')";
if ($filter_status) $where .= " AND i.status='$filter_status'";

// company users can only see invoices for their linked clients; admins see all
if (!isAdmin()) {
    $where .= clientScope('i');
}
$invoices = $conn->query("SELECT i.*, c.name AS client_name FROM invoices i JOIN clients c ON c.id=i.client_id $where ORDER BY i.created_at DESC");

// Admins see all clients; company users only see their linked clients
if (isAdmin()) {
    $clients = $conn->query("SELECT id, name, company FROM clients ORDER BY name");
} else {
    $ids = getUserClientIds();
    if (empty($ids)) {
        $clients = $conn->query("SELECT id, name, company FROM clients WHERE 1=0"); // none
    } else {
        $in = implode(',', array_map('intval', $ids));
        $clients = $conn->query("SELECT id, name, company FROM clients WHERE id IN ($in) ORDER BY name");
    }
}

// Edit?
$edit = null;
$edit_items = [];
if (isset($_GET['edit'])) {
    $eid     = (int)$_GET['edit'];
    $scope_e = clientScope('invoices');   // uses IN(...) from session client_ids array
    $res     = $conn->query("SELECT * FROM invoices WHERE id=$eid $scope_e");
    $edit    = $res->fetch_assoc();
    $ires    = $conn->query("SELECT * FROM invoice_items WHERE invoice_id=$eid");
    while ($r = $ires->fetch_assoc()) $edit_items[] = $r;
}

require_once '../includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">✅ Invoice <?= $_GET['success']==='deleted'?'deleted':'saved' ?> successfully.</div>
<?php endif; ?>

<!-- ======= FORM (Create / Edit) ======= -->
<div class="panel mb-20">
    <div class="panel-header">
        <span class="panel-title"><?= $edit ? '✏️ Edit Invoice #'.htmlspecialchars($edit['invoice_number']) : '➕ New Invoice' ?></span>
        <?php if ($edit): ?><a href="invoices.php" class="btn btn-outline btn-sm">Cancel</a><?php endif; ?>
    </div>
    <div class="panel-body">
    <form method="POST">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
        <input type="hidden" name="subtotal_hidden" id="subtotal_hidden" value="<?= $edit['subtotal'] ?? 0 ?>">
        <input type="hidden" name="tax_amount_hidden" id="tax_amount_hidden" value="<?= $edit['tax_amount'] ?? 0 ?>">
        <input type="hidden" name="total_hidden" id="total_hidden" value="<?= $edit['total'] ?? 0 ?>">

        <div class="form-grid">
            <div class="form-group">
                <label>Invoice Number *</label>
                <input type="text" name="invoice_number" value="<?= htmlspecialchars($edit['invoice_number'] ?? nextInvoiceNumber($conn)) ?>" required>
            </div>
            <div class="form-group">
                <label>Client *</label>
                <?php
                $clients->data_seek(0);
                $client_rows = [];
                while ($c = $clients->fetch_assoc()) $client_rows[] = $c;

                if (isAdmin() || count($client_rows) > 1): ?>
                    <!-- Admin or multi-client user: show dropdown scoped to allowed clients -->
                    <select name="client_id" required>
                        <option value="">— Select client —</option>
                        <?php foreach ($client_rows as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($edit['client_id'] ?? '')==$c['id']?'selected':'' ?>>
                            <?= htmlspecialchars($c['name']) ?><?= $c['company'] ? ' — '.htmlspecialchars($c['company']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!isAdmin()): ?>
                    <span class="text-muted" style="font-size:11px;margin-top:2px;">🔒 Showing your linked clients only</span>
                    <?php endif; ?>
                <?php elseif (count($client_rows) === 1):
                    $own = $client_rows[0]; ?>
                    <!-- Single linked client: locked read-only -->
                    <input type="hidden" name="client_id" value="<?= $own['id'] ?>">
                    <input type="text"
                           value="<?= htmlspecialchars($own['name']) ?><?= !empty($own['company']) ? ' — '.htmlspecialchars($own['company']) : '' ?>"
                           disabled
                           style="background:var(--gray-100);color:var(--gray-600);cursor:not-allowed;">
                    <span class="text-muted" style="font-size:11px;margin-top:2px;">🔒 Invoices are linked to your company</span>
                <?php else: ?>
                    <!-- No linked clients -->
                    <input type="text" value="No clients linked to your account" disabled
                           style="background:#fff0f0;color:var(--danger);cursor:not-allowed;">
                    <span style="color:var(--danger);font-size:11px;margin-top:2px;">⚠ Ask an administrator to link clients to your account.</span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Issue Date *</label>
                <input type="date" name="issue_date" value="<?= $edit['issue_date'] ?? date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Due Date *</label>
                <input type="date" name="due_date" value="<?= $edit['due_date'] ?? date('Y-m-d', strtotime('+14 days')) ?>" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['draft','sent','paid','overdue','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($edit['status'] ?? 'draft')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tax Rate (%)</label>
                <input type="number" id="tax_rate" name="tax_rate" value="<?= $edit['tax_rate'] ?? 0 ?>" min="0" step="0.01" oninput="updateTotals()">
            </div>
            <div class="form-group">
                <label>Discount (<?= CURRENCY ?>)</label>
                <input type="number" id="discount" name="discount" value="<?= $edit['discount'] ?? 0 ?>" min="0" step="0.01" oninput="updateTotals()">
            </div>
            <div class="form-group full">
                <label>Notes</label>
                <textarea name="notes"><?= htmlspecialchars($edit['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Items -->
        <hr>
        <div style="font-weight:700;margin-bottom:10px;">📦 Line Items</div>
        <div class="table-wrap">
        <table id="items-table">
            <thead><tr><th>Description</th><th style="width:110px">Qty</th><th style="width:130px">Unit Price</th><th style="width:120px">Total</th><th style="width:40px"></th></tr></thead>
            <tbody id="items-body"></tbody>
        </table>
        </div>
        <button type="button" class="btn btn-outline btn-sm mt-10" onclick="addItemRow()">＋ Add Item</button>

        <!-- Summary -->
        <div class="invoice-summary">
            <table>
                <tr><td>Subtotal</td><td class="text-right" id="subtotal_display"><?= money($edit['subtotal'] ?? 0) ?></td></tr>
                <tr><td>Tax</td><td class="text-right" id="tax_amount_display"><?= money($edit['tax_amount'] ?? 0) ?></td></tr>
                <tr><td>Discount</td><td class="text-right">- <?= money($edit['discount'] ?? 0) ?></td></tr>
                <tr class="total-row"><td><strong>Total</strong></td><td class="text-right"><strong id="total_display"><?= money($edit['total'] ?? 0) ?></strong></td></tr>
            </table>
        </div>

        <div class="mt-20 btn-group">
            <button type="submit" class="btn btn-primary">💾 Save Invoice</button>
            <a href="invoices.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
    </div>
</div>

<!-- Pre-fill edit items via JS -->
<?php if ($edit_items): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    <?php foreach ($edit_items as $item): ?>
    addItemRow(<?= json_encode($item['description']) ?>, <?= $item['quantity'] ?>, <?= $item['unit_price'] ?>);
    <?php endforeach; ?>
    updateTotals();
});
</script>
<?php else: ?>
<script>document.addEventListener('DOMContentLoaded', () => addItemRow());</script>
<?php endif; ?>

<!-- ======= Invoice List ======= -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">📋 All Invoices</span>
    </div>
    <div class="panel-body" style="padding-bottom:0;">
        <div class="search-bar">
            <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;">
                <input type="text" name="search" placeholder="🔍 Search invoice # or client…" value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;">
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (['draft','sent','paid','overdue','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">Filter</button>
                <a href="invoices.php" class="btn btn-outline">Clear</a>
            </form>
        </div>
    </div>
    <div class="table-wrap">
        <table id="invoice-table">
            <thead>
                <tr>
                    <th>#</th><th>Client</th><th>Issue Date</th><th>Due Date</th>
                    <th>Total</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($invoices->num_rows === 0): ?>
                <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📭</div>No invoices found.</div></td></tr>
            <?php else: while ($inv = $invoices->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                    <td><?= htmlspecialchars($inv['client_name']) ?></td>
                    <td><?= date('d M Y', strtotime($inv['issue_date'])) ?></td>
                    <td><?= date('d M Y', strtotime($inv['due_date'])) ?></td>
                    <td><?= money($inv['total']) ?></td>
                    <td><?= statusBadge($inv['status']) ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="view_invoice.php?id=<?= $inv['id'] ?>" class="btn btn-outline btn-sm">👁 View</a>
                            <a href="invoices.php?edit=<?= $inv['id'] ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
                            <a href="invoices.php?delete=<?= $inv['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Delete invoice <?= htmlspecialchars($inv['invoice_number']) ?>?')">🗑</a>
                        </div>
                    </td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
