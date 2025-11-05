<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// --- Sync stock for manually approved transactions ---
cplg_sync_all_unprocessed_stock();


// Get global PipraPay settings
global $global_setting_response; 
$system_currency = isset($global_setting_response['response'][0]['default_currency']) ? $global_setting_response['response'][0]['default_currency'] : 'USD';
$system_currency_symbol = isset($global_setting_response['response'][0]['currency_symbol']) ? $global_setting_response['response'][0]['currency_symbol'] : '$';
$currencies = cplg_get_all_currencies();

// --- View Routing ---
$action = $_GET['cplg_action'] ?? 'list';
$link_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$base_page_url = '?page=modules--customizable-payment-link-generator';


$edit_link = null;
if ($action === 'edit' && $link_id) {
    $edit_link = cplg_get_link_settings($link_id);
} elseif ($action === 'create') {
    $edit_link = cplg_get_default_settings(); // Pre-fill with defaults
}

// Ensure all new fields exist for the form
if ($edit_link) {
    $edit_link = array_merge(cplg_get_default_settings(), $edit_link);
}

// --- Determine current tab ---
$current_tab = 'list'; // Default
if ($action === 'create' || $action === 'edit') {
    $current_tab = 'edit';
} elseif ($action === 'reports') {
    $current_tab = 'reports';
} elseif ($action === 'transactions' || $action === 'view_transaction') {
    $current_tab = 'transactions';
}

?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
    .nav-tabs .nav-link.active {
        background-color: #f8f9fa;
        border-bottom-color: #f8f9fa;
    }
    .cplg-search-form {
        margin-bottom: 1.5rem;
    }

    /* --- NEW: Advanced Custom Fields --- */
    #customFieldsContainer {
        padding: 10px;
        background: #fdfdfd;
        border: 1px solid #eee;
        border-radius: 4px;
    }
    .custom-field-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 5px;
        margin-bottom: 10px;
        padding: 10px;
    }
    .custom-field-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .custom-field-header .field-type {
        font-weight: 600;
        margin-left: 5px;
    }
    .custom-field-header .field-label-preview {
        font-weight: 600;
        color: #333;
        margin-left: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 200px;
    }
    .custom-field-header .badge {
        font-size: 0.8rem;
    }
    .custom-field-body {
        /* display: none; */ /* Collapsed by default */
    }
    .custom-field-options {
        margin-top: 10px;
    }
    .custom-field-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #f1f1f1;
    }
    .field-type-select-group {
        border-top: 1px solid #eee;
        padding-top: 15px;
        margin-top: 15px;
    }

    /* --- Transaction Details --- */
    .details-list { list-style: none; padding-left: 0; }
    .details-list li { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #e9ecef; }
    .details-list li span { color: #6c757d; font-weight: 500; }
    .details-list li strong { color: #333; text-align: right; word-break: break-all; padding-left: 1rem; }
    .pagination .page-item .page-link { color: #2dbd83; }
    .pagination .page-item.active .page-link { background-color: #2dbd83; border-color: #2dbd83; color: #fff; }
    .badge.bg-status-completed, .badge.bg-status-succeeded { background-color: #e6f6f1 !important; color: #00864e !important; }
    .badge.bg-status-pending, .badge.bg-status-initialize { background-color: #fff6e0 !important; color: #f08c00 !important; }
    .badge.bg-status-failed, .badge.bg-status-cancelled, .badge.bg-status-refunded { background-color: #fdeeee !important; color: #d93d3d !important; }
    
    /* Transaction table checkbox */
    .table th.checkbox-col, .table td.checkbox-col {
        width: 3rem;
        text-align: center;
    }
    
    /* --- NEW: Update styles --- */
    .update-card {
        border: 1px solid #e9ecef;
        border-radius: 5px;
        margin-bottom: 1rem;
    }
    .update-card-header {
        background-color: #f8f9fa;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e9ecef;
        font-weight: 600;
    }
    .update-card-body {
        padding: 1rem;
    }
    .changelog {
        background: #fff;
        border: 1px solid #eee;
        padding: 1rem;
        border-radius: 4px;
        max-height: 200px;
        overflow-y: auto;
    }
    .changelog ul { margin-bottom: 0; }
</style>

<div class="page-header">
  <h1 class="page-header-title">Customizable Payment Links</h1>
</div>

<div id="ajaxResponse" class="mb-3"></div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_tab === 'list' || $current_tab === 'edit') ? 'active' : ''; ?>" href="<?php echo $base_page_url; ?>">
            <i class="fas fa-link me-1"></i> Payment Links
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_tab === 'transactions') ? 'active' : ''; ?>" href="<?php echo $base_page_url; ?>&cplg_action=transactions">
            <i class="fas fa-receipt me-1"></i> Transactions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_tab === 'reports') ? 'active' : ''; ?>" href="<?php echo $base_page_url; ?>&cplg_action=reports">
            <i class="fas fa-chart-pie me-1"></i> Reports
        </a>
    </li>
</ul>


<?php if ($action === 'list'): ?>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">All Payment Links</h4>
            <a href="<?php echo $base_page_url; ?>&cplg_action=create" class="btn btn-primary">Create New Link</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Link URL</th>
                            <th>Amount Mode</th>
                            <th>Stock</th>
                            <th>Enabled</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $all_links = cplg_get_all_links();
                        if (empty($all_links)):
                        ?>
                            <tr>
                                <td colspan="6" class="text-center">No payment links found. <a href="<?php echo $base_page_url; ?>&cplg_action=create">Create one now</a>.</td>
                            </tr>
                        <?php
                        else:
                            foreach ($all_links as $link):
                                $payment_link = '';
                                if ($link['pretty_link_enabled'] === 'true' && !empty($link['link_slug'])) {
                                    $payment_link = pp_get_site_url() . '/' . trim($link['link_slug'], '/');
                                } else {
                                    $payment_link = pp_get_site_url() . '/pp-content/plugins/modules/customizable-payment-link-generator/link.php?slug=' . $link['link_slug'];
                                }
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($link['link_title']); ?></strong>
                                    <?php if ($link['is_default']): ?>
                                        <span class="badge bg-soft-primary text-primary ms-1">Default</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="min-width: 220px;">
                                        <input type="text" readonly class="form-control form-control-sm" id="paymentLinkUrl-<?php echo $link['id']; ?>" value="<?php echo $payment_link; ?>" style="background: #f8f9fa;">
                                        <button class="btn btn-soft-primary btn-sm copyLinkBtn mt-1" data-target-id="paymentLinkUrl-<?php echo $link['id']; ?>" type="button">Copy</button>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($link['amount_mode'] === 'fixed'): ?>
                                        <span class="badge bg-soft-info text-info">Fixed</span>
                                    <?php else: ?>
                                        <span class="badge bg-soft-secondary text-secondary">Custom</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ((int)$link['total_stock'] > 0): 
                                        echo (int)$link['current_stock'] . ' / ' . (int)$link['total_stock'];
                                    else:
                                        echo '<span class="text-muted">N/A</span>';
                                    endif; 
                                    ?>
                                </td>
                                <td>
                                    <?php if ($link['link_enabled'] === 'true'): ?>
                                        <span class="badge bg-soft-success text-success">Enabled</span>
                                    <?php else: ?>
                                        <span class="badge bg-soft-danger text-danger">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo $base_page_url; ?>&cplg_action=edit&id=<?php echo $link['id']; ?>" class="btn btn-soft-primary btn-sm">Edit</a>
                                    <?php if (!$link['is_default']): ?>
                                        <button class="btn btn-soft-danger btn-sm deleteLinkBtn" data-link-id="<?php echo $link['id']; ?>" data-link-title="<?php echo htmlspecialchars($link['link_title']); ?>">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php
                            endforeach;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><h4 class="card-title">Plugin Updates</h4></div>
                <div class="card-body">
                    <p class="form-text">Check for new versions of the plugin. Updates can be installed directly.</p>
                    <button id="checkForUpdatesBtn" class="btn btn-secondary">Check for Updates</button>
                    <div id="updateCheckResponse" class="mt-3"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
             <div class="card mb-3">
                <div class="card-header"><h4 class="card-title">Developer Info</h4></div>
                <div class="card-body">
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
    </div>

<?php 
elseif ($action === 'reports'): 
    
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
    $start_date_safe = htmlspecialchars($start_date);
    $end_date_safe = htmlspecialchars($end_date);
    
    $clear_link = $base_page_url . '&cplg_action=reports';
?>
    
    <div class="card cplg-search-form">
        <div class="card-body">
            <form method="GET" action="<?php echo $base_page_url; ?>">
                <input type="hidden" name="page" value="modules--customizable-payment-link-generator">
                <input type="hidden" name="cplg_action" value="reports">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" value="<?php echo $start_date_safe; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" value="<?php echo $end_date_safe; ?>">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
                        <a href="<?php echo $clear_link; ?>" class="btn btn-soft-secondary ms-2">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title">Link Reports</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Link Title</th>
                            <th>Total Revenue</th>
                            <th>Transactions</th>
                            <th>Items Sold</th>
                            <th>Average Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $reports = cplg_get_link_reports($start_date, $end_date);
                        if (empty($reports)):
                        ?>
                            <tr>
                                <td colspan="5" class="text-center">No completed transactions found<?php echo (!empty($start_date) || !empty($end_date)) ? ' for this date range' : ''; ?>.</td>
                            </tr>
                        <?php
                        else:
                            $total_revenue = 0;
                            $total_count = 0;
                            $total_items = 0;
                            foreach ($reports as $report):
                                $total_revenue += $report['revenue'];
                                $total_count += $report['count'];
                                $total_items += $report['items_sold'];
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($report['title']); ?></strong></td>
                                <td><?php echo $system_currency_symbol; ?> <?php echo number_format($report['revenue'], 2); ?></td>
                                <td><?php echo $report['count']; ?></td>
                                <td><?php echo (int)$report['items_sold']; ?></td>
                                <td><?php echo $system_currency_symbol; ?> <?php echo number_format($report['avg'], 2); ?></td>
                            </tr>
                        <?php
                            endforeach;
                        ?>
                        <tr class="table-light" style="font-weight: bold;">
                            <td>Total</td>
                            <td><?php echo $system_currency_symbol; ?> <?php echo number_format($total_revenue, 2); ?></td>
                            <td><?php echo $total_count; ?></td>
                            <td><?php echo $total_items; ?></td>
                            <td>
                                <?php 
                                $total_avg = ($total_count > 0) ? $total_revenue / $total_count : 0;
                                echo $system_currency_symbol; ?> <?php echo number_format($total_avg, 2); 
                                ?>
                            </td>
                        </tr>
                        <?php
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php 
elseif ($action === 'transactions'): 
    
    // Get all filters
    $search_term = isset($_GET['cplg_search']) ? trim($_GET['cplg_search']) : '';
    $filter_status = isset($_GET['cplg_status']) ? trim($_GET['cplg_status']) : '';
    $filter_start_date = isset($_GET['cplg_start_date']) ? trim($_GET['cplg_start_date']) : '';
    $filter_end_date = isset($_GET['cplg_end_date']) ? trim($_GET['cplg_end_date']) : '';
    
    // Create safe versions for HTML output
    $search_term_safe = htmlspecialchars($search_term);
    $filter_status_safe = htmlspecialchars($filter_status);
    $filter_start_date_safe = htmlspecialchars($filter_start_date);
    $filter_end_date_safe = htmlspecialchars($filter_end_date);
    $clear_link = $base_page_url . '&cplg_action=transactions';

    // Pagination
    $items_per_page = 20;
    $current_page = isset($_GET['cplg_page']) ? max(1, (int)$_GET['cplg_page']) : 1;
    
    // Get counts and data using all filters
    $total_items = cplg_get_transaction_count($search_term, $filter_status, $filter_start_date, $filter_end_date);
    $total_pages = ceil($total_items / $items_per_page);
    $offset = ($current_page - 1) * $items_per_page;
    $transactions = cplg_get_all_transactions($search_term, $filter_status, $filter_start_date, $filter_end_date, $items_per_page, $offset);
    
    // Build pagination query string with all filters
    $pagination_query_string = "&cplg_action=transactions";
    if (!empty($search_term)) { $pagination_query_string .= "&cplg_search=" . urlencode($search_term); }
    if (!empty($filter_status)) { $pagination_query_string .= "&cplg_status=" . urlencode($filter_status); }
    if (!empty($filter_start_date)) { $pagination_query_string .= "&cplg_start_date=" . urlencode($filter_start_date); }
    if (!empty($filter_end_date)) { $pagination_query_string .= "&cplg_end_date=" . urlencode($filter_end_date); }

    $all_stati = ['completed', 'pending', 'initialize', 'failed', 'cancelled', 'refunded'];
?>
    <div class="card cplg-search-form">
        <div class="card-body">
            <form method="GET" action="<?php echo $base_page_url; ?>">
                <input type="hidden" name="page" value="modules--customizable-payment-link-generator">
                <input type="hidden" name="cplg_action" value="transactions">
                
                <div class="row g-3 mb-3">
                    <div class="col-lg-12">
                         <label for="cplg_search" class="form-label">Search</label>
                        <input type="text" class="form-control" name="cplg_search" id="cplg_search" value="<?php echo $search_term_safe; ?>" placeholder="Search by Payment ID, Customer Name, Trx ID, or Sender Number...">
                    </div>
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="cplg_status" class="form-label">Status</label>
                        <select name="cplg_status" id="cplg_status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach ($all_stati as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $filter_status_safe === $status ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="col-md-3">
                        <label for="cplg_start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="cplg_start_date" id="cplg_start_date" value="<?php echo $filter_start_date_safe; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="cplg_end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" name="cplg_end_date" id="cplg_end_date" value="<?php echo $filter_end_date_safe; ?>">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
                        <a href="<?php echo $clear_link; ?>" class="btn btn-soft-secondary ms-2">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title">Transactions</h4>
        </div>
        <div class="card-body">
            <form id="bulkActionForm">
                <button type="button" id="bulkDeleteBtn" class="btn btn-danger btn-sm mb-3" style="display: none;">
                    <i class="fas fa-trash-alt"></i> Delete Selected
                </button>
            
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="checkbox-col"><input type="checkbox" id="selectAllCheckbox" class="form-check-input"></th>
                                <th>Payment ID</th>
                                <th>Customer Name</th>
                                <th>Sender Number</th>
                                <th>Gateway Trx ID</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <?php echo !empty($search_term) || !empty($filter_status) || !empty($filter_start_date) || !empty($filter_end_date) ? 'No transactions found matching your filters.' : 'No transactions found.'; ?>
                                    </td>
                                </tr>
                            <?php else:
                                foreach ($transactions as $trx):
                                    $status_class = 'bg-status-' . strtolower(htmlspecialchars($trx['transaction_status']));
                                    $sender_number = htmlspecialchars($trx['payment_sender_number'] ?? '--');
                                    $verify_id = htmlspecialchars($trx['payment_verify_id'] ?? '--');
                                    
                                    if (in_array($sender_number, ['--', ''])) $sender_number = '<span class="text-muted">N/A</span>';
                                    if (in_array($verify_id, ['--', ''])) $verify_id = '<span class="text-muted">N/A</span>';
                            ?>
                                <tr>
                                    <td class="checkbox-col">
                                        <input type="checkbox" class="form-check-input trx-checkbox" name="pp_ids[]" value="<?php echo htmlspecialchars($trx['pp_id']); ?>">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($trx['pp_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($trx['c_name']); ?></td>
                                    <td><?php echo $sender_number; ?></td>
                                    <td><?php echo $verify_id; ?></td>
                                    <td><?php echo htmlspecialchars($trx['transaction_amount']) . ' ' . htmlspecialchars($trx['transaction_currency']); ?></td>
                                    <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst(htmlspecialchars($trx['transaction_status'])); ?></span></td>
                                    <td><?php echo date("d M Y, h:i A", strtotime($trx['created_at'])); ?></td>
                                    <td>
                                        <a href="<?php echo $base_page_url; ?>&cplg_action=view_transaction&trx_id=<?php echo $trx['pp_id']; ?>" class="btn btn-soft-primary btn-sm">View Details</a>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            endif; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </form>
            
            <?php 
            if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-4">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?php echo $base_page_url . $pagination_query_string; ?>&cplg_page=<?php echo $current_page - 1; ?>">Previous</a></li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $base_page_url . $pagination_query_string; ?>&cplg_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="<?php echo $base_page_url . $pagination_query_string; ?>&cplg_page=<?php echo $current_page + 1; ?>">Next</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
        </div>
    </div>

<?php 
elseif ($action === 'view_transaction'):
    
    $trx_id = $_GET['trx_id'] ?? null;
    $trx = null;
    $metadata = [];
    $custom_fields = [];
    $quantity = 1;

    if ($trx_id) {
        $trx = cplg_get_transaction_by_pp_id($trx_id);
    }
    
    if (!$trx) {
        echo "<div class='alert alert-danger'>Transaction not found or it does not belong to this plugin.</div>";
    } else {
        $metadata = json_decode($trx['transaction_metadata'], true);
        if (is_array($metadata)) {
            $custom_fields = $metadata['custom_fields'] ?? [];
            $quantity = $metadata['cplg_quantity'] ?? 1;
        }
        $status_class = 'bg-status-' . strtolower(htmlspecialchars($trx['transaction_status']));
    }
?>
    
    <?php if ($trx): ?>
    <div class="mb-3">
         <a href="<?php echo $base_page_url; ?>&cplg_action=transactions" class="btn btn-soft-secondary">
            <i class="fas fa-arrow-left"></i> &nbsp; Back to Transactions
         </a>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">
                    <h4 class="card-title">Transaction Details</h4>
                </div>
                <div class="card-body">
                    <ul class="details-list">
                        <li><span>Payment ID:</span><strong><?php echo htmlspecialchars($trx['pp_id']); ?></strong></li>
                        <li><span>Status:</span><strong><span class="badge <?php echo $status_class; ?> fs-6"><?php echo ucfirst(htmlspecialchars($trx['transaction_status'])); ?></span></strong></li>
                        <li><span>Customer Name:</span><strong><?php echo htmlspecialchars($trx['c_name']); ?></strong></li>
                        <li><span>Customer Contact:</span><strong><?php echo htmlspecialchars($trx['c_email_mobile']); ?></strong></li>
                        
                        <?php if ($quantity > 1): ?>
                             <li><span>Unit Price:</span><strong><?php echo htmlspecialchars(number_format((float)$trx['transaction_amount'] / $quantity, 2)) . ' ' . htmlspecialchars($trx['transaction_currency']); ?></strong></li>
                             <li><span>Quantity:</span><strong><?php echo $quantity; ?></strong></li>
                             <li><span>Total Amount:</span><strong><?php echo htmlspecialchars($trx['transaction_amount']) . ' ' . htmlspecialchars($trx['transaction_currency']); ?></strong></li>
                        <?php else: ?>
                            <li><span>Amount:</span><strong><?php echo htmlspecialchars($trx['transaction_amount']) . ' ' . htmlspecialchars($trx['transaction_currency']); ?></strong></li>
                        <?php endif; ?>

                        <li><span>Payment Method:</span><strong><?php echo htmlspecialchars($trx['payment_method']); ?></strong></li>
                        <li><span>Sender Number:</span><strong><?php echo htmlspecialchars($trx['payment_sender_number']); ?></strong></li>
                        <li><span>Gateway TRx ID:</span><strong><?php echo htmlspecialchars($trx['payment_verify_id']); ?></strong></li>
                        <li><span>Date:</span><strong><?php echo date("d M Y, h:i A", strtotime($trx['created_at'])); ?></strong></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <?php if (!empty($custom_fields)): ?>
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Custom Fields Data</h4>
                </div>
                <div class="card-body">
                    <ul class="details-list">
                        <?php foreach ($custom_fields as $label => $value): ?>
                            <li>
                                <span><?php echo htmlspecialchars($label); ?>:</span>
                                <strong><?php echo is_array($value) ? implode(', ', array_map('htmlspecialchars', $value)) : htmlspecialchars($value); ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php else: ?>
                 <div class="card">
                    <div class="card-header"><h4 class="card-title">Custom Fields Data</h4></div>
                    <div class="card-body"><p class="text-muted">No custom field data was submitted for this transaction.</p></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php endif; // end if($trx) ?>


<?php 
// --- EDIT/CREATE PAGE ---
elseif ($action === 'create' || ($action === 'edit' && $edit_link)): 
?>

    <form id="customizableLinkSettingsForm" method="post" action="">
        <input type="hidden" name="customizable-payment-link-generator-action" value="save_link">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="link_id" value="<?php echo $edit_link['id']; ?>">
            <input type="hidden" name="is_default" value="<?php echo $edit_link['is_default']; ?>">
            <input type="hidden" name="current_stock" value="<?php echo $edit_link['current_stock']; ?>">
        <?php endif; ?>

        <div class="mb-3 d-flex justify-content-between align-items-center">
             <a href="<?php echo $base_page_url; ?>" class="btn btn-soft-secondary">
                <i class="fas fa-arrow-left"></i> &nbsp; Back to List
             </a>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><h4 class="card-title">Link Settings</h4></div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="link_enabled" name="link_enabled" <?php echo $edit_link['link_enabled'] === 'true' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="link_enabled"><b>Enable This Payment Link</b></label>
                        </div>

                        <div class="mb-3">
                            <label for="link_title" class="form-label">Link Title</label>
                            <input type="text" class="form-control" id="link_title" name="link_title" value="<?php echo htmlspecialchars($edit_link['link_title']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="link_description" class="form-label">Link Description</label>
                            <textarea class="form-control" id="link_description" name="link_description" rows="3"><?php echo htmlspecialchars($edit_link['link_description']); ?></textarea>
                        </div>
                         <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="use_system_currency" name="use_system_currency" <?php echo $edit_link['use_system_currency'] === 'true' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="use_system_currency"><b>Use System Currency (<?php echo $system_currency; ?>)</b></label>
                        </div>
                         <div class="mb-3">
                            <label for="link_currency" class="form-label">Currency</label>
                            <select class="form-select" id="link_currency" name="link_currency" <?php echo $edit_link['use_system_currency'] === 'true' ? 'disabled' : ''; ?>>
                                <?php
                                $selected_currency = $edit_link['link_currency'];
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
                    <div class="card-header"><h4 class="card-title">Amount & Stock Controls</h4></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Amount Mode</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="amount_mode" id="amount_mode_custom" value="custom" <?php echo $edit_link['amount_mode'] === 'custom' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="amount_mode_custom">Custom Amount</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="amount_mode" id="amount_mode_fixed" value="fixed" <?php echo $edit_link['amount_mode'] === 'fixed' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="amount_mode_fixed">Fixed Amount</label>
                            </div>
                        </div>

                        <div id="fixedAmountOptions" class="p-3 bg-light rounded" style="display:none;">
                            <div class="mb-3">
                                <label for="fixed_amount" class="form-label">Fixed Amount</label>
                                <input type="number" step="0.01" class="form-control" id="fixed_amount" name="fixed_amount" value="<?php echo htmlspecialchars($edit_link['fixed_amount']); ?>">
                            </div>
                            
                            <hr>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="allow_quantity" name="allow_quantity" <?php echo $edit_link['allow_quantity'] === 'true' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="allow_quantity"><b>Allow Quantity Input</b></label>
                                <small class="form-text text-muted d-block">If checked, the user can select a quantity on the payment page.</small>
                            </div>

                            <div id="stockOptions" style="display:none;">
                                <label for="total_stock" class="form-label">Total Stock</label>
                                <input type="number" step="1" class="form-control" id="total_stock" name="total_stock" value="<?php echo (int)$edit_link['total_stock']; ?>">
                                <small class="form-text text-muted">Set to 0 for unlimited stock. If you set a number, this will enable stock control.</small>
                                
                                <?php if ($action === 'edit' && (int)$edit_link['total_stock'] > 0): ?>
                                    <div class="mt-2 alert alert-info">
                                        <strong>Current Stock:</strong> <?php echo (int)$edit_link['current_stock']; ?> / <?php echo (int)$edit_link['total_stock']; ?>
                                        <br>
                                        <small>To reset or change stock, update the "Total Stock" value. Current stock will adjust automatically.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="customAmountOptions" class="p-3 bg-light rounded" style="display:none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="min_amount" class="form-label">Minimum Amount</label>
                                    <input type="number" step="0.01" class="form-control" id="min_amount" name="min_amount" value="<?php echo htmlspecialchars($edit_link['min_amount']); ?>">
                                    <small class="form-text text-muted">Set to 0.00 for no minimum.</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="max_amount" class="form-label">Maximum Amount</label>
                                    <input type="number" step="0.01" class="form-control" id="max_amount" name="max_amount" value="<?php echo htmlspecialchars($edit_link['max_amount']); ?>">
                                    <small class="form-text text-muted">Set to 0.00 for no maximum.</small>
                                </div>
                            </div>
                             <div class="mb-3">
                                <label for="suggested_amounts" class="form-label">Suggested Amounts (Chips)</label>
                                <input type="text" class="form-control" id="suggested_amounts" name="suggested_amounts" value="<?php echo htmlspecialchars($edit_link['suggested_amounts']); ?>">
                                <small class="form-text text-muted">Enter comma-separated values, e.g., 10, 25, 50</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h4 class="card-title">Form Fields</h4></div>
                    <div class="card-body">
                        <h5>Standard Fields:</h5>
                        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="show_name" name="show_name" <?php echo $edit_link['show_name'] === 'true' ? 'checked' : ''; ?>><label class="form-check-label" for="show_name">Customer Name</label></div>
                        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="show_contact" name="show_contact" <?php echo $edit_link['show_contact'] === 'true' ? 'checked' : ''; ?>><label class="form-check-label" for="show_contact">Customer Email or Phone</label></div>
                        
                        <hr>
                        
                        <h5>Custom Fields:</h5>
                        <div id="customFieldsContainer" class="mb-3">
                            </div>
                        
                        <div class="field-type-select-group">
                             <label class="form-label">Add New Field:</label>
                             <div class="input-group">
                                <select id="newFieldType" class="form-select">
                                    <option value="text">Text Input</option>
                                    <option value="textarea">Text Area</option>
                                    <option value="select">Dropdown Select</option>
                                    <option value="checkbox">Checkboxes</option>
                                    <option value="radio">Radio Buttons</option>
                                </select>
                                <button type="button" id="addCustomFieldBtn" class="btn btn-secondary">
                                    <i class="fas fa-plus"></i> Add Field
                                </button>
                            </div>
                        </div>
                        
                        <input type="hidden" name="custom_fields_json" id="custom_fields_json_input" value="<?php echo htmlspecialchars($edit_link['custom_fields']); ?>">
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h4 class="card-title">Instruction Notice</h4></div>
                    <div class="card-body">
                         <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="show_instruction" name="show_instruction" <?php echo $edit_link['show_instruction'] === 'true' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="show_instruction"><b>Show Instruction Notice</b></label>
                        </div>
                        <div class="mb-3">
                            <label for="instruction_text" class="form-label">Instruction Text</label>
                            <textarea class="form-control" id="instruction_text" name="instruction_text" rows="5"><?php echo htmlspecialchars(stripslashes(str_replace('\r\n', "\n", $edit_link['instruction_text']))); ?></textarea>
                            <small class="form-text text-muted">This text will appear above the payment form. Use line breaks.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><h4 class="card-title">Pretty Link (URL)</h4></div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="pretty_link_enabled" name="pretty_link_enabled" <?php echo $edit_link['pretty_link_enabled'] === 'true' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="pretty_link_enabled"><b>Enable Pretty Link</b></label>
                        </div>

                        <div id="prettyLinkOptions">
                            <div class="mb-3">
                                <label for="link_slug" class="form-label">Pretty Link Slug</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo pp_get_site_url() . '/'; ?></span>
                                    <input type="text" class="form-control" id="link_slug" name="link_slug" value="<?php echo htmlspecialchars($edit_link['link_slug']); ?>" <?php echo ($action === 'edit' && $edit_link['is_default']) ? 'readonly' : ''; ?>>
                                </div>
                                <?php if ($action === 'edit' && $edit_link['is_default']): ?>
                                    <small class="form-text text-muted">The slug for the default link cannot be changed after creation.</small>
                                <?php else: ?>
                                     <small class="form-text text-muted">Use simple, URL-friendly characters (a-z, 0-9, -, /).</small>
                                <?php endif; ?>
                            </div>

                            <div class="alert alert-info">
                                <strong>Note:</strong> Enabling this option will automatically attempt to update your server's <code>.htaccess</code> file.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h4 class="card-title">Appearance Settings</h4></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="logo_url" class="form-label">Logo Image URL</label>
                            <input type="url" class="form-control" id="logo_url" name="logo_url" value="<?php echo htmlspecialchars($edit_link['logo_url']); ?>" placeholder="https://example.com/logo.png">
                            <small class="form-text text-muted">Leave blank to not display a logo.</small>
                        </div>
                        <div class="mb-3">
                            <label for="favicon_url" class="form-label">Favicon URL</label>
                            <input type="url" class="form-control" id="favicon_url" name="favicon_url" value="<?php echo htmlspecialchars($edit_link['favicon_url']); ?>" placeholder="https://example.com/favicon.png">
                            <small class="form-text text-muted">Leave blank to use the default favicon.</small>
                        </div>
                        <hr>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="show_footer_text" name="show_footer_text" <?php echo $edit_link['show_footer_text'] === 'true' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="show_footer_text"><b>Show Secure Payment Footer Text</b></label>
                        </div>
                        <div class="mb-3">
                            <label for="footer_text" class="form-label">Footer Text</label>
                            <input type="text" class="form-control" id="footer_text" name="footer_text" value="<?php echo htmlspecialchars($edit_link['footer_text']); ?>">
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h4 class="card-title">Post-Payment</h4></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="redirect_url" class="form-label">Custom Success Redirect URL</label>
                            <input type="url" class="form-control" id="redirect_url" name="redirect_url" value="<?php echo htmlspecialchars($edit_link['redirect_url']); ?>" placeholder="https://example.com/thank-you">
                            <small class="form-text text-muted">Optional. Leave blank to show the default success page.</small>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save Settings</button>
        <a href="<?php echo $base_page_url; ?>" class="btn btn-soft-secondary">Cancel</a>
    </form>

<?php endif; ?>


<script>
$(document).ready(function() {
    
    const basePageUrl = '<?php echo $base_page_url; ?>';

    function showResponse(message, isSuccess) {
        const alertClass = isSuccess ? 'alert-success' : 'alert-danger';
        $('#ajaxResponse').html(`<div class="alert ${alertClass} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`);
        window.scrollTo(0, 0);
    }

    // --- Form Submission (Create/Edit) ---
    $('#customizableLinkSettingsForm').on('submit', function(e) {
        e.preventDefault();
        
        // --- Custom Fields Serialization ---
        // Must run before form.serialize()
        let fields = [];
        $('.custom-field-card').each(function() {
            const field = {
                type: $(this).data('type'),
                label: $(this).find('input[data-key="label"]').val(),
                options: $(this).find('textarea[data-key="options"]').val() || '',
                required: $(this).find('input[data-key="required"]').is(':checked'),
                enabled: $(this).find('input[data-key="enabled"]').is(':checked'),
            };
            if (field.label) {
                fields.push(field);
            }
        });
        $('#custom_fields_json_input').val(JSON.stringify(fields));
        // --- End Serialization ---

        const form = $(this);
        const button = form.find('button[type="submit"]');
        const originalButtonText = button.html();
        button.html('<span class="spinner-border spinner-border-sm"></span> Saving...').prop('disabled', true);

        $.ajax({
            url: '', type: 'POST', data: form.serialize(), dataType: 'json',
            success: function(response) {
                showResponse(response.message, response.status);
                 if (response.status) {
                    setTimeout(() => {
                        window.location.href = basePageUrl;
                    }, 1000);
                 }
            },
            error: function() { showResponse('An unexpected error occurred. Please check server logs.', false); },
            complete: function() { button.html(originalButtonText).prop('disabled', false); }
        });
    });
    
    // --- Delete Button ---
    $('.deleteLinkBtn').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        const linkId = button.data('link-id');
        const linkTitle = button.data('link-title');
        
        if (confirm(`Are you sure you want to delete the link "${linkTitle}"? This cannot be undone.`)) {
            $.ajax({
                url: '', type: 'POST',
                data: {
                    'customizable-payment-link-generator-action': 'delete_link',
                    'link_id': linkId
                },
                dataType: 'json',
                success: function(response) {
                    showResponse(response.message, response.status);
                    if (response.status) {
                        button.closest('tr').fadeOut(500, function() { $(this).remove(); });
                    }
                },
                error: function() { showResponse('An unexpected error occurred.', false); }
            });
        }
    });

    // --- Copy Link Button ---
    $(document).on('click', '.copyLinkBtn', function() {
        const button = $(this);
        const targetId = button.data('target-id');
        const urlInput = document.getElementById(targetId);
        
        urlInput.select();
        document.execCommand('copy');
        
        const originalText = button.text();
        button.text('Copied!');
        setTimeout(() => { button.text(originalText); }, 2000);
    });

    // --- Form Controls ---
    $('#use_system_currency').on('change', function() {
        const currencyDropdown = $('#link_currency');
        if ($(this).is(':checked')) {
            currencyDropdown.val('<?php echo $system_currency; ?>').prop('disabled', true);
        } else {
            currencyDropdown.prop('disabled', false);
        }
    });

    // --- MODIFIED: Update Checker ---
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
                let html = '';
                
                if (response.status) {
                    if (!response.github.update_available && !response.refat.update_available) {
                        html = `<div class="alert alert-success mt-3">${response.message}</div>`;
                    } else {
                        // GitHub Card
                        if (response.github.update_available) {
                            html += createUpdateCard('GitHub', response.github.data);
                        }
                        // Refat's Server Card
                        if (response.refat.update_available) {
                            html += createUpdateCard("Refat's Server", response.refat.data);
                        }
                    }
                } else {
                     html = `<div class="alert alert-danger mt-3">Error: ${response.message}</div>`;
                }
                responseContainer.html(html);
            },
            error: function() {
                responseContainer.html('<div class="alert alert-danger mt-3">An unexpected error occurred.</div>');
            },
            complete: function() {
                button.html(originalButtonText).prop('disabled', false);
            }
        });
    });
    
    // --- NEW: Helper to build update card ---
    function createUpdateCard(sourceName, update) {
        if (!update || !update.new_version) return '';
        
        // Get the changelog. It's already HTML, so no escaping is needed.
        let changelog = update.changelog || "<p>No changelog provided.</p>";
        
        return `
            <div class="update-card mt-3">
                <div class="update-card-header">
                    Update Available from: ${sourceName}
                </div>
                <div class="update-card-body">
                    <p>A new version (<strong>${update.new_version}</strong>) is available.</p>
                    <strong>Changelog:</strong>
                    <div class="changelog mb-3">${changelog}</div>
                    <button class="btn btn-success install-update-btn" 
                            data-url="${update.download_url}" 
                            data-source="${sourceName}">
                        <i class="fas fa-download"></i> Install Now
                    </button>
                    <div class="install-status text-muted small mt-2"></div>
                </div>
            </div>`;
    }

    // --- NEW: Handle Install Button Click ---
    $(document).on('click', '.install-update-btn', function() {
        const button = $(this);
        const downloadUrl = button.data('url');
        const sourceName = button.data('source');
        const statusContainer = button.siblings('.install-status');

        if (!confirm(`Are you sure you want to update from ${sourceName}? This will back up and replace your current plugin files.`)) {
            return;
        }
        
        const originalButtonText = button.html();
        button.html('<span class="spinner-border spinner-border-sm"></span> Backing up...').prop('disabled', true);
        statusContainer.text('Creating a backup of the current version...');

        $.ajax({
            url: '',
            type: 'POST',
            data: {
                'customizable-payment-link-generator-action': 'install_update',
                'download_url': downloadUrl
            },
            dataType: 'json',
            beforeSend: function() {
                // Change status text right before sending
                // The setTimeout creates a race condition, so we remove it.
                button.html('<span class="spinner-border spinner-border-sm"></span> Installing...');
                statusContainer.text('Downloading and installing the new version...');
            },
            success: function(response) {
                if (response.status) {
                    button.html('<i class="fas fa-check"></i> Update Complete').removeClass('btn-success').addClass('btn-secondary');
                    statusContainer.text('Update successful! Please refresh the page to see changes.');
                    showResponse(response.message, true);
                } else {
                    button.html(originalButtonText).prop('disabled', false);
                    statusContainer.text('Error: ' + response.message);
                    showResponse(response.message, false);
                }
            },
            error: function() {
                button.html(originalButtonText).prop('disabled', false);
                statusContainer.text('An unexpected server error occurred.');
                showResponse('An unexpected server error occurred.', false);
            }
        });
    });


    // --- Amount Mode & Stock Toggle ---
    function toggleAmountFields() {
        const mode = $('input[name="amount_mode"]:checked').val();
        if (mode === 'fixed') {
            $('#fixedAmountOptions').slideDown();
            $('#customAmountOptions').slideUp();
        } else {
            $('#fixedAmountOptions').slideUp();
            $('#customAmountOptions').slideDown();
        }
    }
    
    function toggleStockOptions() {
        if ($('#allow_quantity').is(':checked')) {
            $('#stockOptions').slideDown();
        } else {
            $('#stockOptions').slideUp();
        }
    }
    
    $('input[name="amount_mode"]').on('change', toggleAmountFields);
    $('#allow_quantity').on('change', toggleStockOptions);

    toggleAmountFields(); // init
    toggleStockOptions(); // init


    // ---  Advanced Custom Fields Builder ---
    function escapeHTML(str) {
        if (!str) return "";
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    
    function getFieldTypeLabel(type) {
        const labels = {
            'text': 'Text Input',
            'textarea': 'Text Area',
            'select': 'Dropdown',
            'checkbox': 'Checkboxes',
            'radio': 'Radio Buttons'
        };
        return labels[type] || 'Unknown';
    }

    function renderCustomField(field) {
        const container = $('#customFieldsContainer');
        const optionsNeeded = ['select', 'checkbox', 'radio'];
        const showOptions = optionsNeeded.includes(field.type);
        
        const enabledChecked = field.enabled ? 'checked' : '';
        const requiredChecked = field.required ? 'checked' : '';
        const enabledLabel = field.enabled ? 'Enabled' : 'Disabled';
        const enabledClass = field.enabled ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary';

        const fieldHtml = `
            <div class="custom-field-card" data-type="${field.type}">
                <div class="custom-field-header">
                    <div>
                        <i class="fas fa-grip-vertical me-2" style="cursor: move; color: #ced4da;"></i>
                        <span class="field-label-preview">${escapeHTML(field.label) || 'New Field'}</span>
                    </div>
                    <div>
                        <span class="badge ${enabledClass} me-2">${enabledLabel}</span>
                        <span class="badge bg-soft-info text-info me-2">${getFieldTypeLabel(field.type)}</span>
                        <button type="button" class="btn btn-soft-secondary btn-sm toggle-field-body"><i class="fas fa-edit"></i></button>
                    </div>
                </div>
                <div class="custom-field-body" style="display: none;">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Label</label>
                            <input type="text" class="form-control form-control-sm" data-key="label" value="${escapeHTML(field.label)}" placeholder="e.g., T-Shirt Size">
                        </div>
                    </div>
                    ${showOptions ? `
                    <div class="custom-field-options mb-3">
                        <label class="form-label">Options</label>
                        <textarea class="form-control form-control-sm" data-key="options" rows="3" placeholder="One option per line, e.g.,\nSmall\nMedium\nLarge">${escapeHTML(field.options)}</textarea>
                    </div>
                    ` : ''}
                    <div class="custom-field-footer">
                        <div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" data-key="enabled" ${enabledChecked}>
                                <label class="form-check-label">Enabled</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" data-key="required" ${requiredChecked}>
                                <label class="form-check-label">Required</label>
                            </div>
                        </div>
                        <button type="button" class="btn btn-soft-danger btn-sm removeCustomFieldBtn">Delete</button>
                    </div>
                </div>
            </div>
        `;
        container.append(fieldHtml);
    }
    
    function renderAllCustomFields() {
        const container = $('#customFieldsContainer');
        let fields = [];
        try {
            fields = JSON.parse($('#custom_fields_json_input').val());
        } catch (e) {
            fields = [];
        }

        container.html('');
        if (fields.length === 0) {
            container.html('<small class="form-text text-muted">No custom fields added.</small>');
        }

        fields.forEach(field => {
            renderCustomField(field);
        });
        
        // Make fields sortable
        if (typeof $.fn.sortable === 'function') {
            container.sortable({
                handle: '.fa-grip-vertical',
                axis: 'y',
                stop: function() {
                    // Note: We'll serialize on submit, no need to update hidden input on sort
                }
            });
        }
    }

    // --- Custom Field Actions ---
    
    $('#addCustomFieldBtn').on('click', function() {
        const newFieldType = $('#newFieldType').val();
        const newField = {
            type: newFieldType,
            label: '',
            options: '',
            required: false,
            enabled: true // Default to enabled
        };
        
        if ($('#customFieldsContainer').find('small').length > 0) {
           $('#customFieldsContainer').html(''); // Clear "No fields" message
        }
        
        renderCustomField(newField);
        
        // Auto-expand the new field
        $('#customFieldsContainer').find('.custom-field-card:last .custom-field-body').slideDown();
    });

    $(document).on('click', '.removeCustomFieldBtn', function() {
        if (confirm('Are you sure you want to delete this field?')) {
            $(this).closest('.custom-field-card').remove();
            if ($('#customFieldsContainer').children().length === 0) {
                 $('#customFieldsContainer').html('<small class="form-text text-muted">No custom fields added.</small>');
            }
        }
    });
    
    $(document).on('click', '.toggle-field-body', function() {
        $(this).closest('.custom-field-card').find('.custom-field-body').slideToggle(200);
    });
    
    // Update preview label on input
    $(document).on('input', 'input[data-key="label"]', function() {
        const label = $(this).val();
        $(this).closest('.custom-field-card').find('.field-label-preview').text(label || 'New Field');
    });
    
    // Update enabled/disabled badge
    $(document).on('change', 'input[data-key="enabled"]', function() {
         const badge = $(this).closest('.custom-field-card').find('.custom-field-header .badge').first();
         if ($(this).is(':checked')) {
             badge.text('Enabled').removeClass('bg-soft-secondary text-secondary').addClass('bg-soft-success text-success');
         } else {
             badge.text('Disabled').removeClass('bg-soft-success text-success').addClass('bg-soft-secondary text-secondary');
         }
    });

    renderAllCustomFields(); // init

    if (typeof $.fn.sortable === 'function') {
        $("#customFieldsContainer").sortable({
            handle: '.fa-grip-vertical',
            axis: 'y'
        });
    } else {
        console.warn('CPLG: jQuery UI Sortable is not loaded. Field re-ordering is disabled.');
    }
    
    
    //  Bulk Transaction Delete JS ---
    
    // Toggle "Select All"
    $('#selectAllCheckbox').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.trx-checkbox').prop('checked', isChecked);
        toggleBulkDeleteButton();
    });

    // Toggle individual checkbox
    $(document).on('change', '.trx-checkbox', function() {
        if (!$(this).is(':checked')) {
            $('#selectAllCheckbox').prop('checked', false);
        }
        toggleBulkDeleteButton();
    });

    // Show/hide the delete button
    function toggleBulkDeleteButton() {
        if ($('.trx-checkbox:checked').length > 0) {
            $('#bulkDeleteBtn').show();
        } else {
            $('#bulkDeleteBtn').hide();
        }
    }

    // Handle the bulk delete button click
    $('#bulkDeleteBtn').on('click', function(e) {
        e.preventDefault();
        
        const checkedBoxes = $('.trx-checkbox:checked');
        
        if (checkedBoxes.length === 0) {
            alert('Please select at least one transaction to delete.');
            return;
        }

        if (!confirm(`Are you sure you want to delete ${checkedBoxes.length} selected transaction(s)? This action cannot be undone.`)) {
            return;
        }

        const pp_ids_array = checkedBoxes.map(function() {
            return $(this).val();
        }).get();
        
        const button = $(this);
        const originalButtonText = button.html();
        button.html('<span class="spinner-border spinner-border-sm"></span> Deleting...').prop('disabled', true);

        $.ajax({
            url: '', // Post to the same page
            type: 'POST',
            data: {
                'customizable-payment-link-generator-action': 'bulk_delete_transactions',
                'pp_ids': pp_ids_array
            },
            dataType: 'json',
            success: function(response) {
                showResponse(response.message, response.status);
                if (response.status) {
                    setTimeout(() => {
                        location.reload(); // Reload the page to show the updated list
                    }, 1500);
                }
            },
            error: function() {
                showResponse('An unexpected error occurred during bulk deletion.', false);
            },
            complete: function() {
                button.html(originalButtonText).prop('disabled', false);
            }
        });
    });

});
</script>