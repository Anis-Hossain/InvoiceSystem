<?php
require_once 'includes/config.php';
$page_title = 'Dashboard';
requireLogin();

// Role-aware scope (supports multiple linked clients)
// Build bare-table scope (no alias) for single-table queries
if (isAdmin()) {
    $scope = '';
} else {
    $ids = getUserClientIds();
    $scope = empty($ids) ? ' AND 1=0' : ' AND client_id IN (' . implode(',', array_map('intval', $ids)) . ')';
}
$scope_join = clientScope('i');  // for joined queries: WHERE i.client_id IN(...)

// Stats
$total_invoices = $conn->query("SELECT COUNT(*) AS c FROM invoices WHERE 1=1 $scope")->fetch_assoc()['c'];
$total_revenue  = $conn->query("SELECT SUM(p.amount) AS s FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE 1=1 $scope_join")->fetch_assoc()['s'] ?? 0;
// Admin: count all clients. User: count only clients linked to them in user_clients table.
if (isAdmin()) {
    $total_clients = (int)$conn->query("SELECT COUNT(*) AS c FROM clients")->fetch_assoc()['c'];
} else {
    $uid = (int)$_SESSION['user_id'];
    $total_clients = (int)$conn->query("SELECT COUNT(*) AS c FROM user_clients WHERE user_id = $uid")->fetch_assoc()['c'];
}
$overdue_count  = $conn->query("SELECT COUNT(*) AS c FROM invoices WHERE status='overdue' $scope")->fetch_assoc()['c'];
$paid_count     = $conn->query("SELECT COUNT(*) AS c FROM invoices WHERE status='paid' $scope")->fetch_assoc()['c'];
$draft_count    = $conn->query("SELECT COUNT(*) AS c FROM invoices WHERE status='draft' $scope")->fetch_assoc()['c'];
$sent_count     = $conn->query("SELECT COUNT(*) AS c FROM invoices WHERE status='sent' $scope")->fetch_assoc()['c'];
$pending_amt    = $conn->query("SELECT SUM(total) AS s FROM invoices WHERE status IN ('sent','overdue') $scope")->fetch_assoc()['s'] ?? 0;

// Recent invoices
$recent = $conn->query("SELECT i.*, c.name AS client_name FROM invoices i JOIN clients c ON c.id=i.client_id WHERE 1=1 $scope_join ORDER BY i.created_at DESC LIMIT 7");

// Monthly revenue (last 6 months)
$monthly = $conn->query("
    SELECT DATE_FORMAT(p.payment_date,'%b') AS month, MONTH(p.payment_date) AS mnum, SUM(p.amount) AS total
    FROM payments p
    JOIN invoices i ON i.id=p.invoice_id
    WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) $scope_join
    GROUP BY YEAR(p.payment_date), MONTH(p.payment_date)
    ORDER BY YEAR(p.payment_date), MONTH(p.payment_date)
");
$months = []; $max_rev = 1;
while ($r = $monthly->fetch_assoc()) { $months[] = $r; $max_rev = max($max_rev, $r['total']); }

require_once 'includes/header.php';
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon">📋</div>
        <div class="stat-label">Total Invoices</div>
        <div class="stat-value" id="totalInvoices"><?= $total_invoices ?></div>
        <div class="stat-sub">All time</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">💰</div>
        <div class="stat-label">Revenue Collected</div>
        <div class="stat-value" id="totalRevenue"><?= money($total_revenue) ?></div>
        <div class="stat-sub">From payments</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">⏳</div>
        <div class="stat-label">Pending Amount</div>
        <div class="stat-value" id="pendingAmount"><?= money($pending_amt) ?></div>
        <div class="stat-sub"><?= $sent_count ?> sent + <?= $overdue_count ?> overdue</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">⏰</div>
        <div class="stat-label">Overdue</div>
        <div class="stat-value" id="overdueCount"><?= $overdue_count ?></div>
        <div class="stat-sub">Requires attention</div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon">👥</div>
        <div class="stat-label">Clients</div>
        <div class="stat-value" id="totalClients"><?= $total_clients ?></div>
        <div class="stat-sub">Registered</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:22px;">

<!-- Recent Invoices -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">📋 Recent Invoices</span>
        <a href="pages/invoices.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Client</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php if ($recent->num_rows === 0): ?>
                <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📭</div>No invoices yet.</div></td></tr>
            <?php else: while ($inv = $recent->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                    <td><?= htmlspecialchars($inv['client_name']) ?></td>
                    <td><?= money($inv['total']) ?></td>
                    <td><?= date('d M Y', strtotime($inv['due_date'])) ?></td>
                    <td><?= statusBadge($inv['status']) ?></td>
                    <td><a href="pages/view_invoice.php?id=<?= $inv['id'] ?>" class="btn btn-outline btn-sm">View</a></td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Status Summary -->
<div class="panel">
    <div class="panel-header"><span class="panel-title">📊 Invoice Status</span></div>
    <div class="panel-body">
        <?php
        $statuses = [
            'paid'      => ['#16a34a', $paid_count],
            'sent'      => ['#0d6efd', $sent_count],
            'draft'     => ['#6c757d', $draft_count],
            'overdue'   => ['#dc2626', $overdue_count],
        ];
        $total_inv_for_pct = max(1, $total_invoices);
        foreach ($statuses as $s => [$color, $count]):
            $pct = round($count / $total_inv_for_pct * 100);
        ?>
        <div style="margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                <span style="font-weight:600;text-transform:capitalize;"><?= ucfirst($s) ?></span>
                <span><?= $count ?> (<?= $pct ?>%)</span>
            </div>
            <div style="background:var(--gray-100);border-radius:20px;height:8px;overflow:hidden;">
                <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:20px;transition:width .4s;"></div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($months)): ?>
        <hr>
        <div style="font-size:13px;font-weight:700;margin-bottom:10px;">📈 Monthly Revenue</div>
        <div class="bar-chart" style="position:relative;">
            <?php foreach ($months as $m): ?>
            <div class="bar-wrap">
                <div class="bar-val"><?= money($m['total']) ?></div>
                <div class="bar" style="height:<?= round($m['total']/$max_rev*90) ?>px;" title="<?= $m['month'] ?>: <?= money($m['total']) ?>"></div>
                <span class="bar-label" style="position:static;font-size:10px;margin-top:3px;"><?= $m['month'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

</div>

<script>
async function updateDashboard(){
  try{
    const r=await fetch('api_dashboard_stats.php');
    const d=await r.json();
    totalInvoices.textContent=d.invoices;
    totalRevenue.textContent=d.revenue;
    pendingAmount.textContent=d.pending;
    overdueCount.textContent=d.overdue;
    totalClients.textContent=d.clients;
  }catch(e){console.log('Dashboard refresh failed');}
}
setInterval(updateDashboard,5000);
</script>

<?php require_once 'includes/footer.php'; ?>
