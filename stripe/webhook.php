<?php
/*
   stripe/webhook.php
   Stripe calls this URL automatically after every payment event.

   You register this URL in your Stripe Dashboard under:
     Developers → Webhooks → Add endpoint
     URL: http://yourdomain.com/invoice_system/stripe/webhook.php
     Events to listen for: checkout.session.completed

   IMPORTANT: This file must NOT require login — Stripe calls it
   directly, not through a browser.
*/

require_once '../includes/config.php';
require_once 'stripe-php/init.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Read the raw POST body Stripe sent
$payload   = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify the request actually came from Stripe (not a fake request)
// If verification fails, stop immediately with a 400 error
try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        STRIPE_WEBHOOK_SECRET
    );
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Webhook signature verification failed.');
}

// We only care about successful payments
if ($event->type === 'checkout.session.completed') {

    $session    = $event->data->object;
    $invoice_id = (int)($session->metadata->invoice_id ?? 0);
    $amount     = $session->amount_total / 100;    // convert cents back to dollars
    $payment_id = $session->payment_intent;        // Stripe's unique payment ID

    if ($invoice_id > 0) {

        // Check this payment hasn't already been recorded (Stripe can retry webhooks)
        $existing = $conn->query(
            "SELECT id FROM payments WHERE reference = '$payment_id'"
        )->fetch_assoc();

        if (!$existing) {

            // Record the payment in our payments table
            $date = date('Y-m-d');
            $conn->query(
                "INSERT INTO payments (invoice_id, amount, payment_date, method, reference, notes)
                 VALUES ($invoice_id, $amount, '$date', 'credit_card', '$payment_id', 'Paid via Stripe')"
            );

            // Check if the invoice is now fully paid
            $inv_total = (float)$conn->query(
                "SELECT total FROM invoices WHERE id = $invoice_id"
            )->fetch_assoc()['total'];

            $total_paid = (float)$conn->query(
                "SELECT COALESCE(SUM(amount), 0) AS s FROM payments WHERE invoice_id = $invoice_id"
            )->fetch_assoc()['s'];

            // Mark as paid if balance is cleared
            if ($total_paid >= $inv_total) {
                $conn->query(
                    "UPDATE invoices SET status = 'paid' WHERE id = $invoice_id"
                );
            }
        }
    }
}

// Tell Stripe everything went fine
http_response_code(200);
echo json_encode(['status' => 'ok']);
