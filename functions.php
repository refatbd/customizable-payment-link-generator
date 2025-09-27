<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

function cplg_get_settings() {
    $plugin_slug = 'customizable-payment-link-generator';
    $settings = pp_get_plugin_setting($plugin_slug);
    if (!is_array($settings)) {
        $settings = [];
    }

    $defaults = [
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
        'pretty_link_enabled' => 'false',
        'pretty_link_slug' => 'custom-payment/custom-invoice',
        'show_instruction' => 'true',
        'instruction_text' => "To Complete Your Payment:\n\nEnter your due amount, full name, and either your email address or mobile number.\nClick the \"Pay\" button.\nChoose your preferred payment method.\nComplete the payment process.",
    ];

    return array_merge($defaults, $settings);
}

function cplg_get_all_currencies() {
    global $conn, $db_host, $db_user, $db_pass, $db_name, $db_prefix;
    if (!isset($conn) || $conn->connect_error) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($conn->connect_error) {
            return [];
        }
    }

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


if (isset($_POST['customizable-payment-link-generator-action'])) {
    header('Content-Type: application/json');
    $plugin_slug = 'customizable-payment-link-generator';
    $action = $_POST['customizable-payment-link-generator-action'];

    if ($action === 'save_settings') {
        $settings = pp_get_plugin_setting($plugin_slug);
        if (!is_array($settings)) $settings = [];

        $use_system_currency = isset($_POST['use_system_currency']) ? 'true' : 'false';
        $link_currency = $use_system_currency === 'true' ? pp_get_settings()['default_currency'] : escape_string($_POST['link_currency']);

        $new_settings = [
            'link_enabled' => isset($_POST['link_enabled']) ? 'true' : 'false',
            'link_title' => escape_string($_POST['link_title']),
            'link_description' => escape_string($_POST['link_description']),
            'use_system_currency' => $use_system_currency,
            'link_currency' => $link_currency,
            'show_name' => isset($_POST['show_name']) ? 'true' : 'false',
            'show_contact' => isset($_POST['show_contact']) ? 'true' : 'false',
            'logo_url' => filter_var($_POST['logo_url'], FILTER_SANITIZE_URL),
            'favicon_url' => filter_var($_POST['favicon_url'], FILTER_SANITIZE_URL),
            'show_footer_text' => isset($_POST['show_footer_text']) ? 'true' : 'false',
            'footer_text' => escape_string($_POST['footer_text']),
            'pretty_link_enabled' => isset($_POST['pretty_link_enabled']) ? 'true' : 'false',
            'pretty_link_slug' => escape_string($_POST['pretty_link_slug']),
            'show_instruction' => isset($_POST['show_instruction']) ? 'true' : 'false',
            'instruction_text' => escape_string($_POST['instruction_text']),
        ];

        $settings_to_save = array_merge($settings, $new_settings);

        if(cplg_save_settings($plugin_slug, $settings_to_save)) {
             echo json_encode(['status' => true, 'message' => 'Settings saved successfully.']);
        } else {
             echo json_encode(['status' => false, 'message' => 'Failed to save settings.']);
        }
        exit();
    }

    if ($action === 'check_for_updates') {
        $update_info = cplg_check_for_github_updates();
        if ($update_info) {
            echo json_encode([
                'status' => true, 
                'update_available' => true, 
                'data' => $update_info
            ]);
        } else {
            echo json_encode([
                'status' => true, 
                'update_available' => false, 
                'message' => 'You are using the latest version of the plugin.'
            ]);
        }
        exit();
    }
}

// Renamed function to avoid conflicts
function cplg_save_settings(string $plugin_slug, array $data_to_save) {
    $targetUrl = pp_get_site_url().'/admin/dashboard';
    $data = array_merge(['action' => 'plugin_update-submit', 'plugin_slug' => $plugin_slug], $data_to_save);

    $ch = curl_init($targetUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return in_array($http_code, [200, 302]);
}

// --- Update Checker ---

function cplg_check_for_github_updates() {
    $current_version = '1.0.2'; 
    $github_repo = 'refatbd/customizable-payment-link-generator';

    $api_url = "https://api.github.com/repos/{$github_repo}/releases/latest";

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

            if (version_compare($latest_version, $current_version, '>')) {
                $download_url = '';
                if (!empty($release_data['assets'])) {
                    foreach ($release_data['assets'] as $asset) {
                        if (strpos($asset['name'], '.zip') !== false) {
                            $download_url = $asset['browser_download_url'];
                            break;
                        }
                    }
                }
                
                return [
                    'new_version' => $latest_version,
                    'download_url' => $download_url,
                    'changelog' => $release_data['body']
                ];
            }
        }
    }

    return null;
}