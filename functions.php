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
        'show_name' => 'true',
        'show_contact' => 'true',
        'logo_url' => '',
        'show_footer_text' => 'true',
        'footer_text' => 'Payments are secure and encrypted with PipraPay',
        'pretty_link_enabled' => 'false',
        'pretty_link_slug' => 'custom-payment/custom-invoice',
        'show_instruction' => 'true',
        'instruction_text' => "To Complete Your Payment:\n\nEnter your due amount, full name, and either your email address or mobile number.\nClick the \"Pay\" button.\nChoose your preferred payment method.\nComplete the payment process.",
    ];

    return array_merge($defaults, $settings);
}


if (isset($_POST['customizable-payment-link-generator-action'])) {
    header('Content-Type: application/json');
    $plugin_slug = 'customizable-payment-link-generator';
    $action = $_POST['customizable-payment-link-generator-action'];

    if ($action === 'save_settings') {
        $settings = pp_get_plugin_setting($plugin_slug);
        if (!is_array($settings)) $settings = [];

        $new_settings = [
            'link_enabled' => isset($_POST['link_enabled']) ? 'true' : 'false',
            'link_title' => escape_string($_POST['link_title']),
            'link_description' => escape_string($_POST['link_description']),
            'link_currency' => escape_string($_POST['link_currency']),
            'show_name' => isset($_POST['show_name']) ? 'true' : 'false',
            'show_contact' => isset($_POST['show_contact']) ? 'true' : 'false',
            'logo_url' => filter_var($_POST['logo_url'], FILTER_SANITIZE_URL),
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
}


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