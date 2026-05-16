<?php
require_once '../includes/config.php';
requireLogin();
$page_title = 'Invoice Status Check';
$base = '../';

$result  = null;
$error   = '';
$search  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['q'])) {
    $search = sanitize($conn, $_POST['invoice_number'] ?? $_GET['q'] ?? '');

    if ($search) {
        $inv = $conn->query("SELECT i.*, c.name AS client_name, c.email AS client_email,
                              c.company AS client_company, c.phone AS client_phone
                              FROM invoices i JOIN clients c ON c.id=i.client_id
                              WHERE i.invoice_number='$search'")->fetch_assoc();

        if ($inv) {
            $items    = $conn->query("SELECT * FROM invoice_items WHERE invoice_id={$inv['id']}");
            $payments = $conn->query("SELECT * FROM payments WHERE invoice_id={$inv['id']} ORDER BY payment_date DESC");
            $paid_sum = $conn->query("SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE invoice_id={$inv['id']}")->fetch_assoc()['s'];
            $balance  = (float)$inv['total'] - (float)$paid_sum;
            $result   = compact('inv','items','payments','paid_sum','balance');
        } else {
            $error = "No invoice found with number: <strong>" . htmlspecialchars($search) . "</strong>";
        }
    }
}

require_once '../includes/header.php';
?>

<div style="max-width:720px;margin:0 auto;">

    <!-- Search Box -->
    <div class="panel">
        <div class="panel-header"><span class="panel-title">🔍 Invoice Status Lookup</span></div>
        <div class="panel-body">
            <p style="margin-bottom:16px;color:var(--gray-600);">Enter an invoice number to check its current status, payment history, and outstanding balance.</p>
            <form method="POST" style="display:flex;gap:10px;">
                <input type="text" name="invoice_number" placeholder="e.g. INV-2024-001"
                       value="<?= htmlspecialchars($search) ?>" style="flex:1;font-size:15px;" required autofocus>
                <button type="submit" class="btn btn-primary">🔍 Check Status</button>
            </form>

            <?php if ($error): ?>
            <div class="alert alert-error" style="margin-top:16px;margin-bottom:0;">❌ <?= $error ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Result -->
    <?php if ($result): extract($result); ?>
    <div class="status-result">

        <!-- Header -->
        <div class="inv-header">
            <div>
                <div class="inv-title">Invoice <?= htmlspecialchars($inv['invoice_number']) ?></div>
                <div class="text-muted"><?= htmlspecialchars($inv['client_name']) ?> <?= $inv['client_company'] ? '· '.htmlspecialchars($inv['client_company']) : '' ?></div>
            </div>
            <?= statusBadge($inv['status']) ?>
        </div>

        <!-- Summary cards -->
        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:22px;">
            <div style="background:var(--gray-50);border-radius:8px;padding:14px;text-align:center;">
                <div style="font-size:11px;text-transform:uppercase;color:var(--gray-400);margin-bottom:4px;">Invoice Total</div>
                <div style="font-size:20px;font-weight:800;"><?= money($inv['total']) ?></div>
            </div>
            <div style="background:#f0fdf4;border-radius:8px;padding:14px;text-align:center;">
                <div style="font-size:11px;text-transform:uppercase;color:var(--gray-400);margin-bottom:4px;">Amount Paid</div>
                <div style="font-size:20px;font-weight:800;color:var(--success);"><?= money($paid_sum) ?></div>
            </div>
            <div style="background:<?= $balance>0?'#fef2f2':'#f0fdf4' ?>;border-radius:8px;padding:14px;text-align:center;">
                <div style="font-size:11px;text-transform:uppercase;color:var(--gray-400);margin-bottom:4px;">Balance Due</div>
                <div style="font-size:20px;font-weight:800;color:<?= $balance>0?'var(--danger)':'var(--success)' ?>;"><?= money($balance) ?></div>
            </div>
        </div>

        <!-- Dates -->
        <table style="width:100%;font-size:13px;margin-bottom:20px;">
            <tr>
                <td style="padding:5px 0;color:var(--gray-600);width:40%;">Issue Date</td>
                <td><?= date('d M Y', strtotime($inv['issue_date'])) ?></td>
            </tr>
            <tr>
                <td style="padding:5px 0;color:var(--gray-600);">Due Date</td>
                <td><?= date('d M Y', strtotime($inv['due_date'])) ?>
                    <?php if ($inv['status']==='overdue'): ?>
                    <span style="color:var(--danger);font-weight:600;font-size:11px;margin-left:6px;">⏰ OVERDUE</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="padding:5px 0;color:var(--gray-600);">Client Email</td>
                <td><?= htmlspecialchars($inv['client_email']) ?></td>
            </tr>
            <?php if ($inv['client_phone']): ?>
            <tr>
                <td style="padding:5px 0;color:var(--gray-600);">Client Phone</td>
                <td><?= htmlspecialchars($inv['client_phone']) ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <!-- Items -->
        <div style="font-weight:700;margin-bottom:8px;">📦 Items</div>
        <div class="table-wrap" style="margin-bottom:20px;">
        <table>
            <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
            <tbody>
            <?php while ($item = $items->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($item['description']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td><?= money($item['unit_price']) ?></td>
                <td><?= money($item['total']) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>

        <!-- Payments -->
        <div style="font-weight:700;margin-bottom:8px;">💳 Payment History</div>
        <?php $payments->data_seek(0); if ($payments->num_rows === 0): ?>
        <div class="alert alert-info">No payments recorded for this invoice.</div>
        <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
            <tbody>
            <?php while ($p = $payments->fetch_assoc()): ?>
            <tr>
                <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                <td style="color:var(--success);font-weight:600;"><?= money($p['amount']) ?></td>
                <td><?= ucfirst(str_replace('_',' ',$p['method'])) ?></td>
                <td><?= htmlspecialchars($p['reference'] ?: '—') ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

        <?php if ($inv['notes']): ?>
        <div style="margin-top:16px;padding:12px;background:var(--gray-50);border-radius:8px;border-left:3px solid var(--primary);">
            <strong>Notes:</strong> <?= nl2br(htmlspecialchars($inv['notes'])) ?>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="btn-group mt-20 no-print">
            <a href="view_invoice.php?id=<?= $inv['id'] ?>" class="btn btn-primary">👁 View Full Invoice</a>
            <?php if ($balance > 0): ?>
            <a href="payments.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-success">💳 Record Payment</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>
