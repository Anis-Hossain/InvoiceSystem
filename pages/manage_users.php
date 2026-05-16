<?php
require_once '../includes/config.php';
requireAdmin();
$page_title = 'User Management';
$base = '../';

// ---- DELETE ----
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id === (int)$_SESSION['user_id']) {
        header('Location: manage_users.php?error=self'); exit;
    }
    $conn->query("DELETE FROM users WHERE id=$id"); // user_clients cascade
    header('Location: manage_users.php?success=deleted'); exit;
}

// ---- TOGGLE ACTIVE ----
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE users SET is_active = NOT is_active WHERE id=$id AND id != {$_SESSION['user_id']}");
    header('Location: manage_users.php?success=updated'); exit;
}

// ---- SAVE (create / edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = (int)($_POST['id'] ?? 0);
    $name      = sanitize($conn, $_POST['name']);
    $email     = sanitize($conn, $_POST['email']);
    $role      = sanitize($conn, $_POST['role']);
    $password  = trim($_POST['password'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Collect chosen client IDs (only relevant for company role)
    $chosen_ids = [];
    if ($role === 'company' && !empty($_POST['client_ids']) && is_array($_POST['client_ids'])) {
        foreach ($_POST['client_ids'] as $cid) {
            $cid = (int)$cid;
            if ($cid > 0) $chosen_ids[] = $cid;
        }
    }

    if ($password && strlen($password) < 8) {
        $error_msg = 'Password must be at least 8 characters.';
    } elseif ($id === 0 && !$password) {
        $error_msg = 'Password is required for new users.';
    } else {
        if ($id === 0) {
            // INSERT
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $conn->prepare("INSERT INTO users (name,email,password,role,is_active) VALUES (?,?,?,?,?)");
            $stmt->bind_param('ssssi', $name, $email, $hash, $role, $is_active);
            if (!$stmt->execute()) {
                $error_msg = 'Email already exists or database error: ' . $stmt->error;
            } else {
                $id = $conn->insert_id;
            }
            $stmt->close();
        } else {
            // UPDATE
            if ($password) {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $conn->prepare("UPDATE users SET name=?,email=?,password=?,role=?,is_active=? WHERE id=?");
                $stmt->bind_param('ssssii', $name, $email, $hash, $role, $is_active, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?,email=?,role=?,is_active=? WHERE id=?");
                $stmt->bind_param('sssii', $name, $email, $role, $is_active, $id);
            }
            $stmt->execute(); $stmt->close();
        }

        if (!isset($error_msg)) {
            // Check if any chosen client is already linked to a DIFFERENT user
            $conflict_names = [];
            foreach ($chosen_ids as $cid) {
                $res = $conn->query(
                    "SELECT u.name FROM user_clients uc
                     JOIN users u ON u.id = uc.user_id
                     WHERE uc.client_id = $cid AND uc.user_id != $id"
                );
                if ($res && $res->num_rows > 0) {
                    $owner      = $res->fetch_assoc()['name'];
                    $client_row = $conn->query("SELECT name FROM clients WHERE id=$cid")->fetch_assoc();
                    $conflict_names[] = htmlspecialchars($client_row['name']) . ' (already linked to ' . htmlspecialchars($owner) . ')';
                }
            }

            if (!empty($conflict_names)) {
                $error_msg = 'The following clients are already linked to another user: ' . implode(', ', $conflict_names) . '. Each client can only be linked to one user.';
            }
        }

        if (!isset($error_msg) && $id > 0) {
            // Sync user_clients pivot — delete ALL existing links then re-insert chosen ones
            $conn->query("DELETE FROM user_clients WHERE user_id=$id");
            foreach ($chosen_ids as $cid) {
                $conn->query("INSERT IGNORE INTO user_clients (user_id, client_id) VALUES ($id, $cid)");
            }
            // If the admin just edited the currently logged-in user, refresh their session client IDs
            if ($id === (int)$_SESSION['user_id']) {
                loadUserClientIds($conn);
            }
            header('Location: manage_users.php?success=' . ($id ? 'updated' : 'created')); exit;
        }
    }
}

// ---- Load all users with their linked clients ----
$users_res = $conn->query("SELECT * FROM users ORDER BY role, name");
$all_users = [];
while ($u = $users_res->fetch_assoc()) {
    $uid = $u['id'];
    $cl  = $conn->query("SELECT c.id, c.name, c.company FROM user_clients uc
                          JOIN clients c ON c.id = uc.client_id
                          WHERE uc.user_id = $uid ORDER BY c.name");
    $u['linked_clients'] = [];
    while ($c = $cl->fetch_assoc()) $u['linked_clients'][] = $c;
    $all_users[] = $u;
}

$clients = $conn->query("SELECT id, name, company FROM clients ORDER BY name");

// ---- Load which clients are already taken by OTHER users ----
// Key: client_id → owner name  (used to disable checkboxes in the form)
$taken_clients = [];
$taken_res = $conn->query(
    "SELECT uc.client_id, u.id AS owner_id, u.name AS owner_name
     FROM user_clients uc JOIN users u ON u.id = uc.user_id"
);
while ($t = $taken_res->fetch_assoc()) {
    $taken_clients[(int)$t['client_id']] = ['owner_id' => (int)$t['owner_id'], 'owner_name' => $t['owner_name']];
}

// ---- Edit mode: load existing linked client IDs ----
// Also handles re-rendering after a failed POST (uses $_POST['id'] as fallback)
$edit = null;
$edit_client_ids = [];
$eid = 0;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
} elseif (isset($_POST['id']) && (int)$_POST['id'] > 0) {
    // Failed POST — re-load the user so the form shows the correct data
    $eid = (int)$_POST['id'];
}
if ($eid > 0) {
    $edit = $conn->query("SELECT * FROM users WHERE id=$eid")->fetch_assoc();
    $ecl  = $conn->query("SELECT client_id FROM user_clients WHERE user_id=$eid");
    while ($r = $ecl->fetch_assoc()) $edit_client_ids[] = (int)$r['client_id'];
}

require_once '../includes/header.php';
?>

<?php if (isset($_GET['success'])): $msgs=['created'=>'✅ User created.','updated'=>'✅ User updated.','deleted'=>'✅ User deleted.']; ?>
<div class="alert alert-success"><?= $msgs[$_GET['success']] ?? '✅ Done.' ?></div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error']==='self'): ?>
<div class="alert alert-error">❌ You cannot delete your own account.</div>
<?php endif; ?>
<?php if (isset($error_msg)): ?>
<div class="alert alert-error">❌ <?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

<!-- ======= Form ======= -->
<div class="panel mb-20">
    <div class="panel-header">
        <span class="panel-title"><?= $edit ? '✏️ Edit User: '.htmlspecialchars($edit['name']) : '➕ New User' ?></span>
        <?php if ($edit): ?><a href="manage_users.php" class="btn btn-outline btn-sm">Cancel</a><?php endif; ?>
    </div>
    <div class="panel-body">
    <form method="POST" id="user-form">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

        <div class="form-grid">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($edit['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Role *</label>
                <select name="role" id="role-select" onchange="toggleClientField()">
                    <option value="admin"   <?= ($edit['role'] ?? '')==='admin'   ? 'selected' : '' ?>>🔑 Administrator</option>
                    <option value="company" <?= ($edit['role'] ?? 'company')==='company' ? 'selected' : '' ?>>🏢 Company (Client)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Password <?= $edit ? '(leave blank to keep current)' : '*' ?></label>
                <input type="password" name="password" placeholder="Min 8 characters"
                       <?= $edit ? '' : 'required' ?> minlength="8">
            </div>
            <div class="form-group" style="justify-content:flex-end;padding-top:4px;">
                <label>&nbsp;</label>
                <label style="flex-direction:row;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:normal;">
                    <input type="checkbox" name="is_active" value="1"
                           <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?> style="width:auto;">
                    Account Active
                </label>
            </div>
        </div>

        <!-- ---- Linked Clients (multi-checkbox) ---- -->
        <div id="client-field" style="<?= ($edit['role'] ?? 'company') === 'admin' ? 'display:none' : '' ?>">
            <hr style="margin:18px 0;">
            <div style="margin-bottom:12px;">
                <span style="font-weight:700;font-size:14px;">🔗 Linked Clients</span>
                <span class="text-muted" style="margin-left:8px;font-size:12px;">
                    This user can only create/view invoices for selected clients.
                </span>
            </div>

            <?php if ($clients->num_rows === 0): ?>
                <div class="alert alert-info">No clients exist yet. <a href="clients.php">Add clients first.</a></div>
            <?php else: ?>

            <!-- Select All / None shortcuts -->
            <div style="margin-bottom:10px;display:flex;gap:10px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="setAllClients(true)">☑ Select All</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="setAllClients(false)">☐ Deselect All</button>
            </div>

            <div class="client-checkbox-grid">
                <?php $clients->data_seek(0); while ($c = $clients->fetch_assoc()):
                    $cid     = (int)$c['id'];
                    $checked = in_array($cid, $edit_client_ids) ? 'checked' : '';
                    $taken   = isset($taken_clients[$cid]) && $taken_clients[$cid]['owner_id'] !== $eid;
                    $owner   = $taken ? $taken_clients[$cid]['owner_name'] : '';
                ?>
                <label class="client-checkbox-card <?= $checked ? 'checked' : '' ?> <?= $taken ? 'taken' : '' ?>"
                       for="client_<?= $cid ?>">
                    <input type="checkbox"
                           id="client_<?= $cid ?>"
                           name="client_ids[]"
                           value="<?= $cid ?>"
                           <?= $checked ?>
                           <?= $taken ? 'disabled' : '' ?>
                           onchange="this.closest('label').classList.toggle('checked', this.checked)">
                    <div class="client-checkbox-body">
                        <div class="client-checkbox-name"><?= htmlspecialchars($c['name']) ?></div>
                        <?php if ($c['company']): ?>
                        <div class="client-checkbox-company">🏢 <?= htmlspecialchars($c['company']) ?></div>
                        <?php endif; ?>
                        <?php if ($taken): ?>
                        <div class="client-checkbox-taken">🔒 Linked to <?= htmlspecialchars($owner) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="client-checkbox-tick">✓</span>
                </label>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="mt-20">
            <button type="submit" class="btn btn-primary">💾 Save User</button>
            <a href="manage_users.php" class="btn btn-outline" style="margin-left:8px;">Cancel</a>
        </div>
    </form>
    </div>
</div>

<!-- ======= Users Table ======= -->
<div class="panel">
    <div class="panel-header"><span class="panel-title">🔐 All Users (<?= count($all_users) ?>)</span></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th><th>Email</th><th>Role</th>
                    <th>Linked Clients</th><th>Status</th><th>Created</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($all_users as $u): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($u['name']) ?></strong>
                    <?= $u['id']===$_SESSION['user_id'] ? '<span class="badge" style="background:#4f46e5;margin-left:4px;">You</span>' : '' ?>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <?= $u['role'] === 'admin'
                        ? "<span class='badge' style='background:#7c3aed'>🔑 Admin</span>"
                        : "<span class='badge' style='background:#0284c7'>🏢 Company</span>" ?>
                </td>
                <td>
                    <?php if ($u['role'] === 'admin'): ?>
                        <span class="text-muted">All clients</span>
                    <?php elseif (empty($u['linked_clients'])): ?>
                        <span style="color:var(--danger);font-size:12px;">⚠ None linked</span>
                    <?php else: ?>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;">
                        <?php foreach ($u['linked_clients'] as $lc): ?>
                            <span class="badge" style="background:#0891b2;font-size:11px;">
                                <?= htmlspecialchars($lc['name'] ?: $lc['company']) ?>
                            </span>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?= $u['is_active']
                        ? "<span class='badge' style='background:#16a34a'>✅ Active</span>"
                        : "<span class='badge' style='background:#dc2626'>🚫 Inactive</span>" ?>
                </td>
                <td class="text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <div class="btn-group">
                        <a href="manage_users.php?edit=<?= $u['id'] ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
                        <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                        <a href="manage_users.php?toggle=<?= $u['id'] ?>"
                           class="btn btn-info btn-sm"
                           onclick="return confirm('Toggle account status?')">
                            <?= $u['is_active'] ? '🔒 Deactivate' : '🔓 Activate' ?>
                        </a>
                        <a href="manage_users.php?delete=<?= $u['id'] ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirmDelete('Permanently delete user <?= htmlspecialchars(addslashes($u['name'])) ?>?')">🗑</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* ---- Multi-client checkbox grid ---- */
.client-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 10px;
    margin-bottom: 4px;
}
.client-checkbox-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border: 2px solid var(--gray-200);
    border-radius: 9px;
    cursor: pointer;
    transition: border-color .18s, background .18s;
    position: relative;
    background: var(--white);
    user-select: none;
}
.client-checkbox-card input[type=checkbox] {
    display: none;
}
.client-checkbox-card:hover {
    border-color: var(--primary);
    background: #f5f3ff;
}
.client-checkbox-card.checked {
    border-color: var(--primary);
    background: #ede9fe;
}
.client-checkbox-body { flex: 1; min-width: 0; }
.client-checkbox-name {
    font-weight: 700;
    font-size: 13px;
    color: var(--gray-800);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.client-checkbox-company {
    font-size: 11px;
    color: var(--gray-600);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.client-checkbox-tick {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    opacity: 0;
    transition: opacity .18s;
}
.client-checkbox-card.checked .client-checkbox-tick { opacity: 1; }
.client-checkbox-card.taken {
    background: #f8fafc;
    border-color: #e2e8f0;
    cursor: not-allowed;
    opacity: 0.65;
}
.client-checkbox-card.taken:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.client-checkbox-taken {
    font-size: 10px;
    color: #dc2626;
    margin-top: 3px;
    font-weight: 600;
}
</style>

<script>
function toggleClientField() {
    const role  = document.getElementById('role-select').value;
    const field = document.getElementById('client-field');
    field.style.display = role === 'company' ? '' : 'none';
}
toggleClientField();

function setAllClients(checked) {
    document.querySelectorAll('.client-checkbox-card input[type=checkbox]').forEach(cb => {
        cb.checked = checked;
        cb.closest('label').classList.toggle('checked', checked);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
