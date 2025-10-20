<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

$settings = cplg_get_settings();
$currencies = cplg_get_all_currencies();
global $global_setting_response; // Bring the global settings into scope.
$system_currency = isset($global_setting_response['response'][0]['default_currency']) ? $global_setting_response['response'][0]['default_currency'] : 'USD';

?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"

<div class="page-header">
  <h1 class="page-header-title">Customizable Payment Link Generator</h1>
</div>

<div class="row">
    <div class="col-lg-8">
        <div id="ajaxResponse" class="mb-3"></div>
        
        <div class="card mb-3">
            <div class="card-header"><h4 class="card-title">Customizable Link URL</h4></div>
            <div class="card-body">
                <p>Your customizable payment link is:</p>
                <div class="input-group">
                    <?php
                    $payment_link = pp_get_site_url() . '/pp-content/plugins/modules/customizable-payment-link-generator/link';
                    if ($settings['pretty_link_enabled'] === 'true' && !empty($settings['pretty_link_slug'])) {
                        $payment_link = pp_get_site_url() . '/' . trim($settings['pretty_link_slug'], '/');
                    }
                    ?>
                    <input type="text" id="paymentLinkUrl" class="form-control" value="<?php echo $payment_link; ?>" readonly>
                    <button class="btn btn-soft-primary" type="button" id="copyLinkBtn">Copy Link</button>
                </div>
            </div>
        </div>

        <form id="customizableLinkSettingsForm" method="post" action="">
            <input type="hidden" name="customizable-payment-link-generator-action" value="save_settings">

            <div class="card mb-3">
                <div class="card-header"><h4 class="card-title">Link Settings</h4></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="link_enabled" name="link_enabled" <?php echo $settings['link_enabled'] === 'true' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="link_enabled"><b>Enable Payment Link</b></label>
                    </div>

                    <div class="mb-3">
                        <label for="link_title" class="form-label">Link Title</label>
                        <input type="text" class="form-control" id="link_title" name="link_title" value="<?php echo htmlspecialchars($settings['link_title']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="link_description" class="form-label">Link Description</label>
                        <textarea class="form-control" id="link_description" name="link_description" rows="3"><?php echo htmlspecialchars($settings['link_description']); ?></textarea>
                    </div>
                     <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="use_system_currency" name="use_system_currency" <?php echo $settings['use_system_currency'] === 'true' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="use_system_currency"><b>Use System Currency (<?php echo $system_currency; ?>)</b></label>
                    </div>
                     <div class="mb-3">
                        <label for="link_currency" class="form-label">Currency</label>
                        <select class="form-select" id="link_currency" name="link_currency" <?php echo $settings['use_system_currency'] === 'true' ? 'disabled' : ''; ?>>
                            <?php
                            $selected_currency = $settings['link_currency'];
                            foreach ($currencies as $code => $name) {
                                $selected = ($code === $selected_currency) ? 'selected' : '';
                                echo "<option value='{$code}' {$selected}>{$name} - {$code}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h4 class="card-title">Appearance Settings</h4></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="logo_url" class="form-label">Logo Image URL</label>
                        <input type="url" class="form-control" id="logo_url" name="logo_url" value="<?php echo htmlspecialchars($settings['logo_url']); ?>" placeholder="https://example.com/logo.png">
                        <small class="form-text text-muted">Leave blank to not display a logo.</small>
                    </div>
                    <div class="mb-3">
                        <label for="favicon_url" class="form-label">Favicon URL</label>
                        <input type="url" class="form-control" id="favicon_url" name="favicon_url" value="<?php echo htmlspecialchars($settings['favicon_url']); ?>" placeholder="https://example.com/favicon.png">
                        <small class="form-text text-muted">Leave blank to use the default favicon.</small>
                    </div>
                    <hr>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="show_footer_text" name="show_footer_text" <?php echo $settings['show_footer_text'] === 'true' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="show_footer_text"><b>Show Secure Payment Footer Text</b></label>
                    </div>
                    <div class="mb-3">
                        <label for="footer_text" class="form-label">Footer Text</label>
                        <input type="text" class="form-control" id="footer_text" name="footer_text" value="<?php echo htmlspecialchars($settings['footer_text']); ?>">
                    </div>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header"><h4 class="card-title">Instruction Notice</h4></div>
                <div class="card-body">
                     <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="show_instruction" name="show_instruction" <?php echo $settings['show_instruction'] === 'true' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="show_instruction"><b>Show Instruction Notice</b></label>
                    </div>
                    <div class="mb-3">
                        <label for="instruction_text" class="form-label">Instruction Text</label>
                        <textarea class="form-control" id="instruction_text" name="instruction_text" rows="5"><?php echo htmlspecialchars(stripslashes(str_replace('\r\n', "\n", $settings['instruction_text']))); ?></textarea>
                        <small class="form-text text-muted">This text will appear above the payment form. Use line breaks.</small>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h4 class="card-title">Pretty Link Settings</h4></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="pretty_link_enabled" name="pretty_link_enabled" <?php echo $settings['pretty_link_enabled'] === 'true' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="pretty_link_enabled"><b>Enable Pretty Link</b></label>
                    </div>

                    <div id="prettyLinkOptions" style="<?php echo $settings['pretty_link_enabled'] === 'true' ? '' : 'display: none;'; ?>">
                        <div class="mb-3">
                            <label for="pretty_link_slug" class="form-label">Pretty Link Slug</label>
                            <div class="input-group">
                                <span class="input-group-text"><?php echo pp_get_site_url() . '/'; ?></span>
                                <input type="text" class="form-control" id="pretty_link_slug" name="pretty_link_slug" value="<?php echo htmlspecialchars($settings['pretty_link_slug']); ?>">
                                <button class="btn btn-soft-primary" type="button" id="copyPrettyLinkBtn">Copy Link</button>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <strong>Heads up!</strong> For the pretty link to work, you need to add the following rewrite rule to your server's configuration file (e.g., <code>.htaccess</code> for Apache). If you disable this option, you must also manually remove the rule.
                            <pre class="mt-2"><code>RewriteEngine On
RewriteRule ^<?php echo trim(htmlspecialchars($settings['pretty_link_slug']), '/'); ?>/?$ /pp-content/plugins/modules/customizable-payment-link-generator/link.php [L]</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h4 class="card-title">Form Fields</h4></div>
                <div class="card-body">
                    <h5>Fields to show on the payment form:</h5>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="show_name" name="show_name" <?php echo $settings['show_name'] === 'true' ? 'checked' : ''; ?>><label class="form-check-label" for="show_name">Customer Name</label></div>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="show_contact" name="show_contact" <?php echo $settings['show_contact'] === 'true' ? 'checked' : ''; ?>><label class="form-check-label" for="show_contact">Customer Email or Phone</label></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>

        <div class="card mb-3 mt-3">
            <div class="card-header"><h4 class="card-title">Plugin Updates</h4></div>
            <div class="card-body">
                <p class="form-text">Check for new versions of the plugin directly from GitHub.</p>
                <button id="checkForUpdatesBtn" class="btn btn-secondary">Check for Updates</button>
                <div id="updateCheckResponse" class="mt-3"></div>
            </div>
        </div>

        <div class="developer-info" style="margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px; text-align: center;">
            <h5 class="developer-title" style="margin-bottom: 10px;">Plugin Developer Information</h5>
            <p class="developer-name" style="margin-bottom: 5px; font-size: 16px;"><strong>Refat Rahman</strong></p>
            <div class="developer-links">
                <a href="https://www.facebook.com/rjrefat" target="_blank" style="margin-right: 10px; text-decoration: none;">
                    <i class="fab fa-facebook-square" style="font-size: 24px;"></i> Facebook
                </a>
                <a href="https://github.com/refatbd/" target="_blank" style="text-decoration: none;">
                    <i class="fab fa-github-square" style="font-size: 24px;"></i> Github
                </a>
            </div>
        </div>

    </div>
</div>

<script>
$(document).ready(function() {
    function showResponse(message, isSuccess) {
        const alertClass = isSuccess ? 'alert-success' : 'alert-danger';
        $('#ajaxResponse').html(`<div class="alert ${alertClass} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`);
        window.scrollTo(0, 0);
    }

    $('#customizableLinkSettingsForm').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const button = form.find('button[type="submit"]');
        const originalButtonText = button.html();
        button.html('<span class="spinner-border spinner-border-sm"></span> Saving...').prop('disabled', true);

        $.ajax({
            url: '', type: 'POST', data: form.serialize(), dataType: 'json',
            success: function(response) {
                showResponse(response.message, response.status);
                 setTimeout(() => {
                    location.reload();
                }, 1000);
            },
            error: function() { showResponse('An unexpected error occurred. Please check server logs.', false); },
            complete: function() { button.html(originalButtonText).prop('disabled', false); }
        });
    });

    $('#copyLinkBtn').on('click', function() {
        const urlInput = $('#paymentLinkUrl');
        urlInput.select();
        document.execCommand('copy');
        $(this).text('Copied!');
        setTimeout(() => {
            $(this).text('Copy Link');
        }, 2000);
    });

    $('#copyPrettyLinkBtn').on('click', function() {
        const siteUrl = '<?php echo pp_get_site_url(); ?>/';
        const slug = $('#pretty_link_slug').val();
        const fullUrl = siteUrl + slug;
        
        const tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(fullUrl).select();
        document.execCommand('copy');
        tempInput.remove();

        $(this).text('Copied!');
        setTimeout(() => {
            $(this).text('Copy Link');
        }, 2000);
    });

    $('#pretty_link_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('#prettyLinkOptions').slideDown();
        } else {
            $('#prettyLinkOptions').slideUp();
        }
    });

    $('#use_system_currency').on('change', function() {
        const currencyDropdown = $('#link_currency');
        if ($(this).is(':checked')) {
            currencyDropdown.val('<?php echo $system_currency; ?>').prop('disabled', true);
        } else {
            currencyDropdown.prop('disabled', false);
        }
    });

    // --- Update Checker ---
    $('#checkForUpdatesBtn').on('click', function() {
        const button = $(this);
        const responseContainer = $('#updateCheckResponse');
        const originalButtonText = button.html();
        
        button.html('<span class="spinner-border spinner-border-sm"></span> Checking...').prop('disabled', true);
        responseContainer.html('');

        $.ajax({
            url: '',
            type: 'POST',
            data: { 'customizable-payment-link-generator-action': 'check_for_updates' },
            dataType: 'json',
            success: function(response) {
                if (response.status) {
                    if (response.update_available) {
                        const update = response.data;
                        const changelog = update.changelog.replace(/\n/g, '<br>');
                        const updateHtml = `
                            <div class="alert alert-info mt-3">
                                <h4 class="alert-heading">🚀 New Version Available!</h4>
                                <p>A new version (<strong>${update.new_version}</strong>) is available.</p>
                                <hr>
                                NORMALIZED_TEXT:
                                <div>${changelog}</div>
                                <a href="${update.download_url}" class="btn btn-success mt-3" target="_blank">Download Update</a>
                            </div>`;
                        responseContainer.html(updateHtml);
                    } else {
                        responseContainer.html(`<div class="alert alert-success mt-3">${response.message}</div>`);
                    }
                } else {
                     responseContainer.html(`<div class="alert alert-danger mt-3">Error: ${response.message}</div>`);
                }
            },
            error: function() {
                responseContainer.html('<div class="alert alert-danger mt-3">An unexpected error occurred.</div>');
            },
            complete: function() {
                button.html(originalButtonText).prop('disabled', false);
            }
        });
    });
});
</script>