<?php
/*
   stripe/success.php
   The page clients are redirected to after a successful Stripe payment.
   The webhook already handled marking the invoice as paid —
   this page just shows a confirmation message.
*/

require_once '../includes/config.php';

$invoice_id = (int)($_GET['invoice_id'] ?? 0);
$inv = $invoice_id
    ? $conn->query("SELECT * FROM invoices WHERE id = $invoice_id")->fetch_assoc()
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful — <?= APP_NAME ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 16px; padding: 48px 40px; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 8px 30px rgba(0,0,0,.1); }
        .icon { font-size: 64px; margin-bottom: 16px; }
        h1 { font-size: 24px; font-weight: 800; color: #1e293b; margin-bottom: 8px; }
        p { color: #475569; font-size: 15px; margin-bottom: 24px; }
        .inv-num { font-weight: 700; color: #4f46e5; }
        .btn { display: inline-block; padding: 12px 28px; background: #4f46e5; color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 14px; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">✅</div>
    <h1>Payment Successful!</h1>
    <?php if ($inv): ?>
        <p>Thank you — invoice <span class="inv-num"><?= htmlspecialchars($inv['invoice_number']) ?></span> has been paid.</p>
    <?php else: ?>
        <p>Thank you! Your payment was received successfully.</p>
    <?php endif; ?>
    <a href="../pages/invoice_status.php" class="btn">🔍 Check Invoice Status</a>
</div>
</body>
</html>
