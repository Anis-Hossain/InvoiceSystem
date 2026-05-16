<?php
require_once '../includes/config.php';
requireLogin();
$base = '../';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: invoices.php'); exit; }

$inv = $conn->query("SELECT i.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone,
                      c.company AS client_company, c.address AS client_address, c.city AS client_city, c.country AS client_country
                      FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.id=$id")->fetch_assoc();
if (!$inv) { header('Location: invoices.php'); exit; }

$items    = $conn->query("SELECT * FROM invoice_items WHERE invoice_id=$id");
$payments = $conn->query("SELECT * FROM payments WHERE invoice_id=$id ORDER BY payment_date DESC");

// Total paid
$paid_res = $conn->query("SELECT COALESCE(SUM(amount),0) AS paid FROM payments WHERE invoice_id=$id");
$total_paid = (float)$paid_res->fetch_assoc()['paid'];
$balance = (float)$inv['total'] - $total_paid;

$page_title = 'Invoice ' . $inv['invoice_number'];
require_once '../includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">✅ Invoice saved successfully.</div>
<?php endif; ?>

<div class="flex-between mb-20 no-print">
    <div class="btn-group">
        <a href="invoices.php" class="btn btn-outline">← Back</a>
        <a href="invoices.php?edit=<?= $inv['id'] ?>" class="btn btn-warning">✏️ Edit</a>
        <a href="payments.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-success">💳 Record Payment</a>
        <?php if ($balance > 0 && in_array($inv['status'], ['sent', 'overdue'])): ?>
        <a href="../stripe/checkout.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-primary">💳 Pay Now via Stripe</a>
        <?php endif; ?>
    </div>
    <div class="btn-group">
        <button class="btn btn-primary" onclick="printInvoice()">🖨️ Print / Save PDF</button>
    </div>
</div>

<!-- ======= PRINTABLE INVOICE ======= -->
<div class="panel" id="invoice-print-area" style="max-width:820px;margin:auto;">
<div class="panel-body" style="padding:36px;">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:30px;">
        <div>
            <div style="font-size:26px;font-weight:900;color:var(--primary);">📄 INVOICE</div>
            <div style="font-size:18px;font-weight:700;margin-top:4px;"><?= htmlspecialchars($inv['invoice_number']) ?></div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:20px;font-weight:800;"><?= APP_NAME ?></div>
            <div class="text-muted">Invoice Management System</div>
        </div>
    </div>

    <!-- Meta Grid -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-bottom:28px;">
        <div>
            <div style="font-size:11px;text-transform:uppercase;color:var(--gray-400);letter-spacing:.5px;margin-bottom:6px;">Bill To</div>
            <div style="font-weight:700;font-size:15px;"><?= htmlspecialchars($inv['client_name']) ?></div>
            <?php if ($inv['client_company']): ?><div><?= htmlspecialchars($inv['client_company']) ?></div><?php endif; ?>
            <div class="text-muted"><?= htmlspecialchars($inv['client_email']) ?></div>
            <?php if ($inv['client_phone']): ?><div class="text-muted"><?= htmlspecialchars($inv['client_phone']) ?></div><?php endif; ?>
            <?php if ($inv['client_address']): ?><div class="text-muted"><?= htmlspecialchars($inv['client_address']) ?>, <?= htmlspecialchars($inv['client_city']) ?></div><?php endif; ?>
        </div>
        <div style="text-align:right;">
            <table style="margin-left:auto;font-size:13px;">
                <tr><td style="padding:3px 20px 3px 0;color:var(--gray-600);">Issue Date:</td><td style="font-weight:600;"><?= date('d M Y', strtotime($inv['issue_date'])) ?></td></tr>
                <tr><td style="padding:3px 20px 3px 0;color:var(--gray-600);">Due Date:</td><td style="font-weight:600;"><?= date('d M Y', strtotime($inv['due_date'])) ?></td></tr>
                <tr><td style="padding:3px 20px 3px 0;color:var(--gray-600);">Status:</td><td><?= statusBadge($inv['status']) ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Items Table -->
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
        <thead>
            <tr style="background:var(--gray-800);color:#fff;">
                <th style="padding:10px 14px;text-align:left;border-radius:6px 0 0 6px;">Description</th>
                <th style="padding:10px 14px;text-align:center;">Qty</th>
                <th style="padding:10px 14px;text-align:right;">Unit Price</th>
                <th style="padding:10px 14px;text-align:right;border-radius:0 6px 6px 0;">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($item = $items->fetch_assoc()): ?>
            <tr style="border-bottom:1px solid var(--gray-100);">
                <td style="padding:10px 14px;"><?= htmlspecialchars($item['description']) ?></td>
                <td style="padding:10px 14px;text-align:center;"><?= $item['quantity'] ?></td>
                <td style="padding:10px 14px;text-align:right;"><?= money($item['unit_price']) ?></td>
                <td style="padding:10px 14px;text-align:right;font-weight:600;"><?= money($item['total']) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div style="display:flex;justify-content:flex-end;">
        <table style="font-size:13px;min-width:240px;">
            <tr><td style="padding:5px 20px 5px 0;color:var(--gray-600);">Subtotal</td><td style="text-align:right;"><?= money($inv['subtotal']) ?></td></tr>
            <tr><td style="padding:5px 20px 5px 0;color:var(--gray-600);">Tax (<?= $inv['tax_rate'] ?>%)</td><td style="text-align:right;"><?= money($inv['tax_amount']) ?></td></tr>
            <tr><td style="padding:5px 20px 5px 0;color:var(--gray-600);">Discount</td><td style="text-align:right;">- <?= money($inv['discount']) ?></td></tr>
            <tr style="border-top:2px solid var(--gray-300);">
                <td style="padding:10px 20px 5px 0;font-size:16px;font-weight:800;">Total</td>
                <td style="text-align:right;font-size:16px;font-weight:800;"><?= money($inv['total']) ?></td>
            </tr>
            <tr><td style="padding:3px 20px 3px 0;color:var(--success);font-weight:600;">Amount Paid</td><td style="text-align:right;color:var(--success);font-weight:600;"><?= money($total_paid) ?></td></tr>
            <tr style="border-top:1px solid var(--gray-200);">
                <td style="padding:5px 20px 5px 0;font-weight:700;color:<?= $balance>0?'var(--danger)':'var(--success)' ?>;">Balance Due</td>
                <td style="text-align:right;font-weight:700;color:<?= $balance>0?'var(--danger)':'var(--success)' ?>;"><?= money($balance) ?></td>
            </tr>
        </table>
    </div>

    <?php if ($inv['notes']): ?>
    <div style="margin-top:24px;padding:14px;background:var(--gray-50);border-radius:8px;border-left:3px solid var(--primary);">
        <div style="font-size:11px;text-transform:uppercase;color:var(--gray-400);margin-bottom:4px;">Notes</div>
        <div><?= nl2br(htmlspecialchars($inv['notes'])) ?></div>
    </div>
    <?php endif; ?>

    <div style="margin-top:30px;padding-top:16px;border-top:1px solid var(--gray-200);text-align:center;font-size:11px;color:var(--gray-400);">
        Thank you for your business! · Generated by <?= APP_NAME ?>
    </div>
</div>
</div>

<!-- Payments History -->
<?php $payments->data_seek(0); if ($payments->num_rows > 0): ?>
<div class="panel mt-20 no-print" style="max-width:820px;margin:24px auto 0;">
    <div class="panel-header"><span class="panel-title">💳 Payment History</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Notes</th></tr></thead>
            <tbody>
            <?php while ($p = $payments->fetch_assoc()): ?>
            <tr>
                <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                <td><?= money($p['amount']) ?></td>
                <td><?= ucfirst(str_replace('_',' ',$p['method'])) ?></td>
                <td><?= htmlspecialchars($p['reference'] ?? '—') ?></td>
                <td><?= htmlspecialchars($p['notes'] ?? '—') ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
