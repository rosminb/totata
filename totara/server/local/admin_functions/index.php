<?php
/**
 * Local plugin admin_functions: Database explorer and SQL query runner.
 * Custom Listing Page with 2 tabs, Custom Tables filtering, Super Admin Table Selector Modal, and Admin Debug.
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
$scope   = optional_param('scope', 'custom', PARAM_ALPHA);
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

// CSS only — JS is loaded via js_init_code using Totara's AMD require(['jquery']).
$PAGE->requires->css(new moodle_url('/local/admin_functions/styles.css'));

// Build the AJAX URL to pass into JS.
$ajax_url = (new moodle_url('/local/admin_functions/ajax.php'))->out(false);

// Inject all JS via Totara's AMD system using jQuery (Bootstrap 4 needs it).
$PAGE->requires->js_init_code("
require(['jquery'], function($) {

    var ajaxUrl = " . json_encode($ajax_url) . ";

    // === 1. Bootstrap modal for Table Selector ===
    // Update the selected count badge when the modal opens.
    $('#table-selector-modal').on('show.bs.modal', function() {
        updateTsCount();
    });

    function updateTsCount() {
        var total   = $('.ts-table-checkbox').length;
        var checked = $('.ts-table-checkbox:checked').length;
        $('#ts-selected-count-badge').html('Selected: <strong>' + checked + '</strong> of ' + total + ' tables');
    }

    // Table search filter inside modal.
    $('#ts-modal-search').on('input', function() {
        var q = $(this).val().toLowerCase().trim();
        $('.ts-table-item').each(function() {
            var name = ($(this).data('table-name') || '').toLowerCase();
            $(this).toggle(q === '' || name.indexOf(q) !== -1);
        });
    });

    // Quick selection buttons.
    $('#ts-select-custom-only').on('click', function() {
        $('.ts-table-item').each(function() {
            var chk = $(this).find('.ts-table-checkbox');
            chk.prop('checked', $(this).data('is-custom') == '1');
        });
        updateTsCount();
    });

    $('#ts-select-all').on('click', function() {
        $('.ts-table-checkbox').prop('checked', true);
        updateTsCount();
    });

    $('#ts-deselect-all').on('click', function() {
        $('.ts-table-checkbox').prop('checked', false);
        updateTsCount();
    });

    $(document).on('change', '.ts-table-checkbox', function() {
        updateTsCount();
    });

    // Save selected tables via AJAX then close modal and reload.
    $('#ts-modal-btn-save').on('click', function() {
        var selected = [];
        $('.ts-table-checkbox:checked').each(function() {
            selected.push($(this).val());
        });

        var \$btn = $(this);
        \$btn.prop('disabled', true).html('<i class=\"fa fa-spinner fa-spin mr-1\"></i> Saving...');

        var formData = new FormData();
        formData.append('action', 'save_custom_tables');
        formData.append('tables', JSON.stringify(selected));

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(res) { return res.json(); })
            .then(function(res) {
                \$btn.prop('disabled', false).html('<i class=\"fa fa-save mr-1\"></i> Save Selected Tables');
                if (res.success) {
                    \$('#table-selector-modal').modal('hide');
                    window.location.reload();
                } else {
                    alert(res.error || 'Failed to save table selection.');
                }
            })
            .catch(function(err) {
                \$btn.prop('disabled', false).html('<i class=\"fa fa-save mr-1\"></i> Save Selected Tables');
                console.error('Save tables error:', err);
                alert('An error occurred while saving table selection.');
            });
    });

    // === 2. Bootstrap modal for Debug Mode Toggle ===
    var targetDebugState = false;

    $('#btn-toggle-admin-debug').on('click', function(e) {
        e.preventDefault();
        var willEnable = !this.checked;
        this.checked = !willEnable;
        targetDebugState = willEnable;

        if (willEnable) {
            $('#debug-modal-message').html('Are you sure you want to <strong>ENABLE Developer Debug Mode</strong> and show all PHP error messages, notices, and stack traces?');
            $('#modal-btn-confirm').removeClass('btn-primary').addClass('btn-danger').html('<i class=\"fa fa-check mr-1\"></i> Enable All Errors');
        } else {
            $('#debug-modal-message').html('Are you sure you want to <strong>DISABLE Debug Mode</strong> and suppress error displays?');
            $('#modal-btn-confirm').removeClass('btn-danger').addClass('btn-primary').html('<i class=\"fa fa-check mr-1\"></i> Disable Debug');
        }
        \$('#debug-confirm-modal').modal('show');
    });

    $('#modal-btn-cancel').on('click', function() {
        \$('#debug-confirm-modal').modal('hide');
    });

    \$('#debug-confirm-modal').on('hide.bs.modal', function() {
        \$('#btn-toggle-admin-debug').prop('checked', !targetDebugState);
    });

    $('#modal-btn-confirm').on('click', function() {
        var \$btn = $(this);
        \$btn.prop('disabled', true);

        fetch(ajaxUrl + '?action=toggle_debug')
            .then(function(res) { return res.json(); })
            .then(function(res) {
                \$btn.prop('disabled', false);
                if (res.success) {
                    \$('#btn-toggle-admin-debug').prop('checked', !!res.debug);
                    targetDebugState = !!res.debug;
                    \$('#debug-confirm-modal').modal('hide');
                    \$('#debug-confirm-modal').off('hide.bs.modal');
                    window.location.reload();
                } else {
                    alert(res.error || 'Failed to update Debug Mode.');
                    \$('#debug-confirm-modal').modal('hide');
                }
            })
            .catch(function(err) {
                \$btn.prop('disabled', false);
                console.error('Debug toggle error:', err);
                \$('#debug-confirm-modal').modal('hide');
            });
    });

    // === 3. Tables List AJAX (search + filter + pagination) ===
    var tbody1      = document.querySelector('#db-tables-list tbody');
    var summary1    = document.getElementById('tables-summary-container');
    var pagination1 = document.getElementById('tables-pagination-container');
    var filterForm1 = document.querySelector('.filter-bar-single');
    var searchTimeout = null;

    function fetchTablesList(page) {
        if (!tbody1) return;
        var search        = \$('#table-search-input').val().trim();
        var status_filter = \$('select[name=\"status_filter\"]').val() || '';
        var scope         = \$('#table-scope-select').val() || 'custom';

        \$(tbody1).css('opacity', '0.4');

        var params = new URLSearchParams({
            action: 'fetch_tables',
            search: search,
            status_filter: status_filter,
            scope: scope,
            page: page || 1
        });

        fetch(ajaxUrl + '?' + params.toString())
            .then(function(res) { return res.json(); })
            .then(function(res) {
                \$(tbody1).css('opacity', '1');
                if (res.success) {
                    tbody1.innerHTML = res.html;
                    if (summary1) summary1.textContent = res.summary;
                    if (pagination1) pagination1.innerHTML = res.pagination;
                }
            })
            .catch(function(err) {
                \$(tbody1).css('opacity', '1');
                console.error('AJAX Fetch Tables Error:', err);
            });
    }

    \$('#table-search-input').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { fetchTablesList(1); }, 300);
    });

    if (filterForm1) {
        \$(filterForm1).on('submit', function(e) {
            e.preventDefault();
            fetchTablesList(1);
        });
        \$(filterForm1).find('select').on('change', function() {
            if (this.name !== 'table_select') fetchTablesList(1);
        });
    }

    \$(document).on('click', '#tables-pagination-container .paging a', function(e) {
        e.preventDefault();
        var href = \$(this).attr('href');
        if (href) {
            var match = href.match(/page=(\\d+)/);
            fetchTablesList(match ? match[1] : 1);
        }
    });

    // === 4. SQL Query Runner AJAX ===
    var sqlForm = document.getElementById('sql-runner-form');
    var sqlResultsContainer = document.getElementById('sql-results-container');

    if (sqlForm && sqlResultsContainer) {
        \$(sqlForm).on('submit', function(e) {
            e.preventDefault();
            var sqlVal = \$('#sql-input-textarea').val().trim();
            if (!sqlVal) return;

            \$(sqlResultsContainer).css('opacity', '0.4');
            var formData = new FormData();
            formData.append('action', 'run_sql');
            formData.append('sql', sqlVal);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    \$(sqlResultsContainer).css('opacity', '1');
                    if (res.success) {
                        sqlResultsContainer.innerHTML = res.html;
                    } else if (res.error_html) {
                        sqlResultsContainer.innerHTML = res.error_html;
                    } else {
                        sqlResultsContainer.innerHTML = '<div class=\"alert alert-danger m-3\">' + (res.error || 'SQL Execution Failed.') + '</div>';
                    }
                })
                .catch(function(err) {
                    \$(sqlResultsContainer).css('opacity', '1');
                    console.error('SQL Runner Error:', err);
                });
        });
    }

});
", true);

echo $OUTPUT->header();

// Fetch all database tables and custom configured tables.
$db_all_tables = $DB->get_tables();
sort($db_all_tables);

$custom_tables_list = local_admin_functions_get_custom_tables();

if ($scope === 'all') {
    $all_tables = $db_all_tables;
} else {
    $all_tables = $custom_tables_list;
}

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
$is_admin_user = is_siteadmin() || has_capability('moodle/site:config', $context);
?>

<!-- 1. Page Header Row with Admin Controls -->
<div class="page-header-row">
    <div>
        <h2 class="page-header-title">Tables</h2>
        <p class="page-header-subtitle">Manage and view your database table records</p>
    </div>
    <div class="d-flex align-items-center gap-3">
        <?php if ($is_admin_user): ?>
            <!-- Bootstrap modal trigger button -->
            <button type="button"
                    class="btn btn-primary font-weight-bold shadow-sm"
                    id="btn-open-table-selector"
                    data-toggle="modal"
                    data-target="#table-selector-modal"
                    style="font-size: 14px; border-radius: 8px; padding: 0.6rem 1.25rem;">
                <i class="fa fa-th-list mr-1"></i> Select Custom Tables
            </button>

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

                    <select name="scope" id="table-scope-select" class="custom-select filter-select font-weight-bold" style="min-width: 200px; color: #2563eb;">
                        <option value="custom" <?php echo ($scope === 'custom') ? 'selected' : ''; ?>>Custom Tables (Default)</option>
                        <option value="all" <?php echo ($scope === 'all') ? 'selected' : ''; ?>>All Database Tables</option>
                    </select>

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
                                echo '<tr><td colspan="8" class="text-center py-5 text-muted font-italic">No database tables match your filter criteria. ';
                                if ($is_admin_user) {
                                    echo '<button type="button" class="btn btn-link p-0 font-weight-bold" data-toggle="modal" data-target="#table-selector-modal">Select Custom Tables</button>';
                                }
                                echo '</td></tr>';
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
                            $baseurl_tables = new moodle_url('/local/admin_functions/index.php', array('tab' => 'tables', 'search' => $search, 'scope' => $scope));
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

<?php if ($is_admin_user): ?>

<!-- ============================================================
     Bootstrap 4 Modal: Admin Debug Mode Toggle Confirmation
     ============================================================ -->
<div class="modal fade" id="debug-confirm-modal"
     tabindex="-1" role="dialog"
     aria-labelledby="debugConfirmModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title font-weight-bold d-flex align-items-center text-white mb-0" id="debugConfirmModalLabel">
                    <i class="fa fa-bug text-danger mr-2"></i> Confirm Debug Mode Toggle
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.8;">
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
                <button type="button" class="btn btn-outline-secondary px-4" id="modal-btn-cancel" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4 font-weight-bold" id="modal-btn-confirm">
                    <i class="fa fa-check mr-1"></i> Confirm &amp; Switch
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Bootstrap 4 Modal: Super Admin Table Selector
     ============================================================ -->
<div class="modal fade" id="table-selector-modal"
     tabindex="-1" role="dialog"
     aria-labelledby="tableSelectorModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title font-weight-bold d-flex align-items-center text-white mb-0" id="tableSelectorModalLabel">
                    <i class="fa fa-th-list mr-2"></i> Select Custom Tables to List
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-dark" style="font-size: 14px;">
                <p class="text-muted small mb-3">
                    Check the database tables you want to feature in the <strong>Custom Tables</strong> view. Site administrators can select any combination of tables.
                </p>

                <!-- Search & Quick Selection Controls -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="position-relative flex-grow-1" style="min-width: 250px;">
                        <i class="fa fa-search position-absolute text-muted" style="left: 12px; top: 12px;"></i>
                        <input type="text" id="ts-modal-search" class="form-control" placeholder="Search tables..." style="padding-left: 34px; height: 40px; border-radius: 8px;">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" id="ts-select-custom-only">Select Custom/Local Only</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ts-select-all">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="ts-deselect-all">Clear All</button>
                    </div>
                </div>

                <!-- Scrollable Checkbox List -->
                <div class="p-3 border rounded bg-light" style="max-height: 380px; overflow-y: auto;" id="ts-checkbox-container">
                    <div class="row">
                        <?php foreach ($db_all_tables as $tbl):
                            $is_checked = in_array($tbl, $custom_tables_list);
                            $is_custom_prefix = (strpos($tbl, 'local_') === 0 || strpos($tbl, 'custom_') === 0 || strpos($tbl, 'activemq_') === 0);
                        ?>
                            <div class="col-md-4 col-sm-6 mb-2 ts-table-item"
                                 data-table-name="<?php echo s($tbl); ?>"
                                 data-is-custom="<?php echo $is_custom_prefix ? '1' : '0'; ?>">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox"
                                           class="custom-control-input ts-table-checkbox"
                                           id="ts-chk-<?php echo s($tbl); ?>"
                                           value="<?php echo s($tbl); ?>"
                                           <?php echo $is_checked ? 'checked' : ''; ?>>
                                    <label class="custom-control-label font-size-13 text-truncate w-100 <?php echo $is_custom_prefix ? 'font-weight-bold text-primary' : 'text-dark'; ?>"
                                           for="ts-chk-<?php echo s($tbl); ?>"
                                           title="<?php echo s($tbl); ?>">
                                        <?php echo s($tbl); ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-3 px-4 d-flex justify-content-between">
                <span class="text-muted small" id="ts-selected-count-badge">
                    Selected: <strong><?php echo count($custom_tables_list); ?></strong> of <?php echo count($db_all_tables); ?> tables
                </span>
                <div>
                    <button type="button" class="btn btn-outline-secondary px-4" id="ts-modal-btn-cancel" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary px-4 font-weight-bold shadow-sm" id="ts-modal-btn-save">
                        <i class="fa fa-save mr-1"></i> Save Selected Tables
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
echo $OUTPUT->footer();
