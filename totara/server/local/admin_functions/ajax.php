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

    } else if ($action === 'export_logs') {
        $search     = optional_param('search', '', PARAM_TEXT);
        $crud       = optional_param('crud', '', PARAM_ALPHA);
        $comp_flt   = optional_param('component', '', PARAM_ALPHAEXT);
        $fromdate   = optional_param('fromdate', '', PARAM_TEXT);
        $todate     = optional_param('todate', '', PARAM_TEXT);
        $userfilter = optional_param('userfilter', '', PARAM_TEXT);

        local_admin_functions_export_logs_csv($search, $crud, $comp_flt, $fromdate, $todate, $userfilter);
        exit;

    } else if ($action === 'fetch_logs') {
        $search      = optional_param('search', '', PARAM_TEXT);
        $crud        = optional_param('crud', '', PARAM_ALPHA);
        $comp_flt    = optional_param('component', '', PARAM_ALPHAEXT);
        $fromdate    = optional_param('fromdate', '', PARAM_TEXT);
        $todate      = optional_param('todate', '', PARAM_TEXT);
        $userfilter  = optional_param('userfilter', '', PARAM_TEXT);
        $eventfilter = optional_param('eventfilter', '', PARAM_RAW);
        $viewmode    = optional_param('viewmode', 'list', PARAM_ALPHA);
        $page        = optional_param('page', 1, PARAM_INT);
        $perpage     = 100;

        $where = array("1=1");
        $params = array();

        if ($eventfilter !== '') {
            $where[] = "l.eventname = :evtfilter";
            $params['evtfilter'] = $eventfilter;
        }

        if ($search !== '') {
            $where[] = "(l.eventname " . $DB->sql_like('l.eventname', ':s1', false) . "
                        OR l.component " . $DB->sql_like('l.component', ':s2', false) . "
                        OR l.target " . $DB->sql_like('l.target', ':s3', false) . "
                        OR l.ip " . $DB->sql_like('l.ip', ':s4', false) . "
                        OR u.firstname " . $DB->sql_like('u.firstname', ':s5', false) . "
                        OR u.lastname " . $DB->sql_like('u.lastname', ':s6', false) . "
                        OR u.username " . $DB->sql_like('u.username', ':s7', false) . ")";
            $params['s1'] = '%' . $search . '%';
            $params['s2'] = '%' . $search . '%';
            $params['s3'] = '%' . $search . '%';
            $params['s4'] = '%' . $search . '%';
            $params['s5'] = '%' . $search . '%';
            $params['s6'] = '%' . $search . '%';
            $params['s7'] = '%' . $search . '%';
        }

        if ($crud !== '') {
            $where[] = "l.crud = :crud";
            $params['crud'] = strtolower($crud);
        }

        if ($comp_flt !== '') {
            $where[] = "l.component = :comp";
            $params['comp'] = $comp_flt;
        }

        if ($fromdate !== '') {
            $ts_from = strtotime($fromdate);
            if ($ts_from !== false) {
                $where[] = "l.timecreated >= :tsfrom";
                $params['tsfrom'] = $ts_from;
            }
        }

        if ($todate !== '') {
            $ts_to = strtotime($todate . ' 23:59:59');
            if ($ts_to !== false) {
                $where[] = "l.timecreated <= :tsto";
                $params['tsto'] = $ts_to;
            }
        }

        if ($userfilter !== '') {
            if (is_numeric($userfilter)) {
                $where[] = "l.userid = :ufid";
                $params['ufid'] = (int)$userfilter;
            } else {
                $where[] = "(u.username " . $DB->sql_like('u.username', ':uf1', false) . " OR u.firstname " . $DB->sql_like('u.firstname', ':uf2', false) . " OR u.lastname " . $DB->sql_like('u.lastname', ':uf3', false) . ")";
                $params['uf1'] = '%' . $userfilter . '%';
                $params['uf2'] = '%' . $userfilter . '%';
                $params['uf3'] = '%' . $userfilter . '%';
            }
        }

        $wherestr = implode(' AND ', $where);

        if ($viewmode === 'group') {
            // Grouped View: Count by eventname strictly. First column MUST be unique id for Moodle get_records_sql.
            $groupsql = "SELECT MIN(l.id) AS id,
                                l.eventname,
                                MAX(l.component) AS component,
                                MAX(l.crud) AS crud,
                                MAX(l.target) AS target,
                                COUNT(*) AS total_count,
                                MAX(l.timecreated) AS latest_time
                           FROM {logstore_standard_log} l
                      LEFT JOIN {user} u ON u.id = l.userid
                          WHERE {$wherestr}
                       GROUP BY l.eventname
                       ORDER BY total_count DESC, latest_time DESC";

            try {
                $groups_all = array_values($DB->get_records_sql($groupsql, $params));
            } catch (\Exception $e) {
                echo json_encode(array('success' => false, 'error' => 'Group query failed: ' . $e->getMessage()));
                exit;
            }

            $total_groups = count($groups_all);
            $totalpages = max(1, (int) ceil($total_groups / $perpage));
            if ($page < 1) $page = 1;
            else if ($page > $totalpages) $page = $totalpages;

            $offset = ($page - 1) * $perpage;
            $groups = array_slice($groups_all, $offset, $perpage);

            $start_index = ($total_groups > 0) ? ($offset + 1) : 0;
            $end_index = min($offset + count($groups), $total_groups);

            ob_start();
            if (empty($groups)) {
                echo '<tr><td colspan="7" class="text-center py-5 text-muted font-italic">No log events match your active search filters.</td></tr>';
            } else {
                $idx = $offset + 1;
                foreach ($groups as $g) {
                    $human_title = local_admin_functions_human_event_name($g->eventname, $g->target, '', $g->component);
                    $crud_letter = strtoupper($g->crud);
                    $badge_class = 'badge-crud-' . strtolower($g->crud);
                    
                    echo '<tr>';
                    echo '<td class="font-weight-bold text-secondary mono-cell">#' . $idx++ . '</td>';
                    echo '<td>';
                    echo '<div class="font-weight-bold text-dark" style="font-size: 13.5px;">' . s($human_title) . '</div>';
                    echo '<div class="small text-muted font-monospace">' . s($g->eventname) . '</div>';
                    echo '</td>';
                    echo '<td><span class="badge badge-light border text-secondary font-weight-normal">' . s($g->component) . '</span></td>';
                    echo '<td class="text-center"><span class="' . $badge_class . '">' . s($crud_letter) . '</span></td>';
                    echo '<td><span class="badge badge-primary font-weight-bold p-2" style="font-size: 13px;">' . number_format($g->total_count) . ' events</span></td>';
                    echo '<td class="text-secondary small">' . date('d M Y, h:i:s A', $g->latest_time) . '</td>';
                    echo '<td class="text-center">';
                    echo '<button type="button" class="btn-action-icon btn-view-group-events" data-event-name="' . s($g->eventname) . '" data-component="' . s($g->component) . '" title="View List of These Events"><i class="fa fa-eye"></i></button>';
                    echo '</td>';
                    echo '</tr>';
                }
            }
            $rows_html = ob_get_clean();

            ob_start();
            if ($total_groups > 0) {
                $baseurl = new moodle_url('/local/admin_functions/index.php', array(
                    'tab' => 'logs',
                    'search' => $search,
                    'crud' => $crud,
                    'component' => $comp_flt,
                    'fromdate' => $fromdate,
                    'todate' => $todate,
                    'userfilter' => $userfilter,
                    'viewmode' => 'group'
                ));
                echo $OUTPUT->paging_bar($total_groups, $page, $perpage, $baseurl);
            }
            $pagination_html = ob_get_clean();

            $summary_text = ($total_groups > 0) ? "Showing {$start_index} to {$end_index} of " . number_format($total_groups) . " event categories" : "Showing 0 event categories";

            echo json_encode(array(
                'success' => true,
                'html' => $rows_html,
                'summary' => $summary_text,
                'pagination' => $pagination_html,
                'total' => $total_groups
            ));
            exit;

        } else {
            // Flat List View.
            $countsql = "SELECT COUNT(*)
                           FROM {logstore_standard_log} l
                      LEFT JOIN {user} u ON u.id = l.userid
                          WHERE {$wherestr}";

            $total_records = $DB->count_records_sql($countsql, $params);
            $totalpages = max(1, (int) ceil($total_records / $perpage));
            if ($page < 1) $page = 1;
            else if ($page > $totalpages) $page = $totalpages;

            $offset = ($page - 1) * $perpage;

            $listsql = "SELECT l.id, l.timecreated, l.eventname, l.component, l.action, l.target, l.crud, l.ip, l.userid, l.courseid,
                               u.username, u.firstname, u.lastname, u.email,
                               c.shortname AS coursename
                          FROM {logstore_standard_log} l
                     LEFT JOIN {user} u ON u.id = l.userid
                     LEFT JOIN {course} c ON c.id = l.courseid
                         WHERE {$wherestr}
                      ORDER BY l.id DESC";

            $records = $DB->get_records_sql($listsql, $params, $offset, $perpage);

            $start_index = ($total_records > 0) ? ($offset + 1) : 0;
            $end_index = min($offset + count($records), $total_records);

            ob_start();
            if (empty($records)) {
                echo '<tr><td colspan="8" class="text-center py-5 text-muted font-italic">No system logs match your active filter criteria.</td></tr>';
            } else {
                foreach ($records as $r) {
                    $fullname = trim($r->firstname . ' ' . $r->lastname);
                    if (empty($fullname)) {
                        $fullname = $r->username ? $r->username : 'CLI / System';
                    }
                    $initials = strtoupper(substr($fullname, 0, 1));
                    $human_event = local_admin_functions_human_event_name($r->eventname, $r->target, $r->action, $r->component);
                    $crud_letter = strtoupper($r->crud);
                    $badge_class = 'badge-crud-' . strtolower($r->crud);

                    echo '<tr>';
                    echo '<td class="font-weight-semibold text-muted mono-cell">' . $r->id . '</td>';
                    echo '<td class="text-secondary small white-space-nowrap">' . date('d M Y, h:i:s A', $r->timecreated) . '</td>';
                    echo '<td>';
                    echo '<div class="font-weight-bold text-dark" style="font-size: 13.5px;">' . s($human_event) . '</div>';
                    echo '<div class="text-muted small text-truncate" style="max-width: 280px;" title="' . s($r->eventname) . '">' . s($r->eventname) . '</div>';
                    echo '</td>';
                    echo '<td><span class="badge badge-light border text-secondary font-weight-normal">' . s($r->component) . '</span></td>';
                    echo '<td class="text-center"><span class="' . $badge_class . '">' . s($crud_letter) . '</span></td>';
                    echo '<td>';
                    echo '<div class="d-flex align-items-center gap-2">';
                    echo '<div class="af-user-avatar">' . s($initials) . '</div>';
                    echo '<div>';
                    echo '<div class="font-weight-bold text-dark font-size-13">' . s($fullname) . '</div>';
                    if ($r->email) {
                        echo '<div class="text-muted small">' . s($r->email) . '</div>';
                    }
                    echo '</div>';
                    echo '</div>';
                    echo '</td>';
                    echo '<td class="text-secondary mono-cell small">' . s($r->ip ? $r->ip : 'CLI') . '</td>';
                    echo '<td class="text-center">';
                    echo '<button type="button" class="btn-action-icon btn-view-log-detail" data-log-id="' . $r->id . '" title="View Log Details"><i class="fa fa-eye"></i></button>';
                    echo '</td>';
                    echo '</tr>';
                }
            }
            $rows_html = ob_get_clean();

            ob_start();
            if ($total_records > 0) {
                $baseurl = new moodle_url('/local/admin_functions/index.php', array(
                    'tab' => 'logs',
                    'search' => $search,
                    'crud' => $crud,
                    'component' => $comp_flt,
                    'fromdate' => $fromdate,
                    'todate' => $todate,
                    'userfilter' => $userfilter,
                    'viewmode' => $viewmode
                ));
                echo $OUTPUT->paging_bar($total_records, $page, $perpage, $baseurl);
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
        }

    } else if ($action === 'fetch_log_detail') {
        $logid = required_param('logid', PARAM_INT);

        $sql = "SELECT l.*,
                       u.username, u.firstname, u.lastname, u.email,
                       ru.username AS realusername, ru.firstname AS realfirstname, ru.lastname AS reallastname,
                       c.fullname AS coursename, c.shortname AS courseshortname
                  FROM {logstore_standard_log} l
             LEFT JOIN {user} u ON u.id = l.userid
             LEFT JOIN {user} ru ON ru.id = l.realuserid
             LEFT JOIN {course} c ON c.id = l.courseid
                 WHERE l.id = :id";

        $log = $DB->get_record_sql($sql, array('id' => $logid));

        if (!$log) {
            echo json_encode(array('success' => false, 'error' => 'Log record not found.'));
            exit;
        }

        $user_fullname = trim($log->firstname . ' ' . $log->lastname);
        if (empty($user_fullname)) {
            $user_fullname = $log->username ? $log->username : 'System / CLI';
        }

        $real_user_fullname = '';
        if ($log->realuserid && $log->realuserid != $log->userid) {
            $real_user_fullname = trim($log->realfirstname . ' ' . $log->reallastname);
            if (empty($real_user_fullname)) {
                $real_user_fullname = $log->realusername;
            }
        }

        $human_event = local_admin_functions_human_event_name($log->eventname, $log->target, $log->action, $log->component);
        $other_decoded = local_admin_functions_decode_log_other($log->other);

        ob_start();
        ?>
        <table class="inspector-details-table-clean">
            <tr>
                <td class="inspector-key">Log Record ID</td>
                <td class="inspector-val font-weight-bold text-primary mono-cell">#<?php echo $log->id; ?></td>
            </tr>
            <tr>
                <td class="inspector-key">Event Title</td>
                <td class="inspector-val font-weight-bold text-dark" style="font-size: 15px;"><?php echo s($human_event); ?></td>
            </tr>
            <tr>
                <td class="inspector-key">Raw Event Name</td>
                <td class="inspector-val text-muted mono-cell small"><?php echo s($log->eventname); ?></td>
            </tr>
            <tr>
                <td class="inspector-key">Component</td>
                <td class="inspector-val"><span class="badge badge-light border text-dark font-weight-bold"><?php echo s($log->component); ?></span></td>
            </tr>
            <tr>
                <td class="inspector-key">CRUD Action</td>
                <td class="inspector-val">
                    <span class="badge-crud-<?php echo strtolower($log->crud); ?>">
                        <?php echo strtoupper($log->crud); ?>
                    </span>
                    <span class="text-secondary small ml-2">(Action: <?php echo s($log->action); ?>, Target: <?php echo s($log->target); ?>)</span>
                </td>
            </tr>
            <tr>
                <td class="inspector-key">Performed By User</td>
                <td class="inspector-val font-weight-bold text-dark">
                    <?php echo s($user_fullname); ?>
                    <?php if ($log->email): ?>
                        <span class="text-muted font-weight-normal small ml-1">(<?php echo s($log->email); ?>)</span>
                    <?php endif; ?>
                    <span class="badge badge-secondary ml-2">ID: <?php echo $log->userid; ?></span>
                </td>
            </tr>
            <?php if (!empty($real_user_fullname)): ?>
                <tr>
                    <td class="inspector-key text-danger font-weight-bold">Impersonated By (Real User)</td>
                    <td class="inspector-val text-danger font-weight-bold">
                        <?php echo s($real_user_fullname); ?> (ID: <?php echo $log->realuserid; ?>)
                    </td>
                </tr>
            <?php endif; ?>
            <?php if ($log->coursename): ?>
                <tr>
                    <td class="inspector-key">Course Context</td>
                    <td class="inspector-val font-weight-bold text-dark">
                        <?php echo s($log->coursename); ?> <span class="text-muted small">(<?php echo s($log->courseshortname); ?>)</span>
                    </td>
                </tr>
            <?php endif; ?>
            <tr>
                <td class="inspector-key">IP Address / Origin</td>
                <td class="inspector-val mono-cell">
                    <?php echo s($log->ip ? $log->ip : 'CLI'); ?>
                    <?php if ($log->origin): ?>
                        <span class="badge badge-outline-secondary ml-2"><?php echo s($log->origin); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="inspector-key">Exact Timestamp</td>
                <td class="inspector-val text-dark font-weight-semibold">
                    <?php echo date('d F Y, H:i:s P', $log->timecreated); ?>
                    <span class="text-muted small ml-1">(Unix: <?php echo $log->timecreated; ?>)</span>
                </td>
            </tr>
            <?php if ($log->objecttable): ?>
                <tr>
                    <td class="inspector-key">Target DB Table &amp; ID</td>
                    <td class="inspector-val mono-cell">
                        Table: <code>{<?php echo s($log->objecttable); ?>}</code> | Object ID: <strong><?php echo $log->objectid; ?></strong>
                    </td>
                </tr>
            <?php endif; ?>
            <tr>
                <td class="inspector-key">Context ID</td>
                <td class="inspector-val mono-cell"><?php echo $log->contextid; ?></td>
            </tr>
            <tr>
                <td class="inspector-key">Extra Event Data (Other)</td>
                <td class="inspector-val">
                    <?php if (empty($other_decoded)): ?>
                        <span class="text-muted font-italic">No extra parameters recorded.</span>
                    <?php else: ?>
                        <pre class="bg-dark text-warning p-3 rounded font-size-12 m-0" style="max-height: 220px; overflow-y: auto;"><code><?php echo s(json_encode($other_decoded, JSON_PRETTY_PRINT)); ?></code></pre>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
        $detail_html = ob_get_clean();

        echo json_encode(array('success' => true, 'html' => $detail_html));
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
