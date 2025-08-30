<?php
// Load PipraPay environment to access settings
if (file_exists(__DIR__."/../../../../pp-config.php")) {
    if (file_exists(__DIR__.'/../../../../pp-include/pp-controller.php')) {
        include_once(__DIR__."/../../../../pp-include/pp-controller.php");
    }
}
// Include the plugin's functions file
require_once __DIR__ . '/functions.php';

$settings = cplg_get_settings();

$payment_link = pp_get_site_url() . '/pp-content/plugins/modules/customizable-payment-link-generator/link';
if ($settings['pretty_link_enabled'] === 'true' && !empty($settings['pretty_link_slug'])) {
    $payment_link = pp_get_site_url() . '/' . trim($settings['pretty_link_slug'], '/');
}

// Get transaction details
$pp_id = $_GET['pp_id'] ?? null;
$transaction_details = null;
if ($pp_id) {
    $transaction = pp_get_transation($pp_id);
    if (isset($transaction['response'][0])) {
        $t = $transaction['response'][0];

        // Redirect to pending page if payment is not completed
        if ($t['transaction_status'] === 'pending') {
            $base_url = pp_get_site_url() . "/pp-content/plugins/modules/customizable-payment-link-generator/";
            header("Location: " . $base_url . "pending.php?pp_id=" . $pp_id);
            exit();
        }

        $metadata = isset($t['transaction_metadata']) ? json_decode($t['transaction_metadata'], true) : [];
        if (!is_array($metadata)) $metadata = [];

        $transaction_details = [
            'amount' => htmlspecialchars($t['transaction_amount']),
            'currency' => htmlspecialchars($t['transaction_currency']),
            'customer_name' => htmlspecialchars($t['c_name']),
            'payment_method' => htmlspecialchars(!empty($t['payment_method']) && $t['payment_method'] !== '--' ? $t['payment_method'] : ($metadata['payment_method'] ?? 'N/A')),
            'sender_number' => htmlspecialchars(!empty($t['payment_sender_number']) && $t['payment_sender_number'] !== '--' ? $t['payment_sender_number'] : ($metadata['sender_number'] ?? $metadata['phone'] ?? 'N/A')),
            'date' => htmlspecialchars($t['created_at'] ? date("d M Y, h:i A", strtotime($t['created_at'])) : 'N/A'),
            'payment_id' => htmlspecialchars($t['pp_id'] ?? 'N/A'),
            'gateway_trx_id' => htmlspecialchars($t['payment_verify_id'] ?? 'N/A')
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
	<?php if (!empty($settings['favicon_url'])): ?>
        <link rel="icon" href="<?php echo htmlspecialchars($settings['favicon_url']); ?>" type="image/png">
    <?php else: ?>
        <link rel="icon" href="<?php echo pp_get_site_url(); ?>/pp-content/plugins/modules/customizable-payment-link-generator/assets/icon.png" type="image/png">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .status-container { max-width: 550px; margin: 4rem auto; }
        .status-card { background: #fff; border-radius: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 3rem 2.5rem; text-align: center; }
        .logo-img { max-height: 40px; margin-bottom: 1.5rem; }
        .status-icon { margin-bottom: 1.5rem; color: #2dbd83; }
        .status-icon svg { width: 60px; height: 60px; }
        .card-title { font-weight: 600; color: #333; margin-bottom: 0.5rem; }
        .card-text { color: #6c757d; font-size: 1rem; }
        .payment-details { list-style: none; padding: 0; margin-top: 2rem; text-align: left; }
        .payment-details li { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #e9ecef; }
        .payment-details li:last-child { border-bottom: none; }
        .payment-details span { color: #6c757d; }
        .payment-details strong { color: #333; }
        .btn-primary { background-color: #2dbd83; border-color: #2dbd83; font-weight: 600; padding: 0.75rem; border-radius: 0.375rem; transition: background-color 0.2s ease; margin-top: 2rem; }
        .btn-primary:hover { background-color: #28a773; border-color: #28a773; }
    </style>
</head>
<body>
    <div class="container status-container">
        <div class="text-center">
            <?php if (!empty($settings['logo_url'])): ?>
                <a href="<?php echo $payment_link; ?>"><img src="<?php echo htmlspecialchars($settings['logo_url']); ?>" alt="Logo" class="logo-img"></a>
            <?php endif; ?>
        </div>
        <div class="status-card">
            <div class="status-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                </svg>
            </div>
            <h4 class="card-title">Payment Successful</h4>
            <p class="card-text">Thank you! Your payment has been processed successfully.</p>
            
            <?php if ($transaction_details): ?>
            <ul class="payment-details">
                <li><span>Amount:</span><strong><?php echo $transaction_details['amount'] . ' ' . $transaction_details['currency']; ?></strong></li>
                <li><span>From:</span><strong><?php echo $transaction_details['customer_name']; ?></strong></li>
                <li><span>Method:</span><strong><?php echo $transaction_details['payment_method']; ?></strong></li>
                <li><span>Sender:</span><strong><?php echo $transaction_details['sender_number']; ?></strong></li>
                <li><span>Date:</span><strong><?php echo $transaction_details['date']; ?></strong></li>
                <li><span>Payment ID:</span><strong><?php echo $transaction_details['payment_id']; ?></strong></li>
                <li><span>Transaction ID:</span><strong><?php echo $transaction_details['gateway_trx_id']; ?></strong></li>
            </ul>
            <?php endif; ?>

            <a href="<?php echo $payment_link; ?>" class="btn btn-primary w-100">Make Another Payment</a>
        </div>
    </div>
</body>
</html>