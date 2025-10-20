<?php
// Standard PipraPay loader to ensure the environment is ready
if (file_exists(__DIR__."/../../../../pp-config.php")) {
    if (file_exists(__DIR__.'/../../../../maintenance.lock')) {
        die('System is under maintenance. Please try again later.');
    } else {
        // Include core files
        if (file_exists(__DIR__.'/../../../../pp-include/pp-controller.php')) { include_once(__DIR__."/../../../../pp-include/pp-controller.php"); }
        if (file_exists(__DIR__.'/../../../../pp-include/pp-model.php')) { include_once(__DIR__."/../../../../pp-include/pp-model.php"); }
        if (file_exists(__DIR__.'/../../../../pp-include/pp-view.php')) { include_once(__DIR__."/../../../../pp-include/pp-view.php"); }
    }
} else {
    exit('Configuration file not found.');
}

// Include the plugin's functions file
require_once __DIR__ . '/functions.php';

// Manually establish DB connection if not already connected
global $conn, $db_host, $db_user, $db_pass, $db_name, $db_prefix;
if (!isset($conn) || $conn->connect_error) {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die("Database Connection Failed: " . $conn->connect_error);
    }
}

$settings = cplg_get_settings();
global $global_setting_response; // Make the global settings variable available

// Correctly determine the currency
$currency = $settings['use_system_currency'] === 'true' && isset($global_setting_response['response'][0]['default_currency'])
            ? htmlspecialchars($global_setting_response['response'][0]['default_currency'])
            : htmlspecialchars($settings['link_currency']);


if ($settings['link_enabled'] !== 'true') {
    // Display a styled "disabled" page instead of a plain die() message.
    $page_title = "Payment Link Disabled";
    $status_title = "Payment Link Disabled";
    $status_message = "This payment link is currently not active. Please contact the administrator for assistance.";
    
    //  Prepare favicon HTML before the HEREDOC
    $favicon_link_html = '';
    if (!empty($settings['favicon_url'])) {
        $favicon_link_html = '<link rel="icon" href="' . htmlspecialchars($settings['favicon_url']) . '" type="image/png">';
    } else {
        $favicon_link_html = '<link rel="icon" href="' . pp_get_site_url() . '/pp-content/plugins/modules/customizable-payment-link-generator/assets/icon.png" type="image/png">';
    }

    // Use a structure similar to cancel.php for a consistent look.
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$page_title}</title>
    {$favicon_link_html}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .status-container { max-width: 550px; margin: 4rem auto; text-align: center; }
        .status-card { background: #fff; border-radius: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 3rem 2.5rem; }
        .logo-img { max-height: 40px; margin-bottom: 1.5rem; }
        .status-icon { margin-bottom: 1.5rem; color: #dc3545; }
        .status-icon svg { width: 60px; height: 60px; }
        .card-title { font-weight: 600; color: #333; margin-bottom: 0.5rem; }
        .card-text { color: #6c757d; font-size: 1rem; }
    </style>
</head>
<body>
    <div class="container status-container">
        <div class="text-center">
HTML;
    if (!empty($settings['logo_url'])) {
        echo '<img src="' . htmlspecialchars($settings['logo_url']) . '" alt="Logo" class="logo-img">';
    }
    echo <<<HTML
        </div>
        <div class="status-card">
            <div class="status-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/>
                </svg>
            </div>
            <h4 class="card-title">{$status_title}</h4>
            <p class="card-text">{$status_message}</p>
        </div>
    </div>
</body>
</html>
HTML;
    exit(); // Stop further script execution
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    
    // --- FIX for Deprecated FILTER_SANITIZE_STRING ---
    $name = $settings['show_name'] === 'true' ? filter_input(INPUT_POST, 'name', FILTER_DEFAULT) : 'Customer';
    $email_or_phone = $settings['show_contact'] === 'true' ? filter_input(INPUT_POST, 'email_or_phone', FILTER_DEFAULT) : '';
    // --- END FIX ---

    if (empty($amount) || $amount <= 0) {
        die('Invalid amount provided.');
    }

    // --- Prepare a COMPLETE set of data for the transaction record ---
    $c_email_mobile = $email_or_phone;
    // Check if the input is a valid email, if not, treat it as a phone number for the sender_number field.
    $sender_number = filter_var($email_or_phone, FILTER_VALIDATE_EMAIL) ? '--' : $email_or_phone;

    $pp_id = rand(100000000, 9999999999);
    $product_name = $settings['link_title'];
    $product_desc = $settings['link_description'];
    $verify_way = 'id';
    $metadata_json = json_encode(['invoiceid' => 'cplg_'.uniqid()]);
    $base_url = pp_get_site_url() . "/pp-content/plugins/modules/customizable-payment-link-generator/";
    $redirect_url = $base_url . "success.php?pp_id=" . $pp_id;
    $cancel_url = $base_url . "cancel.php?pp_id=" . $pp_id;
    $webhook_url = $base_url . "ipn.php?pp_id=" . $pp_id;

    // --- SQL statement with 13 placeholders ---
    $sql = "INSERT INTO `{$db_prefix}transaction` (
                `pp_id`, `c_id`, `c_name`, `c_email_mobile`, `payment_method_id`, `payment_method`,
                `payment_verify_way`, `payment_sender_number`, `payment_verify_id`, `transaction_amount`,
                `transaction_fee`, `transaction_refund_amount`, `transaction_refund_reason`, `transaction_currency`,
                `transaction_redirect_url`, `transaction_return_type`, `transaction_cancel_url`, `transaction_webhook_url`,
                `transaction_metadata`, `transaction_status`, `transaction_product_name`, `transaction_product_description`,
                `transaction_product_meta`, `created_at`
            ) VALUES (?, '--', ?, ?, '--', '--', ?, ?, '--', ?, '0', '0', '--', ?, ?, 'GET', ?, ?, ?, 'initialize', ?, ?, '--', NOW())";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param(
        "sssssdsssssss",
        $pp_id, $name, $c_email_mobile, $verify_way, $sender_number,
        $amount, $currency, $redirect_url, $cancel_url, $webhook_url,
        $metadata_json, $product_name, $product_desc
    );

    if ($stmt->execute()) {
        $payment_page_url = pp_get_site_url() . "/payment/" . $pp_id;
        header("Location: " . $payment_page_url); // This is line 145
        exit();
    } else {
        die("Error creating transaction: " . $stmt->error);
    }
}

// Robustly handle all newline variations for display
$instruction_text = nl2br(htmlspecialchars(stripslashes(str_replace('\r\n', "\n", $settings['instruction_text']))));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['link_title']); ?></title>
	<?php if (!empty($settings['favicon_url'])): ?>
        <link rel="icon" href="<?php echo htmlspecialchars($settings['favicon_url']); ?>" type="image/png">
    <?php else: ?>
        <link rel="icon" href="<?php echo pp_get_site_url(); ?>/pp-content/plugins/modules/customizable-payment-link-generator/assets/icon.png" type="image/png">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .payment-container { max-width: 550px; margin: 4rem auto; }
        .payment-card { background: #fff; border-radius: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 2.5rem; }
        .logo-img { max-height: 40px; margin-bottom: 1.5rem; }
        .card-title { font-weight: 600; color: #333; }
        .card-subtitle { color: #6c757d; margin-bottom: 2rem; }
        .form-control { border-radius: 0.375rem; border: 1px solid #ced4da; padding: 0.75rem 1rem; }
        .form-control:focus { border-color: #2dbd83; box-shadow: 0 0 0 0.25rem rgba(45, 189, 131, 0.25); }
        .btn-primary { background-color: #2dbd83; border-color: #2dbd83; font-weight: 600; padding: 0.75rem; border-radius: 0.375rem; transition: background-color 0.2s ease; }
        .btn-primary:hover { background-color: #28a773; border-color: #28a773; }
        .total-due { background-color: #f8f9fa; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .total-due span { color: #6c757d; }
        .total-due strong { color: #333; font-size: 1.25rem; }
        .footer-text { font-size: 0.875rem; color: #6c757d; text-align: center; margin-top: 1.5rem; }
        .footer-text svg { vertical-align: middle; margin-right: 5px; width: 16px; height: 16px; }
        
        .instruction-card {
            padding: 2rem;
        }
        .instruction-card h5 {
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .instruction-card p {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container payment-container">
        <div class="text-center">
            <?php if (!empty($settings['logo_url'])): ?>
                <img src="<?php echo htmlspecialchars($settings['logo_url']); ?>" alt="Logo" class="logo-img">
            <?php endif; ?>
        </div>

        <?php if ($settings['show_instruction'] === 'true' && !empty(trim($instruction_text))): ?>
        <div class="payment-card instruction-card mb-4">
            <h5>Instructions</h5>
            <p><?php echo $instruction_text; ?></p>
        </div>
        <?php endif; ?>

        <div class="payment-card">
            <h2 class="card-title">Complete your payment</h2>
            <p class="card-subtitle"><?php echo htmlspecialchars($settings['link_description']); ?></p>
            
            <form method="post" action="" id="paymentForm">
                <div class="mb-3">
                    <label for="amount" class="form-label visually-hidden">Amount</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" placeholder="Enter Amount (<?php echo $currency; ?>)" required>
                </div>

                <div class="total-due">
                    <span>Total due</span>
                    <strong id="totalDue">0.00 <?php echo $currency; ?></strong>
                </div>

                <?php if ($settings['show_name'] === 'true' || $settings['show_contact'] === 'true'): ?>
                <h5 class="mt-4 mb-3 fs-6 fw-semibold">Customer Details</h5>
                <?php endif; ?>

                <div class="row">
                    <?php if ($settings['show_name'] === 'true'): ?>
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label visually-hidden">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="Full Name" required>
                    </div>
                    <?php endif; ?>

                    <?php if ($settings['show_contact'] === 'true'): ?>
                    <div class="col-md-6 mb-3">
                         <label for="email_or_phone" class="form-label visually-hidden">Email or Mobile</label>
                        <input type="text" class="form-control" id="email_or_phone" name="email_or_phone" placeholder="Email or Mobile" required>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100" id="payButton">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock-fill" viewBox="0 0 16 16" style="vertical-align: text-bottom; margin-right: 5px;">
                      <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                    </svg>
                    Pay 0.00 <?php echo $currency; ?>
                </button>
            </form>
            <?php if ($settings['show_footer_text'] === 'true'): ?>
            <p class="footer-text">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-lock-fill" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M8 0c-.81 0-1.42.083-1.933.22a.76.76 0 0 0-.398.24l-.11.11a.76.76 0 0 0-.24.398C5.083 1.58 5 2.19 5 3c0 .81.083 1.42.22 1.933a.76.76 0 0 0 .24.398l.11.11a.76.76 0 0 0 .398.24C6.58 5.917 7.19 6 8 6s1.42-.083 1.933-.22a.76.76 0 0 0 .398-.24l.11-.11a.76.76 0 0 0 .24-.398C10.917 4.42 11 3.81 11 3c0-.81-.083-1.42-.22-1.933a.76.76 0 0 0-.24-.398l-.11-.11a.76.76 0 0 0-.398-.24C9.42 0.083 8.81 0 8 0zm0 5a1.5 1.5 0 0 1 .5 2.915V11a.5.5 0 0 1-1 0V7.915A1.5 1.5 0 0 1 8 5z"/>
                    <path d="M6.5 10.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5z"/>
                </svg>
                <?php echo htmlspecialchars($settings['footer_text']); ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.getElementById('amount');
            const totalDueEl = document.getElementById('totalDue');
            const payButton = document.getElementById('payButton');
            const currency = '<?php echo $currency; ?>';

            amountInput.addEventListener('input', function() {
                let amount = parseFloat(this.value) || 0;
                let formattedAmount = amount > 0 ? amount.toFixed(2) : '0.00';
                
                totalDueEl.textContent = `${formattedAmount} ${currency}`;
                payButton.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock-fill" viewBox="0 0 16 16" style="vertical-align: text-bottom; margin-right: 5px;">
                      <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                    </svg>
                    Pay ${formattedAmount} ${currency}`;
            });
        });
    </script>
</body>
</html>