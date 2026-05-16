<?php
require_once '../includes/config.php';
requireAdmin(); // admin only
$page_title = 'Clients';
$base = '../';

// ---- DELETE ----
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM clients WHERE id=$id");
    header('Location: clients.php?success=deleted'); exit;
}

// ---- SAVE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id'] ?? 0);
    $name    = sanitize($conn, $_POST['name']);
    $email   = sanitize($conn, $_POST['email']);
    $phone   = sanitize($conn, $_POST['phone'] ?? '');
    $company = sanitize($conn, $_POST['company'] ?? '');
    $address = sanitize($conn, $_POST['address'] ?? '');
    $city    = sanitize($conn, $_POST['city'] ?? '');
    $country = sanitize($conn, $_POST['country'] ?? '');

    if ($id === 0) {
        $conn->query("INSERT INTO clients (name,email,phone,company,address,city,country)
                      VALUES ('$name','$email','$phone','$company','$address','$city','$country')");
    } else {
        $conn->query("UPDATE clients SET name='$name',email='$email',phone='$phone',company='$company',
                      address='$address',city='$city',country='$country' WHERE id=$id");
    }
    header('Location: clients.php?success=saved'); exit;
}

// ---- FILTERS ----
$search = sanitize($conn, $_GET['search'] ?? '');
$where  = $search ? "WHERE name LIKE '%$search%' OR email LIKE '%$search%' OR company LIKE '%$search%'" : '';
$clients = $conn->query("SELECT *, (SELECT COUNT(*) FROM invoices WHERE client_id=clients.id) AS inv_count,
                          (SELECT COALESCE(SUM(total),0) FROM invoices WHERE client_id=clients.id) AS total_billed
                          FROM clients $where ORDER BY name");

// Edit?
$edit = null;
if (isset($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $edit = $conn->query("SELECT * FROM clients WHERE id=$eid")->fetch_assoc();
}

require_once '../includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">✅ Client <?= $_GET['success']==='deleted'?'deleted':'saved' ?> successfully.</div>
<?php endif; ?>

<!-- Form -->
<div class="panel mb-20">
    <div class="panel-header">
        <span class="panel-title"><?= $edit ? '✏️ Edit Client' : '➕ New Client' ?></span>
        <?php if ($edit): ?><a href="clients.php" class="btn btn-outline btn-sm">Cancel</a><?php endif; ?>
    </div>
    <div class="panel-body">
    <form method="POST">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
        <div class="form-grid">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($edit['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($edit['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Company</label>
                <input type="text" name="company" value="<?= htmlspecialchars($edit['company'] ?? '') ?>">
            </div>
            <div class="form-group full">
                <label>Address</label>
                <input type="text" name="address" value="<?= htmlspecialchars($edit['address'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>City</label>
                <input type="text" name="city" value="<?= htmlspecialchars($edit['city'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Country</label>
                <input type="text" name="country" value="<?= htmlspecialchars($edit['country'] ?? '') ?>">
            </div>
        </div>
        <div class="mt-20">
            <button type="submit" class="btn btn-primary">💾 Save Client</button>
            <a href="clients.php" class="btn btn-outline" style="margin-left:8px;">Cancel</a>
        </div>
    </form>
    </div>
</div>

<!-- List -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">👥 All Clients (<?= $clients->num_rows ?>)</span>
    </div>
    <div class="panel-body" style="padding-bottom:0;">
        <form method="GET" class="search-bar">
            <input type="text" name="search" placeholder="🔍 Search by name, email, company…" value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary" type="submit">Search</button>
            <a href="clients.php" class="btn btn-outline">Clear</a>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>City</th><th>Invoices</th><th>Total Billed</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if ($clients->num_rows === 0): ?>
                <tr><td colspan="8"><div class="empty-state"><div class="empty-icon">👤</div>No clients found.</div></td></tr>
            <?php else: while ($c = $clients->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                    <td><?= htmlspecialchars($c['company'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td><?= htmlspecialchars($c['phone'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($c['city'] ?: '—') ?></td>
                    <td><span class="badge" style="background:var(--info)"><?= $c['inv_count'] ?></span></td>
                    <td><?= money($c['total_billed']) ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="clients.php?edit=<?= $c['id'] ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
                            <a href="clients.php?delete=<?= $c['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Delete client <?= htmlspecialchars(addslashes($c['name'])) ?>? All their invoices will also be deleted.')">🗑</a>
                        </div>
                    </td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
