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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Canceled</title>
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
        .btn-secondary { background-color: #6c757d; border-color: #6c757d; color: #fff; font-weight: 600; padding: 0.75rem; border-radius: 0.375rem; transition: background-color 0.2s ease; margin-top: 2rem; }
        .btn-secondary:hover { background-color: #5a6268; border-color: #545b62; }
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
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/>
                </svg>
            </div>
            <h4 class="card-title">Payment Canceled</h4>
            <p class="card-text">Your payment was not completed. It was either canceled or failed. Please try again.</p>
            <a href="<?php echo $payment_link; ?>" class="btn btn-secondary w-100">Try Again</a>
        </div>
    </div>
</body>
</html>