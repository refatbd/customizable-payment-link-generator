<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

global $cplg_db_checked;
$cplg_db_checked = false;

// --- Constants for Update Process ---
define('CPLG_CURRENT_VERSION', '3.1.0');
define('CPLG_GITHUB_REPO', 'refatbd/customizable-payment-link-generator');
define('CPLG_REFAT_SERVER_URL', 'https://wordpress.refat.ovh/api/update.php');
define('CPLG_PLUGIN_SLUG', 'customizable-payment-link-generator');

/**
 * Get the database connection and run JIT setup.
 */
function cplg_get_db() {
    global $conn, $db_host, $db_user, $db_pass, $db_name;
    if (!isset($conn) || $conn->connect_error) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($conn->connect_error) {
            die("Database connection failed: " . $conn->connect_error);
        }
    }

    // JIT Database Setup
    global $cplg_db_checked;
    if (!$cplg_db_checked) {
        cplg_check_and_setup_database($conn);
        $cplg_db_checked = true;
    }

    return $conn;
}

/**
 * Checks if the database table exists and updates it if needed.
 */
function cplg_check_and_setup_database($conn) {
    global $db_prefix;
    $table_name_only = "{$db_prefix}cplg_links";
    
    $result = $conn->query("SHOW TABLES LIKE '{$table_name_only}'");
    
    if ($result && $result->num_rows > 0) {
        // Table exists, check for new columns and add them if missing
        
        // Check for 'amount_mode'
        $col_check_amount_mode = $conn->query("SHOW COLUMNS FROM `{$table_name_only}` LIKE 'amount_mode'");
        if (!$col_check_amount_mode || $col_check_amount_mode->num_rows == 0) {
            $conn->query("DROP TABLE `{$table_name_only}`");
            cplg_setup_database($conn);
            return;
        }

        // --- Check for Quantity/Stock columns ---
        $col_check_quantity = $conn->query("SHOW COLUMNS FROM `{$table_name_only}` LIKE 'allow_quantity'");
        if (!$col_check_quantity || $col_check_quantity->num_rows == 0) {
            $conn->query("ALTER TABLE `{$table_name_only}` 
                          ADD COLUMN `allow_quantity` VARCHAR(10) NOT NULL DEFAULT 'false' AFTER `redirect_url`,
                          ADD COLUMN `total_stock` INT(11) DEFAULT 0 AFTER `allow_quantity`,
                          ADD COLUMN `current_stock` INT(11) DEFAULT 0 AFTER `total_stock`;");
        }

    } else {
        // Table doesn't exist, run the setup
        cplg_setup_database($conn);
    }
}

/**
 * Get the link database table name.
 */
function cplg_get_table_name() {
    global $db_prefix;
    return "{$db_prefix}cplg_links";
}

/**
 * Default settings for a new link.
 */
function cplg_get_default_settings() {
    return [
        'link_slug' => 'custom-payment',
        'is_default' => 0,
        'link_enabled' => 'true',
        'link_title' => 'Customizable Payment',
        'link_description' => 'Use this link to make a custom payment.',
        'link_currency' => 'USD',
        'use_system_currency' => 'false',
        'show_name' => 'true',
        'show_contact' => 'true',
        'logo_url' => '',
        'favicon_url' => '',
        'show_footer_text' => 'true',
        'footer_text' => 'Payments are secure and encrypted with PipraPay',
        'pretty_link_enabled' => 'true',
        'show_instruction' => 'true',
        'instruction_text' => "To Complete Your Payment:\n\nEnter your due amount, full name, and either your email address or mobile number.\nClick the \"Pay\" button.\nChoose your preferred payment method.\nComplete the payment process.",
        
        'amount_mode' => 'custom', // 'custom' or 'fixed'
        'fixed_amount' => '10.00',
        'min_amount' => '1.00',
        'max_amount' => '0.00', // 0 = no limit
        'suggested_amounts' => '10, 20, 50',
        'custom_fields' => '[]', // JSON array of {type: "text", label: "Field", required: false, enabled: true, options: ""}
        'redirect_url' => '',

        'allow_quantity' => 'false',
        'total_stock' => '0', // 0 = unlimited
        'current_stock' => '0',
    ];
}

/**
 * Plugin Activation: Create table.
 */
function cplg_setup_database($conn) {
    $table_name = cplg_get_table_name();

    // SQL to create the new table with all new fields
    $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `link_slug` VARCHAR(255) NOT NULL,
        `is_default` TINYINT(1) NOT NULL DEFAULT 0,
        `link_enabled` VARCHAR(10) NOT NULL DEFAULT 'true',
        `link_title` VARCHAR(255) NOT NULL,
        `link_description` TEXT,
        `link_currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
        `use_system_currency` VARCHAR(10) NOT NULL DEFAULT 'false',
        `show_name` VARCHAR(10) NOT NULL DEFAULT 'true',
        `show_contact` VARCHAR(10) NOT NULL DEFAULT 'true',
        `logo_url` VARCHAR(500),
        `favicon_url` VARCHAR(500),
        `show_footer_text` VARCHAR(10) NOT NULL DEFAULT 'true',
        `footer_text` VARCHAR(255),
        `pretty_link_enabled` VARCHAR(10) NOT NULL DEFAULT 'false',
        `show_instruction` VARCHAR(10) NOT NULL DEFAULT 'true',
        `instruction_text` TEXT,
        
        `amount_mode` VARCHAR(10) NOT NULL DEFAULT 'custom',
        `fixed_amount` DECIMAL(20,2) DEFAULT 0.00,
        `min_amount` DECIMAL(20,2) DEFAULT 0.00,
        `max_amount` DECIMAL(20,2) DEFAULT 0.00,
        `suggested_amounts` VARCHAR(255) DEFAULT '',
        `custom_fields` TEXT,
        `redirect_url` VARCHAR(500) DEFAULT '',

        `allow_quantity` VARCHAR(10) NOT NULL DEFAULT 'false',
        `total_stock` INT(11) DEFAULT 0,
        `current_stock` INT(11) DEFAULT 0,

        PRIMARY KEY (`id`),
        UNIQUE KEY `link_slug` (`link_slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conn->query($sql);

    // Check if the table is empty
    $result = $conn->query("SELECT COUNT(*) as count FROM `{$table_name}`");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Create a default link since no data exists
        $settings_to_insert = cplg_get_default_settings();
        $settings_to_insert['link_title'] = 'Default Payment Link';
        $settings_to_insert['link_slug'] = 'custom-payment';
        $settings_to_insert['is_default'] = 1;
        $settings_to_insert['pretty_link_enabled'] = 'true';
        $settings_to_insert['min_amount'] = '1.00';
        $settings_to_insert['suggested_amounts'] = '5, 10, 25';

        cplg_save_link($settings_to_insert, null, true);
    }
}

/**
 * Get settings for a specific link by ID.
 */
function cplg_get_link_settings($id) {
    $conn = cplg_get_db();
    $table_name = cplg_get_table_name();
    
    $stmt = $conn->prepare("SELECT * FROM `{$table_name}` WHERE `id` = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Get settings for a specific link by SLUG.
 */
function cplg_get_link_settings_by_slug($slug) {
    $conn = cplg_get_db();
    $table_name = cplg_get_table_name();
    
    $stmt = $conn->prepare("SELECT * FROM `{$table_name}` WHERE `link_slug` = ?");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Get the default link settings.
 */
function cplg_get_default_link() {
    $conn = cplg_get_db();
    $table_name = cplg_get_table_name();
    
    $result = $conn->query("SELECT * FROM `{$table_name}` WHERE `is_default` = 1 LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    // Fallback if no default is set
    $result = $conn->query("SELECT * FROM `{$table_name}` LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return cplg_get_default_settings();
}

/**
 * Get all created links.
 */
function cplg_get_all_links() {
    $conn = cplg_get_db();
    $table_name = cplg_get_table_name();
    $links = [];
    
    $result = $conn->query("SELECT * FROM `{$table_name}` ORDER BY `is_default` DESC, `id` ASC");
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $links[] = $row;
        }
    }
    return $links;
}

/**
 * Get all available currencies.
 */
function cplg_get_all_currencies() {
    $conn = cplg_get_db();
    global $db_prefix;
    $currencies = [];
    $sql = "SELECT currency_code, currency_name FROM `{$db_prefix}currency`";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $currencies[$row['currency_code']] = $row['currency_name'];
        }
    }
    return $currencies;
}

/**
 * Save (create or update) a link.
 */
function cplg_save_link($data, $id = null, $is_setup = false) {
    $conn = cplg_get_db();
    $table_name = cplg_get_table_name();
    global $global_setting_response;

    $use_system_currency = isset($data['use_system_currency']) ? 'true' : 'false';
    
    if ($use_system_currency === 'true') {
        $link_currency = isset($global_setting_response['response'][0]['default_currency']) 
                         ? $global_setting_response['response'][0]['default_currency'] 
                         : 'USD';
    } else {
        $link_currency = escape_string($data['link_currency']);
    }

    $slug = strtolower($data['link_slug']);
    $slug = preg_replace('/[^a-z0-9_\-.\/]/', '', $slug);
    $slug = trim($slug, '/');
    if (empty($slug)) {
        $slug = 'custom-payment-' . time();
    }

    if (!$is_setup) {
        $check_sql = "SELECT id FROM `{$table_name}` WHERE `link_slug` = ? AND `id` != ?";
        $stmt_check = $conn->prepare($check_sql);
        $check_id = $id ? $id : 0;
        $stmt_check->bind_param("si", $slug, $check_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $slug = $slug . '-' . rand(100, 999);
        }
    }
    
    // ---  Validate and sanitize advanced custom fields JSON ---
    $custom_fields_json = $data['custom_fields_json'] ?? '[]';
    $custom_fields_decoded = json_decode($custom_fields_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($custom_fields_decoded)) {
        $custom_fields_json = '[]';
    } else {
        $sanitized_fields = [];
        foreach ($custom_fields_decoded as $field) {
            if (!empty($field['label']) && !empty($field['type'])) {
                $sanitized_fields[] = [
                    'type' => $field['type'],
                    'label' => trim($field['label']),
                    'options' => $field['options'] ?? '',
                    'required' => !empty($field['required']),
                    'enabled' => !empty($field['enabled']),
                ];
            }
        }
        $custom_fields_json = json_encode($sanitized_fields);
    }

    // --- Handle Stock Logic ---
    $total_stock = (int)($data['total_stock'] ?? 0);
    $current_stock = (int)($data['current_stock'] ?? 0);

    if ($id) {
        // Editing an existing link
        $old_settings = cplg_get_link_settings($id);
        $old_total_stock = (int)$old_settings['total_stock'];
        
        if ($total_stock != $old_total_stock) {
            // Total stock has changed
            if ($total_stock > $old_total_stock) {
                // Stock was increased
                $difference = $total_stock - $old_total_stock;
                $current_stock = (int)$old_settings['current_stock'] + $difference;
            } else {
                // Stock was decreased
                $current_stock = min($total_stock, (int)$old_settings['current_stock']);
            }
        }
        // If total_stock is 0 (unlimited), current_stock should also be 0
        if ($total_stock <= 0) {
            $current_stock = 0;
        }

    } else {
        // Creating a new link
        $current_stock = $total_stock;
    }


    $settings = [
        'link_slug' => $slug,
        'link_enabled' => isset($data['link_enabled']) ? 'true' : 'false',
        'link_title' => escape_string($data['link_title']),
        'link_description' => escape_string($data['link_description']),
        'use_system_currency' => $use_system_currency,
        'link_currency' => $link_currency,
        'show_name' => isset($data['show_name']) ? 'true' : 'false',
        'show_contact' => isset($data['show_contact']) ? 'true' : 'false',
        'logo_url' => filter_var($data['logo_url'], FILTER_SANITIZE_URL),
        'favicon_url' => filter_var($data['favicon_url'], FILTER_SANITIZE_URL),
        'show_footer_text' => isset($data['show_footer_text']) ? 'true' : 'false',
        'footer_text' => escape_string($data['footer_text']),
        'pretty_link_enabled' => isset($data['pretty_link_enabled']) ? 'true' : 'false',
        'show_instruction' => isset($data['show_instruction']) ? 'true' : 'false',
        'instruction_text' => escape_string($data['instruction_text']),
        'is_default' => isset($data['is_default']) ? (int)$data['is_default'] : 0,
        
        'amount_mode' => in_array($data['amount_mode'], ['custom', 'fixed']) ? $data['amount_mode'] : 'custom',
        'fixed_amount' => (float)($data['fixed_amount'] ?? 0.0),
        'min_amount' => (float)($data['min_amount'] ?? 0.0),
        'max_amount' => (float)($data['max_amount'] ?? 0.0),
        'suggested_amounts' => escape_string($data['suggested_amounts'] ?? ''),
        'custom_fields' => $custom_fields_json,
        'redirect_url' => filter_var($data['redirect_url'] ?? '', FILTER_SANITIZE_URL),
        
        // --- Add new fields to array ---
        'allow_quantity' => isset($data['allow_quantity']) ? 'true' : 'false',
        'total_stock' => $total_stock,
        'current_stock' => $current_stock,
    ];


    if ($id) {
        $sql = "UPDATE `{$table_name}` SET 
                    link_slug = ?, link_enabled = ?, link_title = ?, link_description = ?, 
                    use_system_currency = ?, link_currency = ?, show_name = ?, show_contact = ?, 
                    logo_url = ?, favicon_url = ?, show_footer_text = ?, footer_text = ?, 
                    pretty_link_enabled = ?, show_instruction = ?, instruction_text = ?,
                    amount_mode = ?, fixed_amount = ?, min_amount = ?, max_amount = ?,
                    suggested_amounts = ?, custom_fields = ?, redirect_url = ?,
                    allow_quantity = ?, total_stock = ?, current_stock = ?
                WHERE `id` = ?";
        
        $stmt = $conn->prepare($sql);
        
        $stmt->bind_param("ssssssssssssssssdddssssiii", 
            $settings['link_slug'], $settings['link_enabled'], $settings['link_title'], $settings['link_description'],
            $settings['use_system_currency'], $settings['link_currency'], $settings['show_name'], $settings['show_contact'],
            $settings['logo_url'], $settings['favicon_url'], $settings['show_footer_text'], $settings['footer_text'],
            $settings['pretty_link_enabled'], $settings['show_instruction'], $settings['instruction_text'],
            $settings['amount_mode'], $settings['fixed_amount'], $settings['min_amount'], $settings['max_amount'],
            $settings['suggested_amounts'], $settings['custom_fields'], $settings['redirect_url'],
            $settings['allow_quantity'], $settings['total_stock'], $settings['current_stock'],
            $id
        );
    } else {
        $sql = "INSERT INTO `{$table_name}` 
                    (link_slug, link_enabled, link_title, link_description, use_system_currency, link_currency, 
                     show_name, show_contact, logo_url, favicon_url, show_footer_text, footer_text, 
                     pretty_link_enabled, show_instruction, instruction_text, is_default,
                     amount_mode, fixed_amount, min_amount, max_amount, suggested_amounts, custom_fields, redirect_url,
                     allow_quantity, total_stock, current_stock) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        $stmt->bind_param("sssssssssssssssisdddssssii",
            $settings['link_slug'], $settings['link_enabled'], $settings['link_title'], $settings['link_description'],
            $settings['use_system_currency'], $settings['link_currency'], $settings['show_name'], $settings['show_contact'],
            $settings['logo_url'], $settings['favicon_url'], $settings['show_footer_text'], $settings['footer_text'],
            $settings['pretty_link_enabled'], $settings['show_instruction'], $settings['instruction_text'], $settings['is_default'],
            $settings['amount_mode'], $settings['fixed_amount'], $settings['min_amount'], $settings['max_amount'],
            $settings['suggested_amounts'], $settings['custom_fields'], $settings['redirect_url'],
            $settings['allow_quantity'], $settings['total_stock'], $settings['current_stock']
        );
    }
    
    $success = $stmt->execute();
    if (!$success) {
         error_log("CPLG SQL Error: " . $stmt->error);
    }
    cplg_update_htaccess();
    return $success;
}

/**
 * Delete a link by ID.
 */
function cplg_delete_link($id) {
    $conn = cplg_get_db();
    $table_name = cplg_get_table_name();

    $link = cplg_get_link_settings($id);
    if ($link && $link['is_default'] == 1) {
        return false;
    }

    $stmt = $conn->prepare("DELETE FROM `{$table_name}` WHERE `id` = ? AND `is_default` = 0");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    
    cplg_update_htaccess();

    return $success;
}

/**
 * Update the .htaccess file with all active pretty links.
 */
function cplg_update_htaccess() {
    $all_links = cplg_get_all_links();
    $active_links = [];
    foreach ($all_links as $link) {
        if ($link['pretty_link_enabled'] === 'true' && !empty($link['link_slug'])) {
            $active_links[] = $link;
        }
    }

    $marker_start = "# BEGIN CustomizablePaymentLink";
    $marker_end   = "# END CustomizablePaymentLink";
    $htaccess_path = __DIR__ . '/../../../../.htaccess';

    $htaccess_message = '';

    if (file_exists($htaccess_path)) {
        if (is_writable($htaccess_path)) {
            $backup_path = __DIR__ . '/../../../../.htaccess.cplg-backup';
            if (!@copy($htaccess_path, $backup_path)) {
                $htaccess_message = ' Could not create .htaccess backup.';
            }

            $htaccess_content = file_get_contents($htaccess_path);
            
            $pattern = "/\n?$marker_start\n.*?\n$marker_end\n?/s";
            $htaccess_content = preg_replace($pattern, "\n", $htaccess_content);
            $htaccess_content = trim($htaccess_content);

            if (!empty($active_links)) {
                $plugin_path_base = 'pp-content/plugins/modules/customizable-payment-link-generator';
                $new_rules = [$marker_start, "RewriteEngine On"];

                foreach ($active_links as $link) {
                    $slug = trim($link['link_slug'], '/');
                    
                    // Rule for success: /slug/success/pp_id
                    $new_rules[] = "RewriteRule ^{$slug}/success/([a-zA-Z0-9_]+)/?$ {$plugin_path_base}/success.php?slug={$slug}&pp_id=$1 [L,QSA]";
                    // Rule for cancel: /slug/cancel/pp_id
                    $new_rules[] = "RewriteRule ^{$slug}/cancel/([a-zA-Z0-9_]+)/?$ {$plugin_path_base}/cancel.php?slug={$slug}&pp_id=$1 [L,QSA]";
                    // Rule for pending: /slug/pending/pp_id
                    $new_rules[] = "RewriteRule ^{$slug}/pending/([a-zA-Z0-9_]+)/?$ {$plugin_path_base}/pending.php?slug={$slug}&pp_id=$1 [L,QSA]";
                    
                    // Rule for the main link page (MUST BE LAST)
                    $new_rules[] = "RewriteRule ^{$slug}/?$ {$plugin_path_base}/link.php?slug={$slug} [L,QSA]";
                }
                
                $new_rules[] = $marker_end;

                $htaccess_content .= "\n\n" . implode("\n", $new_rules) . "\n";
            }

            if (@file_put_contents($htaccess_path, $htaccess_content) !== false) {
                $htaccess_message = ' .htaccess file updated successfully.';
            } else {
                $htaccess_message = ' Failed to write to .htaccess. Please update it manually.';
            }

        } else {
            $htaccess_message = ' .htaccess file is not writable. Please update it manually.';
        }
    } else {
         $htaccess_message = ' .htaccess file not found. Please create it or add rules manually.';
    }
    
    return $htaccess_message;
}

/**
 * Get aggregated reports for all links, with optional date range.
 *
 * @param string $start_date The start date (Y-m-d).
 * @param string $end_date The end date (Y-m-d).
 * @return array
 */
function cplg_get_link_reports($start_date = '', $end_date = '') {
    global $conn, $db_prefix;
    cplg_get_db();

    $params = [];
    $types = "";
    
    // Base SQL
    $sql = "
        SELECT
            links.link_title AS title,
            SUM(trx.transaction_amount) AS revenue,
            COUNT(trx.id) AS count,
            AVG(trx.transaction_amount) AS avg,
            SUM(JSON_EXTRACT(trx.transaction_metadata, '$.cplg_quantity')) AS items_sold
        FROM `{$db_prefix}transaction` AS trx
        JOIN `{$db_prefix}cplg_links` AS links
            ON JSON_EXTRACT(trx.transaction_metadata, '$.cplg_link_id') = links.id
        WHERE
            trx.transaction_status = 'completed'
    ";

    // Add date range conditions
    if (!empty($start_date)) {
        $sql .= " AND trx.created_at >= ?";
        array_push($params, $start_date . ' 00:00:00');
        $types .= "s";
    }
    if (!empty($end_date)) {
        $sql .= " AND trx.created_at <= ?";
        array_push($params, $end_date . ' 23:59:59');
        $types .= "s";
    }

    // Grouping and Ordering
    $sql .= "
        GROUP BY
            links.id, links.link_title
        ORDER BY
            revenue DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("CPLG SQL Prepare Error: " . $conn->error);
        return [];
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    return [];
}


// --- AJAX Handlers ---

if (isset($_POST['customizable-payment-link-generator-action'])) {
    header('Content-Type: application/json');
    $action = $_POST['customizable-payment-link-generator-action'];

    if ($action === 'save_link') {
        $link_id = isset($_POST['link_id']) ? (int)$_POST['link_id'] : null;
        
        if (cplg_save_link($_POST, $link_id)) {
            $htaccess_message = cplg_update_htaccess();
            echo json_encode(['status' => true, 'message' => 'Link settings saved successfully.' . $htaccess_message]);
        } else {
             echo json_encode(['status' => false, 'message' => 'Failed to save link settings.']);
        }
        exit();
    }
    
    if ($action === 'delete_link') {
        $link_id = isset($_POST['link_id']) ? (int)$_POST['link_id'] : 0;
        if ($link_id > 0) {
            if (cplg_delete_link($link_id)) {
                $htaccess_message = cplg_update_htaccess();
                echo json_encode(['status' => true, 'message' => 'Link deleted successfully.' . $htaccess_message]);
            } else {
                echo json_encode(['status' => false, 'message' => 'Failed to delete link. You cannot delete the default link.']);
            }
        } else {
            echo json_encode(['status' => false, 'message' => 'Invalid link ID.']);
        }
        exit();
    }
    
    if ($action === 'bulk_delete_transactions') {
        $pp_ids = $_POST['pp_ids'] ?? [];
        if (!is_array($pp_ids) || empty($pp_ids)) {
            echo json_encode(['status' => false, 'message' => 'No transactions selected.']);
            exit();
        }

        if (cplg_delete_transactions_bulk($pp_ids)) {
            echo json_encode(['status' => true, 'message' => 'Selected transactions deleted successfully.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to delete selected transactions.']);
        }
        exit();
    }

    // --- MODIFIED: Check both update sources ---
    if ($action === 'check_for_updates') {
        $github_update = cplg_check_for_github_updates();
        $refat_update = cplg_check_for_refat_server_updates();
        
        $github_available = $github_update && version_compare($github_update['new_version'], CPLG_CURRENT_VERSION, '>');
        $refat_available = $refat_update && version_compare($refat_update['new_version'], CPLG_CURRENT_VERSION, '>');

        echo json_encode([
            'status' => true,
            'github' => [
                'update_available' => $github_available,
                'data' => $github_update
            ],
            'refat' => [
                'update_available' => $refat_available,
                'data' => $refat_update
            ],
            'message' => (!$github_available && !$refat_available) ? 'You are using the latest version (' . CPLG_CURRENT_VERSION . ').' : 'Update check complete.'
        ]);
        exit();
    }

    // --- NEW: Install update (with backup) ---
    if ($action === 'install_update') {
        $download_url = $_POST['download_url'] ?? null;

        if (empty($download_url) || !filter_var($download_url, FILTER_VALIDATE_URL)) {
            echo json_encode(['status' => false, 'message' => 'Invalid download URL.']);
            exit();
        }

        // 1. Create backup
        $backup_path = cplg_create_backup();
        if ($backup_path === false) {
            echo json_encode(['status' => false, 'message' => 'Failed to create backup. Update aborted. Please check file permissions for the backups directory.']);
            exit();
        }

        // 2. Download and install update
        $install_result = cplg_install_update_from_zip($download_url);

        if ($install_result === true) {
            // 3. Success: Delete backup
            @unlink($backup_path);
            echo json_encode(['status' => true, 'message' => 'Update installed successfully.']);
        } else {
            // 4. Failure: Restore from backup
            cplg_restore_from_backup($backup_path);
            echo json_encode(['status' => false, 'message' => 'Update failed: ' . $install_result . ' Restored from backup.']);
        }
        exit();
    }
}


/**
 * Get all transactions generated by this plugin, with pagination AND FILTERS.
 */
function cplg_get_all_transactions($search_term = '', $status = '', $start_date = '', $end_date = '', $limit = 20, $offset = 0) {
    global $conn, $db_prefix;
    cplg_get_db();

    $params = [];
    $types = "";
    
    // Base SQL
    $sql = "SELECT `pp_id`, `c_name`, `transaction_amount`, `transaction_currency`, `transaction_status`, 
                   `created_at`, `payment_verify_id`, `payment_sender_number`
            FROM `{$db_prefix}transaction`
            WHERE `transaction_metadata` LIKE '%\"cplg_link_id\"%'";

    if (!empty($search_term)) {
        $sql .= " AND (`pp_id` LIKE ? OR `c_name` LIKE ? OR `payment_verify_id` LIKE ? OR `payment_sender_number` LIKE ?)";
        $search_like = "%{$search_term}%";
        array_push($params, $search_like, $search_like, $search_like, $search_like);
        $types .= "ssss";
    }

    if (!empty($status)) {
        $sql .= " AND `transaction_status` = ?";
        array_push($params, $status);
        $types .= "s";
    }

    if (!empty($start_date)) {
        $sql .= " AND `created_at` >= ?";
        array_push($params, $start_date . ' 00:00:00');
        $types .= "s";
    }

    if (!empty($end_date)) {
        $sql .= " AND `created_at` <= ?";
        array_push($params, $end_date . ' 23:59:59');
        $types .= "s";
    }

    $sql .= " ORDER BY `created_at` DESC LIMIT ? OFFSET ?";
    array_push($params, $limit, $offset);
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    return [];
}

/**
 * Get the total count of transactions for pagination, WITH FILTERS.
 */
function cplg_get_transaction_count($search_term = '', $status = '', $start_date = '', $end_date = '') {
    global $conn, $db_prefix;
    cplg_get_db();

    $params = [];
    $types = "";
    
    $sql = "SELECT COUNT(`id`) as total 
            FROM `{$db_prefix}transaction` 
            WHERE `transaction_metadata` LIKE '%\"cplg_link_id\"%'";

    if (!empty($search_term)) {
        $sql .= " AND (`pp_id` LIKE ? OR `c_name` LIKE ? OR `payment_verify_id` LIKE ? OR `payment_sender_number` LIKE ?)";
        $search_like = "%{$search_term}%";
        array_push($params, $search_like, $search_like, $search_like, $search_like);
        $types .= "ssss";
    }

    if (!empty($status)) {
        $sql .= " AND `transaction_status` = ?";
        array_push($params, $status);
        $types .= "s";
    }

    if (!empty($start_date)) {
        $sql .= " AND `created_at` >= ?";
        array_push($params, $start_date . ' 00:00:00');
        $types .= "s";
    }

    if (!empty($end_date)) {
        $sql .= " AND `created_at` <= ?";
        array_push($params, $end_date . ' 23:59:59');
        $types .= "s";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return 0;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (int)$row['total'];
    }
    
    return 0;
}


/**
 * Get a single transaction by its pp_id and verify it belongs to this plugin.
 */
function cplg_get_transaction_by_pp_id($pp_id) {
    global $conn, $db_prefix;
    cplg_get_db();

    $sql = "SELECT * FROM `{$db_prefix}transaction`
            WHERE `pp_id` = ? AND `transaction_metadata` LIKE '%\"cplg_link_id\"%'
            LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return null;
    }
    
    $stmt->bind_param("s", $pp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Delete a list of transactions by their pp_id.
 *
 * @param array $pp_ids An array of pp_id strings.
 * @return bool True on success, false on failure.
 */
function cplg_delete_transactions_bulk($pp_ids) {
    global $conn, $db_prefix;
    cplg_get_db();

    if (!is_array($pp_ids) || empty($pp_ids)) {
        return false;
    }

    // Sanitize all pp_ids. We assume they are strings.
    $sanitized_ids = array_map('strval', $pp_ids);
    
    // Create placeholders for the IN clause
    $placeholders = implode(',', array_fill(0, count($sanitized_ids), '?'));
    $types = str_repeat('s', count($sanitized_ids));

    $sql = "DELETE FROM `{$db_prefix}transaction`
            WHERE `pp_id` IN ({$placeholders})
            AND `transaction_metadata` LIKE '%\"cplg_link_id\"%'";
            
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("CPLG Bulk Delete SQL Prepare Error: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param($types, ...$sanitized_ids);
    
    return $stmt->execute();
}


/**
 * This should be called by IPN file when a payment is CONFIRMED.
 *
 * @param int $link_id The ID of the link.
 * @param int $quantity The quantity purchased.
 */
function cplg_decrement_stock($link_id, $quantity) {
    $conn = cplg_get_db();
    $table_name = cplg_get_table_name();

    $link_id = (int)$link_id;
    $quantity = (int)$quantity;

    if ($link_id <= 0 || $quantity <= 0) {
        return false;
    }

    // Get current stock to make sure we don't go below zero
    $link = cplg_get_link_settings($link_id);
    if ($link && (int)$link['total_stock'] > 0) {
        
        $sql = "UPDATE `{$table_name}` 
                SET `current_stock` = GREATEST(0, `current_stock` - ?) 
                WHERE `id` = ? AND `total_stock` > 0";
                
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("CPLG Stock Decrement SQL Prepare Error: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("ii", $quantity, $link_id);
        return $stmt->execute();
    }
    
    return false; // Not a stocked item or link not found
}


/**
 * Checks a single transaction, decrements stock if needed, and flags it as processed.
 * This is safe to run multiple times (idempotent).
 *
 * @param string $pp_id The transaction ID.
 * @return bool True if stock was processed or was already processed, false on failure.
 */
function cplg_sync_stock_for_transaction($pp_id) {
    global $conn, $db_prefix;
    cplg_get_db();

    // 1. Get the transaction data
    $trx = cplg_get_transaction_by_pp_id($pp_id);

    // 2. Validate the transaction
    if (!$trx || $trx['transaction_status'] !== 'completed') {
        return false; // Not completed or not a CPLG transaction
    }

    $metadata = json_decode($trx['transaction_metadata'], true);
    if (!is_array($metadata)) $metadata = [];

    // 3. Check if stock is already deducted
    if (isset($metadata['cplg_stock_deducted']) && $metadata['cplg_stock_deducted'] === true) {
        return true; // Success: Already processed
    }

    $link_id = $metadata['cplg_link_id'] ?? null;
    $quantity = $metadata['cplg_quantity'] ?? 0;

    // 4. Check if this is a stocked item
    if ($link_id && $quantity > 0) {
        
        // 5. This is a stocked item that needs processing. Decrement the stock.
        $stock_decremented = cplg_decrement_stock($link_id, $quantity);
        
        if ($stock_decremented) {
            // 6. Update the transaction's metadata to prevent double-dipping
            $metadata['cplg_stock_deducted'] = true;
            $new_metadata_json = json_encode($metadata);
            
            $stmt = $conn->prepare("UPDATE `{$db_prefix}transaction` SET `transaction_metadata` = ? WHERE `pp_id` = ?");
            if ($stmt) {
                $stmt->bind_param("ss", $new_metadata_json, $pp_id);
                $stmt->execute();
                return true; // Success: Stock processed and flagged
            }
        }
    } else {
        // Not a stocked item, but mark as "processed" to avoid re-checking
         $metadata['cplg_stock_deducted'] = true; // Not a stocked item, but we've "processed" it
         $new_metadata_json = json_encode($metadata);
         $stmt = $conn->prepare("UPDATE `{$db_prefix}transaction` SET `transaction_metadata` = ? WHERE `pp_id` = ?");
         if ($stmt) {
             $stmt->bind_param("ss", $new_metadata_json, $pp_id);
             $stmt->execute();
             return true; 
         }
    }
    
    return false;
}

/**
 * Scans for any completed CPLG transactions that haven't had stock deducted
 * and processes them. This is designed to catch manual admin approvals.
 */
function cplg_sync_all_unprocessed_stock() {
    global $conn, $db_prefix;
    cplg_get_db();

    // Find CPLG transactions that are 'completed' but do NOT have the 'cplg_stock_deducted:true' flag
    $sql = "SELECT `pp_id` FROM `{$db_prefix}transaction`
            WHERE `transaction_status` = 'completed'
            AND `transaction_metadata` LIKE '%\"cplg_link_id\"%'
            AND (`transaction_metadata` NOT LIKE '%\"cplg_stock_deducted\":true%' OR `transaction_metadata` IS NULL)";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Call the sync function for each one found
            cplg_sync_stock_for_transaction($row['pp_id']);
        }
    }
}


// --- UPDATE FUNCTIONS ---

/**
 * Get the path to the plugin directory.
 */
function cplg_get_plugin_dir() {
    return __DIR__;
}

/**
 * Get the path to the backups directory.
 * (e.g., /.../pp-content/backups/cplg/)
 */
function cplg_get_backup_dir() {
    // Go up 3 levels from /pp-content/plugins/modules/customizable-payment-link-generator/
    $backup_dir = dirname(__DIR__, 3) . '/backups/cplg/';
    if (!is_dir($backup_dir)) {
        @mkdir($backup_dir, 0755, true);
    }
    return $backup_dir;
}

/**
 * Deletes a directory and all its contents.
 */
function cplg_delete_directory($dir) {
    if (!file_exists($dir)) { return true; }
    if (!is_dir($dir)) { return unlink($dir); }
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') { continue; }
        if (!cplg_delete_directory($dir . DIRECTORY_SEPARATOR . $item)) { return false; }
    }
    return rmdir($dir);
}

/**
 * Create a zip backup of the current plugin directory.
 */
function cplg_create_backup() {
    if (!class_exists('ZipArchive')) {
        return false;
    }
    
    $plugin_dir = cplg_get_plugin_dir();
    $backup_dir = cplg_get_backup_dir();
    
    if (!is_writable($backup_dir)) {
        error_log("CPLG Backup Error: Directory not writable: " . $backup_dir);
        return false;
    }

    $backup_file = $backup_dir . CPLG_PLUGIN_SLUG . '-backup-' . time() . '.zip';
    
    $zip = new ZipArchive();
    if ($zip->open($backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        return false;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($plugin_dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($plugin_dir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }

    $zip->close();
    return $backup_file;
}

/**
 * Restore the plugin from a backup zip.
 */
function cplg_restore_from_backup($backup_path) {
    if (!class_exists('ZipArchive') || !file_exists($backup_path)) {
        return false;
    }

    $plugin_dir = cplg_get_plugin_dir();
    
    // 1. Clear the current plugin directory
    cplg_delete_directory($plugin_dir);
    @mkdir($plugin_dir, 0755, true); // Recreate the empty dir

    // 2. Extract backup
    $zip = new ZipArchive();
    if ($zip->open($backup_path) === TRUE) {
        $zip->extractTo($plugin_dir);
        $zip->close();
        return true;
    }
    return false;
}

/**
 * Download and install the update from a zip URL.
 */
function cplg_install_update_from_zip($download_url) {
    if (!class_exists('ZipArchive')) {
        return "ZipArchive class is not available.";
    }

    $plugin_dir = cplg_get_plugin_dir();
    $temp_zip = cplg_get_backup_dir() . 'cplg-update.zip';

    // 1. Download the zip file
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $download_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PipraPay Plugin Update Checker');
    $zip_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200 || $zip_data === false) {
        return "Failed to download update file (HTTP code: {$http_code}).";
    }

    if (file_put_contents($temp_zip, $zip_data) === false) {
        return "Failed to save temporary update file.";
    }

    // 2. Clear the plugin directory (except for the backup zip itself if it's in a subfolder)
    // Backup to a *different* directory, so this is safe.
    cplg_delete_directory($plugin_dir);
    @mkdir($plugin_dir, 0755, true);

    // 3. Extract the new version
    $zip = new ZipArchive();
    if ($zip->open($temp_zip) === TRUE) {
        // Check if the zip has a root folder (e.g., "my-plugin-v1.2/")
        $root_dir = $zip->getNameIndex(0);
        
        if (substr_count($root_dir, '/') == 1 && substr($root_dir, -1) == '/') {
            // Contains a root folder, extract to a temp location and move files
            $temp_extract_dir = cplg_get_backup_dir() . 'cplg-extract/';
            cplg_delete_directory($temp_extract_dir); // Clear old
            
            $zip->extractTo($temp_extract_dir);
            $zip->close();
            
            // Move files from /.../cplg-extract/plugin-root-folder/ to /.../plugin_dir/
            $files = scandir($temp_extract_dir . $root_dir);
            foreach ($files as $file) {
                if ($file == '.' || $file == '..') continue;
                @rename($temp_extract_dir . $root_dir . $file, $plugin_dir . '/' . $file);
            }
            cplg_delete_directory($temp_extract_dir);
            
        } else {
            // No root folder, extract directly
            $zip->extractTo($plugin_dir);
            $zip->close();
        }
        
        @unlink($temp_zip); // Delete the temp update zip
        return true;
    } else {
        @unlink($temp_zip);
        return "Failed to open the downloaded zip file.";
    }
}


/**
 * Check GitHub for plugin updates.
 */
function cplg_check_for_github_updates() {
    $api_url = "https://api.github.com/repos/" . CPLG_GITHUB_REPO . "/releases/latest";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'PipraPay Plugin Update Checker'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $release_data = json_decode($response, true);

        if (isset($release_data['tag_name'])) {
            $latest_version = ltrim($release_data['tag_name'], 'v');
            $download_url = '';

            if (!empty($release_data['assets'])) {
                foreach ($release_data['assets'] as $asset) {
                    if (strpos($asset['name'], '.zip') !== false) {
                        $download_url = $asset['browser_download_url'];
                        break;
                    }
                }
            }
            
            // Fallback if no zip asset, use the auto-generated one
            if (empty($download_url) && isset($release_data['zipball_url'])) {
                 $download_url = $release_data['zipball_url'];
            }

            $html_changelog = $release_data['body'];
            
            // Convert ### headers to <h5>
            $html_changelog = preg_replace('/^### (.*)$/m', '<h5>$1</h5>', $html_changelog);
            
            // Convert **bold** to <strong>
            $html_changelog = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html_changelog);
            
            // Convert list items (e.g., '- item' or '* item') to <li>
            $html_changelog = preg_replace('/^[-*]\s+(.*)$/m', '<li>$1</li>', $html_changelog);
            
            // Convert remaining newlines to <br>
            $html_changelog = nl2br($html_changelog);
            
            // Clean up extra <br> tags that might appear inside the list items
            $html_changelog = str_replace(['<li><br />', '<br />
			</li>', '<h5><br />'], ['<li>', '</li>', '<h5>'], $html_changelog);

            return [
                'new_version' => $latest_version,
                'download_url' => $download_url,
                'changelog' => $html_changelog
            ];
        }
    }

    return null;
}

/**
 * Check Refat's Server for plugin updates.
 */
function cplg_check_for_refat_server_updates() {
    $api_url = CPLG_REFAT_SERVER_URL;
    $payload = [
        'action'  => 'update_check',
        'request' => json_encode([
            'slug'    => CPLG_PLUGIN_SLUG,
            'version' => CPLG_CURRENT_VERSION
        ])
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $result = curl_exec($ch);
    curl_close($ch);

    if ($result === false) {
        return null;
    }

    $response = json_decode($result);

    if ($response && isset($response->success) && $response->success && isset($response->data)) {
        $data = $response->data;
        $changelog = '';

        // --- FIX: Access as an object property (->) instead of an array ([]) ---
        if (isset($data->sections) && isset($data->sections->changelog)) {
            $changelog = $data->sections->changelog;
        }

        return [
            'new_version' => $data->new_version,
            'download_url' => $data->package,
            'changelog' => $changelog
        ];
    }
    
    return null;
}

?>