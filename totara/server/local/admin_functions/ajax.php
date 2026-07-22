<?php
/**
 * AJAX endpoint for local_admin_functions plugin.
 * Returns table rows, pagination, debug status, custom table selections, and detailed SQL error reporting.
 *
 * @package    local_admin_functions
 * @copyright  2026 Rosmin Babu
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Apply admin-only debug mode rules.
local_admin_functions_apply_debug_settings();

// Force user login — superadmin only.
require_login();
if (!is_siteadmin()) {
    echo json_encode(array('success' => false, 'error' => 'Access denied. Superadmin only.'));
    exit;
}
$context = context_system::instance();

header('Content-Type: application/json; charset=utf-8');

$action = optional_param('action', 'fetch_tables', PARAM_ALPHAEXT);

// Helper function to return friendly description for Totara database tables.
function get_table_description_ajax($tablename) {
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

try {
    if ($action === 'toggle_debug') {
        if (!is_siteadmin()) {
            echo json_encode(array('success' => false, 'error' => 'Only administrators can toggle debug mode.'));
            exit;
        }
        $current = local_admin_functions_is_debug_active();
        $new_status = $current ? 0 : 1;
        set_config('debug_mode', $new_status, 'local_admin_functions');

        echo json_encode(array(
            'success' => true,
            'debug' => $new_status,
            'message' => $new_status ? 'Debug Mode Enabled (Admin Only)' : 'Debug Mode Disabled'
        ));
        exit;

    } else if ($action === 'save_custom_tables') {
        if (!is_siteadmin()) {
            echo json_encode(array('success' => false, 'error' => 'Only administrators can select custom tables.'));
            exit;
        }
        $tables_raw = optional_param('tables', '[]', PARAM_RAW);
        $decoded = json_decode($tables_raw, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(stripslashes($tables_raw), true);
        }
        if (!is_array($decoded)) {
            $decoded = array();
        }

        local_admin_functions_save_custom_tables($decoded);

        echo json_encode(array(
            'success' => true,
            'message' => 'Custom table selection saved successfully.',
            'count' => count($decoded)
        ));
        exit;

    } else if ($action === 'fetch_tables') {
        $search        = optional_param('search', '', PARAM_TEXT);
        $status_filter = optional_param('status_filter', '', PARAM_ALPHA);
        $created_by    = optional_param('created_by', '', PARAM_ALPHA);
        $scope         = optional_param('scope', 'custom', PARAM_ALPHA);
        $page          = optional_param('page', 1, PARAM_INT);
        $perpage       = 100;

        if ($scope === 'all') {
            $all_tables = $DB->get_tables();
            sort($all_tables);
        } else {
            $all_tables = local_admin_functions_get_custom_tables();
        }

        $filtered_tables = $all_tables;

        if ($search !== '') {
            $filtered_tables = array_filter($filtered_tables, function($t) use ($search) {
                return (stripos($t, $search) !== false);
            });
        }

        if ($status_filter !== '') {
            $filtered_tables = array_filter($filtered_tables, function($t) use ($DB, $status_filter) {
                try {
                    $cnt = $DB->count_records($t);
                    return ($status_filter === 'active') ? ($cnt > 0) : ($cnt === 0);
                } catch (\Exception $e) {
                    return ($status_filter === 'inactive');
                }
            });
        }

        $total_tables = count($filtered_tables);
        $totalpages = max(1, (int) ceil($total_tables / $perpage));
        if ($page < 1) $page = 1;
        else if ($page > $totalpages) $page = $totalpages;

        $offset = ($page - 1) * $perpage;
        $tables_slice = array_slice($filtered_tables, $offset, $perpage);
        $start_index = ($total_tables > 0) ? ($offset + 1) : 0;
        $end_index = min($offset + count($tables_slice), $total_tables);

        ob_start();
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
                
                $desc = get_table_description_ajax($tbl);
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
        $rows_html = ob_get_clean();

        ob_start();
        if ($total_tables > 0) {
            $baseurl = new moodle_url('/local/admin_functions/index.php', array(
                'tab' => 'tables', 
                'search' => $search, 
                'status_filter' => $status_filter, 
                'created_by' => $created_by,
                'scope' => $scope
            ));
            echo $OUTPUT->paging_bar($total_tables, $page, $perpage, $baseurl);
        }
        $pagination_html = ob_get_clean();

        $summary_text = ($total_tables > 0) ? "Showing {$start_index} to {$end_index} of " . number_format($total_tables) . " entries" : "Showing 0 entries";

        echo json_encode(array(
            'success' => true,
            'html' => $rows_html,
            'summary' => $summary_text,
            'pagination' => $pagination_html,
            'total' => $total_tables
        ));
        exit;

    } else if ($action === 'fetch_table_data') {
        $tablename = required_param('table', PARAM_ALPHANUMEXT);
        $page      = optional_param('page', 1, PARAM_INT);
        $perpage   = 100;

        $all_tables = $DB->get_tables();
        if (!in_array($tablename, $all_tables)) {
            echo json_encode(array('success' => false, 'error' => 'Invalid table name.'));
            exit;
        }

        $columns_info = $DB->get_columns($tablename);
        $columns = array_keys($columns_info);

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

        $total_records = $DB->count_records_select($tablename, $select, $params);
        $totalpages = max(1, (int) ceil($total_records / $perpage));
        if ($page < 1) $page = 1;
        else if ($page > $totalpages) $page = $totalpages;

        $offset = ($page - 1) * $perpage;
        $records = $DB->get_records_select($tablename, $select, $params, '', '*', $offset, $perpage);

        $start_index = ($total_records > 0) ? ($offset + 1) : 0;
        $end_index = min($offset + count($records), $total_records);

        ob_start();
        if (empty($records)) {
            echo '<tr><td colspan="' . (count($columns) + 1) . '" class="text-center py-5 text-muted font-italic">No records match the active column filter criteria.</td></tr>';
        } else {
            foreach ($records as $r) {
                echo '<tr>';
                foreach ($columns as $col) {
                    $is_mono = ($col === 'id' || is_numeric($r->$col)) ? 'mono-cell' : '';
                    echo '<td class="' . $is_mono . '">';
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
                    echo '</td>';
                }
                $view_rec_url = (new moodle_url('/local/admin_functions/view_record.php', array('table' => $tablename, 'id' => $r->id)))->out(false);
                echo '<td class="text-center">';
                echo '<a href="' . $view_rec_url . '" class="btn-action-icon" title="View Full Record Details"><i class="fa fa-eye"></i></a>';
                echo '</td>';
                echo '</tr>';
            }
        }
        $rows_html = ob_get_clean();

        ob_start();
        if ($total_records > 0) {
            $params_url = array_merge(array('table' => $tablename), $active_filters);
            $baseurl_data = new moodle_url('/local/admin_functions/table_data.php', $params_url);
            echo $OUTPUT->paging_bar($total_records, $page, $perpage, $baseurl_data);
        }
        $pagination_html = ob_get_clean();

        $summary_text = ($total_records > 0) ? "Showing {$start_index} to {$end_index} of " . number_format($total_records) . " entries" : "Showing 0 entries";

        echo json_encode(array(
            'success' => true,
            'html' => $rows_html,
            'summary' => $summary_text,
            'pagination' => $pagination_html,
            'total' => $total_records
        ));
        exit;

    } else if ($action === 'run_sql') {
        $sql = trim(optional_param('sql', '', PARAM_RAW));
        if ($sql === '') {
            echo json_encode(array('success' => false, 'error' => 'SQL query cannot be empty.'));
            exit;
        }

        if (!preg_match('/^(select|show|explain|describe)\b/i', $sql)) {
            echo json_encode(array('success' => false, 'error' => 'Only SELECT, SHOW, EXPLAIN or DESCRIBE queries are allowed.'));
            exit;
        }

        $clean_sql = rtrim($sql, ';');

        try {
            $recordset = $DB->get_recordset_sql($clean_sql, null, 0, 100);
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

            ob_start();
            ?>
            <div class="p-3 bg-dark text-white d-flex justify-content-between align-items-center" style="border-radius: 8px 8px 0 0;">
                <span class="font-weight-bold text-success">
                    <i class="fa fa-check-circle mr-1"></i> Query Executed Successfully
                </span>
                <span class="badge badge-info p-2">
                    Rows returned: <?php echo count($data_rows); ?>
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
            $sql_html = ob_get_clean();

            echo json_encode(array('success' => true, 'html' => $sql_html));
            exit;

        } catch (\Exception $e) {
            $error_details = array(
                'message' => $e->getMessage(),
                'debuginfo' => isset($e->debuginfo) ? $e->debuginfo : '',
                'sql' => $clean_sql,
                'trace' => local_admin_functions_is_debug_active() ? $e->getTraceAsString() : ''
            );

            ob_start();
            ?>
            <div class="card border-danger mb-4 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-danger text-white font-weight-bold d-flex align-items-center">
                    <i class="fa fa-exclamation-triangle mr-2"></i> SQL Execution Error
                </div>
                <div class="card-body bg-light text-dark p-4 font-size-14">
                    <div class="alert alert-danger font-weight-bold mb-3" style="font-size: 14px;">
                        <?php echo s($error_details['message']); ?>
                    </div>

                    <?php if (!empty($error_details['debuginfo'])): ?>
                        <div class="mb-3">
                            <label class="font-weight-bold text-secondary text-uppercase small mb-1">Database Driver Debug Info:</label>
                            <pre class="bg-dark text-warning p-3 rounded font-size-13 m-0"><code><?php echo s($error_details['debuginfo']); ?></code></pre>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="font-weight-bold text-secondary text-uppercase small mb-1">Executed SQL Query:</label>
                        <pre class="bg-dark text-white p-3 rounded font-size-13 m-0"><code><?php echo s($error_details['sql']); ?></code></pre>
                    </div>

                    <?php if (!empty($error_details['trace'])): ?>
                        <div>
                            <label class="font-weight-bold text-secondary text-uppercase small mb-1">Debug Stack Trace (Admin Only):</label>
                            <pre class="bg-secondary text-white p-3 rounded font-size-12 m-0" style="max-height: 200px; overflow-y: auto;"><code><?php echo s($error_details['trace']); ?></code></pre>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $error_html = ob_get_clean();

            echo json_encode(array('success' => false, 'error_html' => $error_html, 'error' => $e->getMessage()));
            exit;
        }

    } else {
        echo json_encode(array('success' => false, 'error' => 'Invalid action.'));
        exit;
    }
} catch (\Exception $e) {
    echo json_encode(array('success' => false, 'error' => 'System error: ' . $e->getMessage()));
    exit;
}
