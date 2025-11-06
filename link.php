<?php
// Standard PipraPay loader to ensure the environment is ready
if (file_exists(__DIR__."/../../../../pp-config.php")) {
    if (file_exists(__DIR__.'/../../../../maintenance.lock')) {
        die('System is under maintenance. Please try again later.');
    } else {
        // Include core files
        if (file_exists(__DIR__.'/../../../../pp-include/pp-controller.php')) { include_once(__DIR__."/../../../../pp-include/pp-controller.php"); }
        if (file_exists(__DIR__.'/../../../../pp-include/pp-model.php')) { include_once(__DIR__."/../../../../pp-include/pp-model.php"); }
        if (file_exists(__DIR__.'/../../../../pp-include/pp-view.php')) { include_once(__DIR__.'/../../../../pp-include/pp-view.php'); }
    }
} else {
    exit('Configuration file not found.');
}

// Include the plugin's functions file
require_once __DIR__ . '/functions.php';

// Manually establish DB connection if not already connected
cplg_get_db();

// Get link settings based on slug ---
$slug = $_GET['slug'] ?? null;
$settings = null;

if ($slug) {
    $settings = cplg_get_link_settings_by_slug($slug);
}

// If no slug or link not found, fall back to the default link
if (!$settings) {
    $settings = cplg_get_default_link();
    if (!$settings || !isset($settings['id'])) {
        die('Customizable Payment Link plugin is not configured.');
    }
}

// Ensure all default keys exist
$settings = array_merge(cplg_get_default_settings(), $settings);

global $global_setting_response; // Make the global settings variable available

// Correctly determine the currency
$currency = $settings['use_system_currency'] === 'true' && isset($global_setting_response['response'][0]['default_currency'])
            ? htmlspecialchars($global_setting_response['response'][0]['default_currency'])
            : htmlspecialchars($settings['link_currency']);


// --- Reusable "Disabled" Page Function ---
function cplg_show_disabled_page($title, $message, $settings) {
    $page_title = $title;
    $status_title = $title;
    $status_message = $message;
    
    $favicon_link_html = '';
    if (!empty($settings['favicon_url'])) {
        $favicon_link_html = '<link rel="icon" href="' . htmlspecialchars($settings['favicon_url']) . '" type="image/png">';
    } else {
        $favicon_link_html = '<link rel="icon" href="' . pp_get_site_url() . '/pp-content/plugins/modules/customizable-payment-link-generator/assets/icon.png" type="image/png">';
    }

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
// --- END Reusable "Disabled" Page Function ---

// Check if link is enabled
if ($settings['link_enabled'] !== 'true') {
    cplg_show_disabled_page(
        "Payment Link Disabled",
        "This payment link is currently not active. Please contact the administrator for assistance.",
        $settings
    );
}


$is_stocked_item = $settings['amount_mode'] === 'fixed' && $settings['allow_quantity'] === 'true' && (int)$settings['total_stock'] > 0;
$is_sold_out = $is_stocked_item && (int)$settings['current_stock'] <= 0;

if ($is_sold_out) {
    cplg_show_disabled_page(
        "Sold Out",
        "We're sorry, this item is currently sold out.",
        $settings
    );
}



// --- POST Request Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $conn, $db_prefix;
    
    $errors = [];
    $custom_field_data = [];
    $amount = 0;
    $quantity = 1;

    //  Validate Custom Fields (Advanced) ---
    if (!empty($settings['custom_fields']) && $settings['custom_fields'] !== '[]') {
        $fields = json_decode($settings['custom_fields'], true);
        if (is_array($fields)) {
            foreach ($fields as $field) {
                // Only validate enabled fields
                if (empty($field['enabled'])) {
                    continue;
                }
                
                // Create a stable field name
                $field_name = 'cplg_custom_' . preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $field['label'])));
                $label = htmlspecialchars($field['label']);
                
                if ($field['type'] === 'checkbox') {
                    // Checkbox can be an array
                    $value = $_POST[$field_name] ?? [];
                    if (!is_array($value)) { $value = [$value]; } // Ensure it's an array
                    
                    $sanitized_value = [];
                    foreach ($value as $v) {
                        $sanitized_value[] = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
                    }
                    
                    if ($field['required'] && empty($sanitized_value)) {
                        $errors[] = $label . ' is required.';
                    }
                    $custom_field_data[$label] = $sanitized_value;

                } else {
                    // All other fields (text, textarea, select, radio)
                    $value = filter_input(INPUT_POST, $field_name, FILTER_DEFAULT);
                    $sanitized_value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    
                    if ($field['required'] && empty($sanitized_value)) {
                        $errors[] = $label . ' is required.';
                    }
                    $custom_field_data[$label] = $sanitized_value;
                }
            }
        }
    }
    
    // 2. Validate Amount & Quantity
    if ($settings['amount_mode'] === 'fixed') {
        $base_amount = (float)$settings['fixed_amount'];
        
        //  Validate Quantity & Stock ---
        if ($settings['allow_quantity'] === 'true') {
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT);
            $quantity = (int)$quantity;

            if ($quantity <= 0) {
                $errors[] = 'Quantity must be at least 1.';
            }

            if ($is_stocked_item) {
                $current_stock = (int)$settings['current_stock'];
                if ($quantity > $current_stock) {
                    $errors[] = "Only {$current_stock} items are available in stock. Please reduce your quantity.";
                }
            }
        }
        
        $amount = $base_amount * $quantity;

    } else {
        // Custom Amount Mode
        $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $min = (float)$settings['min_amount'];
        $max = (float)$settings['max_amount'];

        if (empty($amount) || $amount <= 0) {
            $errors[] = 'Invalid amount provided.';
        }
        if ($min > 0 && $amount < $min) {
            $errors[] = 'Amount must be at least ' . $min . ' ' . $currency;
        }
        if ($max > 0 && $amount > $max) {
            $errors[] = 'Amount must be no more than ' . $max . ' ' . $currency;
        }
    }

    // 3. Validate Standard Fields
    $name = $settings['show_name_mode'] !== 'disabled' ? filter_input(INPUT_POST, 'name', FILTER_DEFAULT) : 'Customer';
    $email_or_phone = $settings['show_contact_mode'] !== 'disabled' ? filter_input(INPUT_POST, 'email_or_phone', FILTER_DEFAULT) : '';

    if ($settings['show_name_mode'] === 'required' && empty($name)) {
        $errors[] = 'Full Name is required.';
    }
    if ($settings['show_contact_mode'] === 'required' && empty($email_or_phone)) {
        $errors[] = 'Email or Mobile is required.';
    }


    if (!empty($errors)) {
        // Display errors instead of dying
        $error_html = "<ul>";
        foreach ($errors as $error) {
            $error_html .= "<li>" . $error . "</li>";
        }
        $error_html .= "</ul>";
        
        $alert_message = $error_html;

    } else {
        // --- All Valid: Create Transaction ---
        $c_email_mobile = $email_or_phone;
        $sender_number = filter_var($email_or_phone, FILTER_VALIDATE_EMAIL) ? '--' : $email_or_phone;

        $pp_id = rand(100000000, 9999999999);
        $product_name = $settings['link_title'];
        $product_desc = $settings['link_description'];
        
        //  Add quantity to metadata ---
        $metadata = [
            'invoiceid' => 'cplg_'.uniqid(),
            'cplg_link_id' => $settings['id'], // Store the ID of the link being used
            'cplg_quantity' => $quantity, // Store the quantity
            'custom_fields' => $custom_field_data
        ];
        $metadata_json = json_encode($metadata);

        $base_url = pp_get_site_url() . "/pp-content/plugins/modules/customizable-payment-link-generator/";
        $webhook_url = $base_url . "ipn.php?pp_id=" . $pp_id; // IPN should not be a pretty link.
        
        $redirect_url = '';
        $cancel_url = '';

        if ($settings['pretty_link_enabled'] === 'true') {
            $slug_url_part = trim($settings['link_slug'], '/');
            $redirect_url = pp_get_site_url() . "/{$slug_url_part}/success/" . $pp_id;
            $cancel_url = pp_get_site_url() . "/{$slug_url_part}/cancel/" . $pp_id;
        } else {
            //  Fallback to non-pretty URLs ---
            $redirect_url = $base_url . "success.php?pp_id=" . $pp_id;
            $cancel_url = $base_url . "cancel.php?pp_id=" . $pp_id;
        }

        $sql = "INSERT INTO `{$db_prefix}transaction` (
                    `pp_id`, `c_id`, `c_name`, `c_email_mobile`, `payment_method_id`, `payment_method`,
                    `payment_verify_way`, `payment_sender_number`, `payment_verify_id`, `transaction_amount`,
                    `transaction_fee`, `transaction_refund_amount`, `transaction_refund_reason`, `transaction_currency`,
                    `transaction_redirect_url`, `transaction_return_type`, `transaction_cancel_url`, `transaction_webhook_url`,
                    `transaction_metadata`, `transaction_status`, `transaction_product_name`, `transaction_product_description`,
                    `transaction_product_meta`, `created_at`
                ) VALUES (?, '--', ?, ?, '--', '--', 'id', ?, '--', ?, '0', '0', '--', ?, ?, 'GET', ?, ?, ?, 'initialize', ?, ?, '--', NOW())";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die("Error preparing statement: " . $conn->error);
        }

        $stmt->bind_param(
            "ssssdsssssss", 
            $pp_id, $name, $c_email_mobile, $sender_number,
            $amount, $currency, $redirect_url, $cancel_url, $webhook_url,
            $metadata_json, $product_name, $product_desc
        );

        if ($stmt->execute()) {
            $payment_page_url = pp_get_site_url() . "/payment/" . $pp_id;
            header("Location: " . $payment_page_url);
            exit();
        } else {
            die("Error creating transaction: " . $stmt->error);
        }
    }
}
// --- End POST Handling ---


// Robustly handle all newline variations for display
$instruction_text = nl2br(htmlspecialchars(stripslashes(str_replace(['\r\n', '\n'], "\n", $settings['instruction_text']))));

// Prepare suggested amounts
$suggested_amounts = [];
if (!empty($settings['suggested_amounts'])) {
    $parts = explode(',', $settings['suggested_amounts']);
    foreach ($parts as $part) {
        $num = (float)trim($part);
        if ($num > 0) {
            $suggested_amounts[] = $num;
        }
    }
}

//  Prepare custom fields (Advanced) ---
$custom_fields = [];
if (!empty($settings['custom_fields']) && $settings['custom_fields'] !== '[]') {
    $decoded = json_decode($settings['custom_fields'], true);
    if (is_array($decoded)) {
        foreach($decoded as $field) {
            // Only add fields that are enabled
            if (!empty($field['enabled'])) {
                $custom_fields[] = $field;
            }
        }
    }
}


// Set initial amount for display
$initial_amount = $settings['amount_mode'] === 'fixed' ? (float)$settings['fixed_amount'] : 0.00;

// Prepare amount placeholder with min/max ---
$amount_placeholder = "Enter Amount ({$currency})";
if ($settings['amount_mode'] === 'custom') {
    $min = (float)$settings['min_amount'];
    $max = (float)$settings['max_amount'];
    $parts = [];
    if ($min > 0) { $parts[] = "Min: {$min}"; }
    if ($max > 0) { $parts[] = "Max: {$max}"; }
    if (!empty($parts)) {
        $amount_placeholder .= " (" . implode(', ', $parts) . ")";
    }
}

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
        .form-control, .form-select { border-radius: 0.375rem; border: 1px solid #ced4da; padding: 0.75rem 1rem; }
        .form-control:focus, .form-select:focus { border-color: #2dbd83; box-shadow: 0 0 0 0.25rem rgba(45, 189, 131, 0.25); }
        .btn-primary { background-color: #2dbd83; border-color: #2dbd83; font-weight: 600; padding: 0.75rem; border-radius: 0.375rem; transition: background-color 0.2s ease; }
        .btn-primary:hover { background-color: #28a773; border-color: #28a773; }
        .total-due { background-color: #f8f9fa; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .total-due span { color: #6c757d; }
        .total-due strong { color: #333; font-size: 1.25rem; }
        .footer-text { font-size: 0.875rem; color: #6c757d; text-align: center; margin-top: 1.5rem; }
        .instruction-card { padding: 2rem; }
        .instruction-card h5 { font-weight: 600; margin-bottom: 1rem; }
        .instruction-card p { color: #6c757d; line-height: 1.6; margin-bottom: 0; }
        
        .amount-chips-container { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 1.5rem; }
        .amount-chip { background-color: #f1f3f5; border: 1px solid #dee2e6; border-radius: 20px; padding: 8px 16px; font-weight: 500; color: #495057; cursor: pointer; transition: all 0.2s ease; }
        .amount-chip:hover { background-color: #e9ecef; border-color: #ced4da; }
        
        .form-check-group { margin-top: 5px; }
        .form-check { margin-bottom: 5px; }
        .form-label { font-weight: 500; }
    </style>
</head>
<body>
    <div class="container payment-container">
        <div class="text-center">
            <?php if (!empty($settings['logo_url'])): ?>
                <img src="<?php echo htmlspecialchars($settings['logo_url']); ?>" alt="Logo" class="logo-img">
            <?php endif; ?>
        </div>

        <?php if (!empty($alert_message)): ?>
            <div class="alert alert-danger">
                <b>Please correct the following errors:</b>
                <?php echo $alert_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($settings['show_instruction'] === 'true' && !empty(trim($instruction_text))): ?>
        <div class="payment-card instruction-card mb-4">
            <h5>Instructions</h5>
            <p><?php echo $instruction_text; ?></p>
        </div>
        <?php endif; ?>

        <div class="payment-card">
            <h2 class="card-title"><?php echo htmlspecialchars($settings['link_title']); ?></h2>
            <p class="card-subtitle"><?php echo htmlspecialchars(stripslashes($settings['link_description'])); ?></p>
            
            <form method="post" action="" id="paymentForm">
                
                <?php if ($settings['amount_mode'] === 'fixed'): ?>
                    <input type="hidden" id="base_amount" value="<?php echo $initial_amount; ?>">
                    
                    <?php if ($settings['allow_quantity'] === 'true'): ?>
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" step="1" min="1" 
                                   <?php echo $is_stocked_item ? 'max="' . (int)$settings['current_stock'] . '"' : ''; ?>
                                   class="form-control" id="quantity" name="quantity" value="1" required>
                            <?php if ($is_stocked_item): ?>
                                <small class="form-text text-muted"><?php echo (int)$settings['current_stock']; ?> available</small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount</label>
                        <input type="number" step="0.01" 
                               min="<?php echo (float)$settings['min_amount'] > 0 ? (float)$settings['min_amount'] : '0.01'; ?>" 
                               <?php echo (float)$settings['max_amount'] > 0 ? 'max="' . (float)$settings['max_amount'] . '"' : ''; ?>
                               class="form-control" id="amount" name="amount" 
                               placeholder="<?php echo $amount_placeholder; ?>" required>
                    </div>
                <?php endif; ?>

                <?php if ($settings['amount_mode'] === 'custom' && !empty($suggested_amounts)): ?>
                <div class="amount-chips-container">
                    <?php foreach ($suggested_amounts as $sa): ?>
                    <button type="button" class="amount-chip" data-amount="<?php echo $sa; ?>"><?php echo $sa; ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="total-due">
                    <span>Total due</span>
                    <strong id="totalDue"><?php echo number_format($initial_amount, 2); ?> <?php echo $currency; ?></strong>
                </div>

                <?php if ($settings['show_name_mode'] !== 'disabled' || $settings['show_contact_mode'] !== 'disabled'): ?>
                    <h5 class="mt-4 mb-3 fs-6 fw-semibold">Customer Details</h5>
                    <div class="row">
                        <?php if ($settings['show_name_mode'] !== 'disabled'): ?>
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label visually-hidden">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   placeholder="Full Name<?php echo $settings['show_name_mode'] === 'required' ? ' *' : ''; ?>" 
                                   <?php echo $settings['show_name_mode'] === 'required' ? 'required' : ''; ?>>
                        </div>
                        <?php endif; ?>

                        <?php if ($settings['show_contact_mode'] !== 'disabled'): ?>
                        <div class="col-md-6 mb-3">
                             <label for="email_or_phone" class="form-label visually-hidden">Email or Mobile</label>
                            <input type="text" class="form-control" id="email_or_phone" name="email_or_phone" 
                                   placeholder="Email or Mobile<?php echo $settings['show_contact_mode'] === 'required' ? ' *' : ''; ?>" 
                                   <?php echo $settings['show_contact_mode'] === 'required' ? 'required' : ''; ?>>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($custom_fields)): ?>
                    <?php foreach ($custom_fields as $field): ?>
                        <?php
                        $field_name = 'cplg_custom_' . preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $field['label'])));
                        $label = htmlspecialchars($field['label']);
                        $is_required = !empty($field['required']);
                        $placeholder = $label . ($is_required ? ' *' : '');
                        $options_string = str_replace(['\r\n', '\n'], "\n", $field['options']);
						$options = explode("\n", $options_string);
                        ?>
                        
                        <div class="mb-3">
                            <label for="<?php echo $field_name; ?>" class="form-label"><?php echo $label; ?><?php echo $is_required ? ' <span class="text-danger">*</span>' : ''; ?></label>
                            
                            <?php if ($field['type'] === 'text'): ?>
                                <input type="text" class="form-control" id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>" 
                                       placeholder="<?php echo $placeholder; ?>" <?php echo $is_required ? 'required' : ''; ?>>
                            
                            <?php elseif ($field['type'] === 'textarea'): ?>
                                <textarea class="form-control" id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>" 
                                          rows="3" placeholder="<?php echo $placeholder; ?>" <?php echo $is_required ? 'required' : ''; ?>></textarea>
                            
                            <?php elseif ($field['type'] === 'select'): ?>
                                <select class="form-select" id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>" <?php echo $is_required ? 'required' : ''; ?>>
                                    <option value="">Select <?php echo $label; ?>...</option>
                                    <?php foreach ($options as $opt): $opt = trim($opt); if (!empty($opt)): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endif; endforeach; ?>
                                </select>
                            
                            <?php elseif ($field['type'] === 'radio'): ?>
                                <div class="form-check-group border p-2 rounded">
                                    <?php foreach ($options as $index => $opt): $opt = trim($opt); if (!empty($opt)): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="<?php echo $field_name; ?>" id="<?php echo $field_name . $index; ?>" 
                                                   value="<?php echo htmlspecialchars($opt); ?>" <?php echo $is_required ? 'required' : ''; ?>>
                                            <label class="form-check-label" for="<?php echo $field_name . $index; ?>">
                                                <?php echo htmlspecialchars($opt); ?>
                                            </label>
                                        </div>
                                    <?php endif; endforeach; ?>
                                </div>
                            
                            <?php elseif ($field['type'] === 'checkbox'): ?>
                                <div class="form-check-group border p-2 rounded">
                                    <?php foreach ($options as $index => $opt): $opt = trim($opt); if (!empty($opt)): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="<?php echo $field_name; ?>[]" id="<?php echo $field_name . $index; ?>" 
                                                   value="<?php echo htmlspecialchars($opt); ?>" <?php echo $is_required ? 'data-is-required="true"' : ''; ?>>
                                            <label class="form-check-label" for="<?php echo $field_name . $index; ?>">
                                                <?php echo htmlspecialchars($opt); ?>
                                            </label>
                                        </div>
                                    <?php endif; endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary w-100" id="payButton">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock-fill" viewBox="0 0 16 16" style="vertical-align: text-bottom; margin-right: 5px;">
                      <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                    </svg>
                    Pay <?php echo number_format($initial_amount, 2); ?> <?php echo $currency; ?>
                </button>
            </form>
            
            <?php if ($settings['show_footer_text'] === 'true'): ?>
            <p class="footer-text">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-lock-fill" viewBox="0 0 16 16" style="vertical-align: text-bottom; margin-right: 2px;">
                    <path fill-rule="evenodd" d="M8 0c1.657 0 3 .879 3.879 2.046A.5.5 0 0 1 11.5 2.5H11a.5.5 0 0 1 .454.296A2 2 0 0 1 13 5v2.293l.354.353a.5.5 0 0 1 0 .707l-1.414 1.414a.5.5 0 0 1-.707 0l-.354-.353V13.5a2.5 2.5 0 0 1-5 0V9.207l-.354.353a.5.5 0 0 1-.707 0L3.207 8.146a.5.5 0 0 1 0-.707L3.561 7.293V5a2 2 0 0 1 1.546-1.954A.5.5 0 0 1 5.5 2.5H5a.5.5 0 0 1-.379-.546A3 3 0 0 1 8 0zm0 1.5a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3a.5.5 0 0 1 .5-.5z"/>
                </svg>
                <?php echo htmlspecialchars($settings['footer_text']); ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.getElementById('amount');
            const quantityInput = document.getElementById('quantity');
            const baseAmountInput = document.getElementById('base_amount');
            
            const totalDueEl = document.getElementById('totalDue');
            const payButton = document.getElementById('payButton');
            const currency = '<?php echo $currency; ?>';
            const amountMode = '<?php echo $settings['amount_mode']; ?>';

            let currentBaseAmount = <?php echo $initial_amount; ?>;
            let currentQuantity = 1;

            function updateDisplay() {
                let amount = 0;
                
                if (amountMode === 'fixed') {
                    currentBaseAmount = baseAmountInput ? parseFloat(baseAmountInput.value) : 0;
                    currentQuantity = quantityInput ? parseInt(quantityInput.value) : 1;
                    if (isNaN(currentQuantity) || currentQuantity < 1) { currentQuantity = 1; }
                    amount = currentBaseAmount * currentQuantity;
                } else {
                    amount = amountInput ? parseFloat(amountInput.value) : 0;
                }

                let formattedAmount = amount > 0 ? parseFloat(amount).toFixed(2) : '0.00';
                
                totalDueEl.textContent = `${formattedAmount} ${currency}`;
                payButton.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock-fill" viewBox="0 0 16 16" style="vertical-align: text-bottom; margin-right: 5px;">
                      <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                    </svg>
                    Pay ${formattedAmount} ${currency}`;
            }

            if (amountMode === 'custom' && amountInput) {
                amountInput.addEventListener('input', updateDisplay);
            }
            
            if (amountMode === 'fixed' && quantityInput) {
                quantityInput.addEventListener('input', updateDisplay);
            }
            
            // Handle Amount Chips
            document.querySelectorAll('.amount-chip').forEach(chip => {
                chip.addEventListener('click', function(e) {
                    e.preventDefault();
                    const amount = this.getAttribute('data-amount');
                    if (amountInput) {
                        amountInput.value = amount;
                        updateDisplay();
                    }
                });
            });

            // Form validation for min/max
            const form = document.getElementById('paymentForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (amountMode === 'custom' && amountInput) {
                        const amount = parseFloat(amountInput.value);
                        const min = parseFloat(amountInput.min);
                        const max = parseFloat(amountInput.max);

                        if (min > 0 && amount < min) {
                            alert('Amount must be at least ' + min + ' ' + currency);
                            e.preventDefault();
                        }
                        if (max > 0 && amount > max) {
                            alert('Amount must be no more than ' + max + ' ' + currency);
                            e.preventDefault();
                        }
                    }
                    
                    //  Checkbox required validation ---
                    document.querySelectorAll('.form-check-group').forEach(group => {
                        const firstCheckbox = group.querySelector('input[type="checkbox"][data-is-required="true"]');
                        if (firstCheckbox) {
                            const isRequired = firstCheckbox.getAttribute('data-is-required') === 'true';
                            if (isRequired) {
                                const checkedBoxes = group.querySelectorAll('input[type="checkbox"]:checked');
                                if (checkedBoxes.length === 0) {
                                    const label = group.closest('.mb-3').querySelector('label').textContent;
                                    alert('Please select at least one option for ' + label.replace(' *', ''));
                                    e.preventDefault();
                                }
                            }
                        }
                    });
                });
            }
            
            // Initial call
            updateDisplay();
        });
    </script>
</body>
</html>