<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

$plugin_meta = [
    'Plugin Name'       => 'Customizable Payment Link Generator',
    'Description'       => 'A PipraPay module to generate a customizable payment link where users can enter a custom amount and other details to pay.',
    'Version'           => '1.0.2',
    'Author'            => 'Refat Rahman',
    'Author URI'        => 'https://github.com/refatbd',
    'License'           => 'GPL-2.0+',
    'License URI'       => 'http://www.gnu.org/licenses/gpl-2.0.txt',
    'Requires at least' => '1.0.0',
    'Plugin URI'        => '',
    'Text Domain'       => 'customizable-payment-link-generator',
    'Domain Path'       => '',
    'Requires PHP'      => '7.4'
];


$funcFile = __DIR__ . '/functions.php';
if (file_exists($funcFile)) {
    require_once $funcFile;
}

// Load the admin UI rendering function
function customizable_payment_link_generator_admin_page() {
    $viewFile = __DIR__ . '/views/admin-ui.php';

    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo "<div class='alert alert-warning'>Admin UI not found.</div>";
    }
}