<?php
/**
 * Standalone Table Data Explorer page: 100 rows per page, column filters, AJAX pagination.
 * Level 2 of 3-level database navigation.
 *
 * @package    local_admin_functions
 * @copyright  2026 Rosmin Babu
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Apply admin-only debug mode rules.
local_admin_functions_apply_debug_settings();

// Force user login — superadmin only.
require_login();
$context = context_system::instance();
if (!is_siteadmin()) {
    local_admin_functions_access_denied();
}

$tablename = required_param('table', PARAM_ALPHANUMEXT);
$page      = optional_param('page', 1, PARAM_INT);
$perpage   = 100;

// Security check: Verify if table exists in DB.
$all_tables = $DB->get_tables();
sort($all_tables);

if (!in_array($tablename, $all_tables)) {
    print_error('invalidtablename', 'local_admin_functions');
}

$custom_tables = local_admin_functions_get_custom_tables();

// Set up page URL and properties.
$PAGE->set_url(new moodle_url('/local/admin_functions/table_data.php', array('table' => $tablename)));
$PAGE->set_context($context);
$PAGE->set_title("Table Data - {$tablename}");
$PAGE->set_heading(get_string('pluginname', 'local_admin_functions'));

// Add CSS and JS requirements.
$PAGE->requires->css(new moodle_url('/local/admin_functions/styles.css'));
$PAGE->requires->js(new moodle_url('/local/admin_functions/assets/js/admin_functions.js'));

echo $OUTPUT->header();

$back_to_tables_url = (new moodle_url('/local/admin_functions/index.php'))->out(false);

// Get column metadata.
$columns_info = $DB->get_columns($tablename);
$columns = array_keys($columns_info);

// Build filter queries from column inputs.
$select = '1=1';
$params = array();
$param_counter = 0;
$active_filters = array();

foreach ($columns as $col) {
    $filter_val = optional_param('filter_' . $col, '', PARAM_TEXT);
    if ($filter_val !== '') {
        $param_counter++;
        $param_name = "col_filter_" . $param_counter;
        $select .= " AND " . $DB->sql_like($col, ":" . $param_name, false);
        $params[$param_name] = '%' . $filter_val . '%';
        $active_filters['filter_' . $col] = $filter_val;
    }
}

// Count & paginate 100 rows per page.
$total_records = $DB->count_records_select($tablename, $select, $params);
$totalpages = max(1, (int) ceil($total_records / $perpage));
if ($page < 1) $page = 1;
else if ($page > $totalpages) $page = $totalpages;

$offset = ($page - 1) * $perpage;
$records = $DB->get_records_select($tablename, $select, $params, '', '*', $offset, $perpage);

$start_index = ($total_records > 0) ? ($offset + 1) : 0;
$end_index = min($offset + count($records), $total_records);
?>

<!-- Level 2 Page Header & Back Button -->
<div class="mb-3">
    <a href="<?php echo $back_to_tables_url; ?>" class="back-link-clean">
        <i class="fa fa-arrow-left"></i> Back to Tables List
    </a>
</div>

<div class="page-header-row">
    <div>
        <h2 class="page-header-title">Table: <code><?php echo s($tablename); ?></code></h2>
        <p class="page-header-subtitle">Viewing data records (100 rows per page)</p>
    </div>
    <div>
        <span class="badge-pill-active p-2 px-3" style="font-size: 14px;">
            Total Records: <?php echo number_format($total_records); ?>
        </span>
    </div>
</div>

<div class="admin-functions-container card" id="table-records-explorer-app" data-ajax-url="<?php echo (new moodle_url('/local/admin_functions/ajax.php'))->out(false); ?>" data-table="<?php echo s($tablename); ?>">
    <div class="card-body p-4">

        <!-- Column Filtering Toolbar Form -->
        <form action="table_data.php" method="GET" class="filter-bar-single mb-4" id="table-data-filter-form">
            <input type="hidden" name="table" value="<?php echo s($tablename); ?>">
            
            <div class="d-flex align-items-center gap-2 flex-wrap w-100">
                <span class="font-weight-bold text-dark mr-2" style="font-size: 14px;">Quick Table Switch:</span>
                <select name="switch_table" class="custom-select filter-select" style="min-width: 250px;" onchange="if(this.value) window.location.href='table_data.php?table='+this.value;">
                    <option value="">Select Table...</option>
                    <optgroup label="Custom / Featured Tables">
                        <?php foreach ($custom_tables as $ct): ?>
                            <option value="<?php echo s($ct); ?>" <?php echo ($tablename === $ct) ? 'selected' : ''; ?>>★ <?php echo s($ct); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="All Database Tables">
                        <?php foreach ($all_tables as $t): ?>
                            <?php if (!in_array($t, $custom_tables)): ?>
                                <option value="<?php echo s($t); ?>" <?php echo ($tablename === $t) ? 'selected' : ''; ?>><?php echo s($t); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                </select>

                <?php if (!empty($active_filters)): ?>
                    <a href="table_data.php?table=<?php echo s($tablename); ?>" class="btn btn-reset-action ml-auto">
                        <i class="fa fa-refresh mr-1"></i> Reset Column Filters
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Clean Data Table with Integrated Column Filter Header Row & Horizontal Scrollbar -->
        <div class="clean-table-container mb-3" style="max-height: 650px; overflow-x: auto !important; overflow-y: auto !important;">
            <form action="table_data.php" method="GET" id="column-filters-form">
                <input type="hidden" name="table" value="<?php echo s($tablename); ?>">
                
                <table class="clean-table" id="viewer-data-table">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <th><?php echo s($col); ?></th>
                            <?php endforeach; ?>
                            <th class="text-center" style="width: 80px;">Actions</th>
                        </tr>
                        <tr class="column-filter-row" style="background-color: #f1f5f9;">
                            <?php foreach ($columns as $col): ?>
                                <th>
                                    <input type="text" 
                                           name="filter_<?php echo s($col); ?>" 
                                           class="column-filter-input" 
                                           placeholder="Filter <?php echo s($col); ?>..." 
                                           value="<?php echo s(optional_param('filter_' . $col, '', PARAM_TEXT)); ?>"
                                           style="font-size: 13px; height: 32px; padding: 4px 8px; border-radius: 6px; border: 1px solid #cbd5e1; min-width: 110px; width: 100%;">
                                </th>
                            <?php endforeach; ?>
                            <th class="text-center">
                                <button type="submit" class="btn btn-xs btn-primary w-100" style="height: 32px; font-size: 13px;">Filter</button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="<?php echo count($columns) + 1; ?>" class="text-center py-5 text-muted font-italic">No records match the active column filter criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <?php foreach ($columns as $col): ?>
                                        <td class="<?php echo ($col === 'id' || is_numeric($r->$col)) ? 'mono-cell' : ''; ?>">
                                            <?php
                                            if ($r->$col === null) {
                                                echo '<span class="text-muted font-italic">NULL</span>';
                                            } else {
                                                $val_str = (string)$r->$col;
                                                $clean_val = strip_tags($val_str);
                                                if (strlen($clean_val) > 60) {
                                                    $clean_val = substr($clean_val, 0, 57) . '...';
                                                }
                                                echo s($clean_val);
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="text-center">
                                        <a href="view_record.php?table=<?php echo s($tablename); ?>&id=<?php echo s($r->id); ?>" class="btn-action-icon" title="View Full Record Details">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <!-- Footer Summary & AJAX Pagination Container -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 pt-2">
            <div class="text-secondary small font-weight-medium mb-2 mb-md-0" id="table-data-summary-container">
                <?php if ($total_records > 0): ?>
                    Showing <?php echo $start_index; ?> to <?php echo $end_index; ?> of <?php echo number_format($total_records); ?> entries
                <?php endif; ?>
            </div>
            <div id="table-data-pagination-container">
                <?php
                if ($total_records > 0) {
                    $params_url = array_merge(array('table' => $tablename), $active_filters);
                    $baseurl_data = new moodle_url('/local/admin_functions/table_data.php', $params_url);
                    echo $OUTPUT->paging_bar($total_records, $page, $perpage, $baseurl_data);
                }
                ?>
            </div>
        </div>

    </div>
</div>

<!-- Bottom Back Button -->
<div class="mb-4">
    <a href="<?php echo $back_to_tables_url; ?>" class="btn btn-filter-reset">
        <i class="fa fa-arrow-left mr-1"></i> Back to Tables List
    </a>
</div>

<?php
echo $OUTPUT->footer();
