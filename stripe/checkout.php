<?php
/*
   stripe/checkout.php
   Creates a Stripe Checkout session for an invoice and
   redirects the client to Stripe's hosted payment page.

   Called from the "Pay Now" button on view_invoice.php:
     <a href="stripe/checkout.php?invoice_id=5">Pay Now</a>
*/

require_once '../includes/config.php';

// Load Stripe PHP library
require_once 'stripe-php/init.php';

$invoice_id = (int)($_GET['invoice_id'] ?? 0);
if (!$invoice_id) {
    die('Invalid invoice.');
}

// Load the invoice + client from DB
$inv = $conn->query(
    "SELECT i.*, c.name AS client_name, c.email AS client_email
     FROM invoices i
     JOIN clients c ON c.id = i.client_id
     WHERE i.id = $invoice_id"
)->fetch_assoc();

if (!$inv) {
    die('Invoice not found.');
}

// Work out how much is still owed
$paid_sum = (float)$conn->query(
    "SELECT COALESCE(SUM(amount), 0) AS s FROM payments WHERE invoice_id = $invoice_id"
)->fetch_assoc()['s'];

$balance = (float)$inv['total'] - $paid_sum;

if ($balance <= 0) {
    die('This invoice is already paid.');
}

// Set Stripe secret key
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Create a Stripe Checkout session
// Stripe amounts are in the smallest currency unit (cents for USD)
$session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card'],
    'customer_email'       => $inv['client_email'],
    'line_items'           => [[
        'price_data' => [
            'currency'     => strtolower(CURRENCY_CODE),
            'unit_amount'  => (int)round($balance * 100),   // convert to cents
            'product_data' => [
                'name'        => 'Invoice ' . $inv['invoice_number'],
                'description' => 'Payment for services — ' . $inv['client_name'],
            ],
        ],
        'quantity' => 1,
    ]],
    'mode'        => 'payment',
    // Where to send the client after paying or cancelling
    'success_url' => 'http://localhost/invoice_system/stripe/success.php?invoice_id=' . $invoice_id,
    'cancel_url'  => 'http://localhost/invoice_system/pages/view_invoice.php?id=' . $invoice_id,
    // Store the invoice ID so the webhook can find it later
    'metadata'    => [
        'invoice_id' => $invoice_id,
    ],
]);

// Send the client to Stripe's payment page
header('Location: ' . $session->url);
exit;
