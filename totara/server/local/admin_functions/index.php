<?php
/**
 * Local plugin admin_functions: Database explorer and SQL query runner.
 * Custom Listing Page with 2 tabs, Admin Debug Confirmation Modal, and Detailed SQL Error Reporting.
 *
 * @package    local_admin_functions
 * @copyright  2026 Rosmin Babu
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Apply admin-only debug mode rules.
local_admin_functions_apply_debug_settings();

// Force user login and verify capability.
require_login();
$context = context_system::instance();
if (!is_siteadmin() && !has_capability('moodle/site:config', $context) && !has_capability('local/admin_functions:view', $context)) {
    require_capability('moodle/site:config', $context);
}

// Fetch parameters.
$tab     = optional_param('tab', 'tables', PARAM_ALPHA);
$page    = optional_param('page', 1, PARAM_INT);
$search  = optional_param('search', '', PARAM_TEXT);
$sql     = trim(optional_param('sql', '', PARAM_RAW));
$perpage = 100;

$allowed_tabs = array('tables', 'sql');
if (!in_array($tab, $allowed_tabs)) {
    $tab = 'tables';
}

// Set up page.
$PAGE->set_url(new moodle_url('/local/admin_functions/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_admin_functions'));
$PAGE->set_heading(get_string('pluginname', 'local_admin_functions'));

// Add CSS and JS requirements.
$PAGE->requires->css(new moodle_url('/local/admin_functions/styles.css'));
$PAGE->requires->js(new moodle_url('/local/admin_functions/assets/js/admin_functions.js'));

echo $OUTPUT->header();

// Fetch all database tables.
$all_tables = $DB->get_tables();
sort($all_tables);

// Helper function to return friendly description for Totara database tables.
function get_table_description_index($tablename) {
    $descriptions = array(
        'user' => 'User accounts and profile information',
        'course' => 'Course details and settings',
        'enrol' => 'Course enrolment methods and data',
        'user_enrolments' => 'User course enrolment records',
        'grade_grades' => 'Grades and assessment results',
        'logstore_standard_log' => 'System logs and audit events',
        'role' => 'System roles and definitions',
        'role_assignments' => 'User role assignment records',
        'context' => 'Moodle context hierarchy records',
        'modules' => 'Installed activity modules',
        'course_categories' => 'Course category structure',
        'question' => 'Question bank items',
        'quiz' => 'Quiz activity settings and instances',
        'badge' => 'Badges and accreditation data',
        'cohort' => 'System cohorts and audience groups',
        'config' => 'Core system configuration settings',
        'files' => 'Moodle file storage metadata',
    );
    if (isset($descriptions[$tablename])) {
        return $descriptions[$tablename];
    }
    
    $clean_name = str_replace('_', ' ', $tablename);
    return ucwords($clean_name) . ' database records';
}

$debug_active = local_admin_functions_is_debug_active();
?>

<!-- 1. Page Header Row with Admin Debug Switch -->
<div class="page-header-row">
    <div>
        <h2 class="page-header-title">Tables</h2>
        <p class="page-header-subtitle">Manage and view your table records</p>
    </div>
    <div class="d-flex align-items-center gap-3">
        <?php if (is_siteadmin()): ?>
            <div class="admin-debug-toggle-container">
                <span class="admin-debug-toggle-label"><i class="fa fa-bug mr-1"></i> Debug Mode:</span>
                <label class="admin-debug-switch">
                    <input type="checkbox" id="btn-toggle-admin-debug" <?php echo $debug_active ? 'checked' : ''; ?>>
                    <span class="admin-debug-slider"></span>
                </label>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Main Container -->
<div class="admin-functions-container card" id="admin-functions-tables-app" data-ajax-url="<?php echo (new moodle_url('/local/admin_functions/ajax.php'))->out(false); ?>">
    <div class="card-body p-4">

        <!-- Navigation Tabs (2 Tabs: Tables List & SQL Query Runner) -->
        <ul class="nav nav-tabs admin-nav-tabs mb-4" id="admin-functions-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?php echo ($tab === 'tables') ? 'active' : ''; ?>" href="index.php?tab=tables">
                    <i class="fa fa-list mr-1"></i> Tables List
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($tab === 'sql') ? 'active' : ''; ?>" href="index.php?tab=sql">
                    <i class="fa fa-code mr-1"></i> SQL Query Runner
                </a>
            </li>
        </ul>

        <div class="tab-content" id="admin-functions-tabs-content">
            
            <!-- Pane 1: Tables List -->
            <div class="tab-pane" id="tables-list-pane" role="tabpanel" style="display: <?php echo ($tab === 'tables') ? 'block' : 'none'; ?>;">
                
                <!-- Single-Line Filter Toolbar -->
                <form action="index.php" method="GET" class="filter-bar-single">
                    <input type="hidden" name="tab" value="tables">
                    
                    <div class="search-box">
                        <i class="fa fa-search"></i>
                        <input type="text" 
                               name="search" 
                               id="table-search-input" 
                               class="form-control" 
                               placeholder="Search tables..." 
                               value="<?php echo s($search); ?>"
                               autocomplete="off">
                    </div>

                    <select name="table_select" class="custom-select filter-select" onchange="if(this.value) window.location.href='table_data.php?table='+this.value;">
                        <option value="">Select Table</option>
                        <?php foreach ($all_tables as $t): ?>
                            <option value="<?php echo s($t); ?>"><?php echo s($t); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status_filter" class="custom-select filter-select">
                        <option value="">Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>

                    <select name="created_by" class="custom-select filter-select">
                        <option value="">Created By</option>
                        <option value="admin">Admin</option>
                        <option value="system">System</option>
                    </select>

                    <button type="submit" class="btn btn-filter-action">
                        <i class="fa fa-filter"></i> Filter
                    </button>

                    <a href="index.php?tab=tables" class="btn btn-reset-action">Reset</a>
                </form>

                <?php
                $filtered_tables = $all_tables;
                if ($search !== '') {
                    $filtered_tables = array_filter($all_tables, function($t) use ($search) {
                        return (stripos($t, $search) !== false);
                    });
                }
                $total_tables = count($filtered_tables);
                $totalpages_tables = max(1, (int) ceil($total_tables / $perpage));
                $page_tables = ($tab === 'tables') ? $page : 1;
                if ($page_tables < 1) $page_tables = 1;
                else if ($page_tables > $totalpages_tables) $page_tables = $totalpages_tables;
                
                $offset_tables = ($page_tables - 1) * $perpage;
                $tables_slice = array_slice($filtered_tables, $offset_tables, $perpage);
                $start_index = ($total_tables > 0) ? ($offset_tables + 1) : 0;
                $end_index = min($offset_tables + count($tables_slice), $total_tables);
                ?>

                <!-- Clean Table Container -->
                <div class="clean-table-container">
                    <table class="clean-table" id="db-tables-list">
                        <thead>
                            <tr>
                                <th style="width: 5%">ID <i class="fa fa-caret-down text-muted small ml-1"></i></th>
                                <th style="width: 20%">Table Name</th>
                                <th style="width: 32%">Description</th>
                                <th style="width: 12%">Records <i class="fa fa-sort text-muted small ml-1"></i></th>
                                <th style="width: 10%">Status</th>
                                <th style="width: 10%">Created By</th>
                                <th style="width: 13%">Created At</th>
                                <th style="width: 8%" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($total_tables === 0) {
                                echo '<tr><td colspan="8" class="text-center py-5 text-muted font-italic">No database tables match your search query.</td></tr>';
                            } else {
                                foreach ($tables_slice as $index => $tbl) {
                                    try {
                                        $rows = $DB->count_records($tbl);
                                        $row_count_str = number_format($rows);
                                        $status_badge = ($rows > 0) ? '<span class="badge-pill-active">Active</span>' : '<span class="badge-pill-inactive">Inactive</span>';
                                    } catch (\dml_exception $e) {
                                        $row_count_str = '0';
                                        $status_badge = '<span class="badge-pill-inactive">Inactive</span>';
                                    }
                                    
                                    $desc = get_table_description_index($tbl);
                                    $table_data_url = (new moodle_url('/local/admin_functions/table_data.php', array('table' => $tbl)))->out(false);
                                    
                                    echo '<tr>';
                                    echo '<td class="font-weight-semibold text-muted">' . ($start_index + $index) . '</td>';
                                    echo '<td><a class="font-weight-bold text-dark" href="' . $table_data_url . '">' . s($tbl) . '</a></td>';
                                    echo '<td class="text-secondary">' . s($desc) . '</td>';
                                    echo '<td class="font-weight-semibold">' . $row_count_str . '</td>';
                                    echo '<td>' . $status_badge . '</td>';
                                    echo '<td class="text-secondary">Admin</td>';
                                    echo '<td class="text-secondary">15 Jul 2026 10:30 AM</td>';
                                    echo '<td class="text-center">';
                                    echo '<a href="' . $table_data_url . '" class="btn-action-icon" title="View Table Records"><i class="fa fa-eye"></i></a>';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Summary & AJAX Pagination Container -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 pt-2">
                    <div class="text-secondary small font-weight-medium mb-2 mb-md-0" id="tables-summary-container">
                        <?php if ($total_tables > 0): ?>
                            Showing <?php echo $start_index; ?> to <?php echo $end_index; ?> of <?php echo number_format($total_tables); ?> entries
                        <?php endif; ?>
                    </div>
                    <div id="tables-pagination-container">
                        <?php
                        if ($total_tables > 0) {
                            $baseurl_tables = new moodle_url('/local/admin_functions/index.php', array('tab' => 'tables', 'search' => $search));
                            echo $OUTPUT->paging_bar($total_tables, ($tab === 'tables') ? $page : 1, $perpage, $baseurl_tables);
                        }
                        ?>
                    </div>
                </div>

            </div>

            <!-- Pane 2: SQL Query Runner with Detailed Errors -->
            <div class="tab-pane" id="sql-runner-pane" role="tabpanel" style="display: <?php echo ($tab === 'sql') ? 'block' : 'none'; ?>;">
                <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px; background: #f8fafc;">
                    <div class="card-body p-4">
                        <h6 class="font-weight-bold text-dark mb-2" style="font-size: 1.1rem;">Run Custom Database Query</h6>
                        <p class="text-muted small mb-3">
                            Enter any <code>SELECT</code>, <code>SHOW</code>, <code>EXPLAIN</code> or <code>DESCRIBE</code> SQL command. Use curly braces <code>{table}</code> to automatically append the database prefix. E.g. <code>SELECT * FROM {user} WHERE deleted = 0</code>.
                        </p>
                        
                        <form action="index.php" method="POST" id="sql-runner-form">
                            <input type="hidden" name="tab" value="sql">
                            <div class="form-group mb-3">
                                <textarea name="sql" 
                                          id="sql-input-textarea"
                                          class="form-control sql-textarea" 
                                          rows="5" 
                                          placeholder="SELECT * FROM {user}"><?php echo s($sql); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-filter-action">
                                <i class="fa fa-play mr-1"></i> Run Query
                            </button>
                        </form>
                    </div>
                </div>

                <div class="clean-table-container mb-4" id="sql-results-container">
                    <?php
                    if ($sql !== ''):
                        if (!preg_match('/^(select|show|explain|describe)\b/i', $sql)) {
                            echo '<div class="alert alert-danger m-3 font-size-14">Only SELECT, SHOW, EXPLAIN or DESCRIBE queries are allowed.</div>';
                        } else {
                            try {
                                $clean_sql = rtrim($sql, ';');
                                
                                $total_sql = 0;
                                try {
                                    $countsql = "SELECT COUNT(*) FROM ($clean_sql) temp_count_table";
                                    $total_sql = $DB->count_records_sql($countsql);
                                } catch (\Exception $e) {
                                    $total_sql = 0;
                                }

                                $totalpages_sql = max(1, (int) ceil($total_sql / $perpage));
                                $page_sql = ($tab === 'sql') ? $page : 1;
                                if ($page_sql < 1) $page_sql = 1;
                                else if ($page_sql > $totalpages_sql) $page_sql = $totalpages_sql;
                                
                                $offset_sql = ($page_sql - 1) * $perpage;
                                
                                $recordset = $DB->get_recordset_sql($clean_sql, null, $offset_sql, $perpage);
                                
                                $columns = array();
                                $data_rows = array();
                                foreach ($recordset as $record) {
                                    $record_arr = (array)$record;
                                    if (empty($columns)) {
                                        $columns = array_keys($record_arr);
                                    }
                                    $data_rows[] = $record_arr;
                                }
                                $recordset->close();
                                ?>
                                
                                <div class="p-3 bg-dark text-white d-flex justify-content-between align-items-center" style="border-radius: 8px 8px 0 0;">
                                    <span class="font-weight-bold text-success">
                                        <i class="fa fa-check-circle mr-1"></i> Query Executed Successfully
                                    </span>
                                    <span class="badge badge-info p-2">
                                        <?php echo get_string('rows_returned', 'local_admin_functions', $total_sql > 0 ? $total_sql : count($data_rows)); ?>
                                    </span>
                                </div>

                                <table class="clean-table" id="sql-results-table">
                                    <thead>
                                        <tr>
                                            <?php foreach ($columns as $col): ?>
                                                <th><?php echo s($col); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($data_rows)): ?>
                                            <tr>
                                                <td colspan="<?php echo max(1, count($columns)); ?>" class="text-center py-5 text-muted font-italic">Query executed successfully, but returned no rows.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($data_rows as $row): ?>
                                                <tr>
                                                    <?php foreach ($columns as $col): ?>
                                                        <td class="<?php echo ($col === 'id' || is_numeric($row[$col])) ? 'mono-cell' : ''; ?>">
                                                            <?php
                                                            if ($row[$col] === null) {
                                                                echo '<span class="text-muted font-italic">NULL</span>';
                                                            } else {
                                                                $val_str = (string)$row[$col];
                                                                $clean_val = strip_tags($val_str);
                                                                if (strlen($clean_val) > 80) {
                                                                    $clean_val = substr($clean_val, 0, 77) . '...';
                                                                }
                                                                echo s($clean_val);
                                                            }
                                                            ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            <?php
                            } catch (\Exception $e) {
                                $debug_info = isset($e->debuginfo) ? $e->debuginfo : '';
                                $trace = $debug_active ? $e->getTraceAsString() : '';
                                ?>
                                <div class="card border-danger m-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                                    <div class="card-header bg-danger text-white font-weight-bold d-flex align-items-center">
                                        <i class="fa fa-exclamation-triangle mr-2"></i> SQL Execution Error
                                    </div>
                                    <div class="card-body bg-light text-dark p-4 font-size-14">
                                        <div class="alert alert-danger font-weight-bold mb-3" style="font-size: 14px;">
                                            <?php echo s($e->getMessage()); ?>
                                        </div>

                                        <?php if (!empty($debug_info)): ?>
                                            <div class="mb-3">
                                                <label class="font-weight-bold text-secondary text-uppercase small mb-1">Database Driver Debug Info:</label>
                                                <pre class="bg-dark text-warning p-3 rounded font-size-13 m-0"><code><?php echo s($debug_info); ?></code></pre>
                                            </div>
                                        <?php endif; ?>

                                        <div class="mb-3">
                                            <label class="font-weight-bold text-secondary text-uppercase small mb-1">Executed SQL Query:</label>
                                            <pre class="bg-dark text-white p-3 rounded font-size-13 m-0"><code><?php echo s($clean_sql); ?></code></pre>
                                        </div>

                                        <?php if (!empty($trace)): ?>
                                            <div>
                                                <label class="font-weight-bold text-secondary text-uppercase small mb-1">Debug Stack Trace (Admin Only):</label>
                                                <pre class="bg-secondary text-white p-3 rounded font-size-12 m-0" style="max-height: 200px; overflow-y: auto;"><code><?php echo s($trace); ?></code></pre>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                    endif;
                    ?>
                </div>

                <?php
                if ($sql !== '' && isset($total_sql) && $total_sql > 0) {
                    $baseurl_sql = new moodle_url('/local/admin_functions/index.php', array('tab' => 'sql', 'sql' => $sql));
                    echo $OUTPUT->paging_bar($total_sql, ($tab === 'sql') ? $page : 1, $perpage, $baseurl_sql);
                }
                ?>
            </div>

        </div>
    </div>
</div>

<!-- Admin Debug Confirmation Modal -->
<?php if (is_siteadmin()): ?>
<div class="modal fade" id="debug-confirm-modal" tabindex="-1" role="dialog" aria-labelledby="debugConfirmModalLabel" aria-hidden="true" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title font-weight-bold d-flex align-items-center text-white mb-0" id="debugConfirmModalLabel">
                    <i class="fa fa-bug text-danger mr-2"></i> Confirm Debug Mode Toggle
                </h5>
                <button type="button" class="close text-white" id="modal-close-x" aria-label="Close" style="opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-dark" style="font-size: 14px; line-height: 1.6;">
                <p id="debug-modal-message" class="mb-3 font-weight-medium">
                    Are you sure you want to toggle Developer Debug Mode and show all error messages?
                </p>
                <div class="p-3 bg-light border-left border-danger rounded text-secondary small mb-0">
                    <i class="fa fa-shield text-danger mr-1"></i> <strong>Admin Security Note:</strong> Error messages, warnings, notices, and PHP stack traces will be displayed <strong>only for site administrators</strong>. Non-admin users will continue to have zero error exposure.
                </div>
            </div>
            <div class="modal-footer bg-light py-3 px-4">
                <button type="button" class="btn btn-outline-secondary px-4" id="modal-btn-cancel">Cancel</button>
                <button type="button" class="btn btn-danger px-4 font-weight-bold" id="modal-btn-confirm">
                    <i class="fa fa-check mr-1"></i> Confirm & Switch
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade" id="debug-modal-backdrop" style="display: none;"></div>
<?php endif; ?>

<?php
echo $OUTPUT->footer();
