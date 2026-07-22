<?php
/**
 * Navigation extensions, debug utilities, and custom table configuration for local_admin_functions plugin.
 *
 * @package    local_admin_functions
 * @copyright  2026 Rosmin Babu
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend navigation for the admin_functions plugin.
 *
 * @param global_navigation $nav
 */
function local_admin_functions_extend_navigation(global_navigation $nav) {
    if (is_siteadmin()) {
        $node = navigation_node::create(
            get_string('pluginname', 'local_admin_functions'),
            new moodle_url('/local/admin_functions/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_admin_functions'
        );
        $nav->add_node($node);
    }
}

/**
 * Check if debug mode is active for local_admin_functions.
 *
 * @return bool
 */
function local_admin_functions_is_debug_active() {
    if (!is_siteadmin()) {
        return false;
    }
    return (bool) get_config('local_admin_functions', 'debug_mode');
}

/**
 * Apply admin-only debug mode settings across local_admin_functions pages.
 * Enforces zero error output for non-admin users.
 */
function local_admin_functions_apply_debug_settings() {
    global $CFG;
    if (is_siteadmin()) {
        if (local_admin_functions_is_debug_active()) {
            @error_reporting(E_ALL | E_STRICT);
            @ini_set('display_errors', '1');
            $CFG->debug = (E_ALL | E_STRICT);
            $CFG->debugdisplay = 1;
        } else {
            @error_reporting(0);
            @ini_set('display_errors', '0');
            $CFG->debug = 0;
            $CFG->debugdisplay = 0;
        }
    } else {
        @error_reporting(0);
        @ini_set('display_errors', '0');
        $CFG->debug = 0;
        $CFG->debugdisplay = 0;
    }
}

/**
 * Render a proper, styled "Access Denied" page and exit.
 * Uses Totara's output renderer so it looks like a native Totara page.
 */
function local_admin_functions_access_denied() {
    global $PAGE, $OUTPUT;

    // Suppress all PHP errors for security — never leak info to non-admins.
    @error_reporting(0);
    @ini_set('display_errors', '0');

    $context = context_system::instance();
    $PAGE->set_url(new moodle_url('/local/admin_functions/index.php'));
    $PAGE->set_context($context);
    $PAGE->set_title('Access Denied');
    $PAGE->set_heading('Access Denied');

    // Load our CSS for the error card styling.
    $PAGE->requires->css(new moodle_url('/local/admin_functions/styles.css'));

    echo $OUTPUT->header();
    ?>
    <div class="af-access-denied-wrap">
        <div class="af-access-denied-card">
            <div class="af-access-denied-icon">
                <i class="fa fa-lock"></i>
            </div>
            <h1 class="af-access-denied-title">Access Denied</h1>
            <p class="af-access-denied-subtitle">
                This area is restricted to <strong>Site Administrators</strong> only.
            </p>
            <p class="af-access-denied-desc">
                You do not have the required privileges to access the
                <strong>Admin Functions</strong> module. If you believe this is a mistake,
                please contact your system administrator.
            </p>
        </div>
    </div>
    <style>
    .af-access-denied-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 60vh;
        padding: 2rem 1rem;
    }
    .af-access-denied-card {
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 8px 40px rgba(0,0,0,0.10);
        padding: 3.5rem 3rem;
        max-width: 520px;
        width: 100%;
        text-align: center;
        border-top: 5px solid #ef4444;
    }
    .af-access-denied-icon {
        width: 88px;
        height: 88px;
        border-radius: 50%;
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.75rem;
        box-shadow: 0 4px 16px rgba(239,68,68,0.18);
    }
    .af-access-denied-icon .fa {
        font-size: 2.5rem;
        color: #ef4444;
    }
    .af-access-denied-title {
        font-size: 2rem !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        margin-bottom: 0.75rem !important;
        letter-spacing: -0.5px;
    }
    .af-access-denied-subtitle {
        font-size: 1.05rem;
        color: #475569;
        margin-bottom: 1rem;
        font-weight: 500;
    }
    </style>
    <?php
    echo $OUTPUT->footer();
    exit;
}

/**
 * Get configured custom tables or auto-detect custom/local plugin tables.
 *
 * @return array List of custom table names.
 */
function local_admin_functions_get_custom_tables() {
    global $DB;

    $all_tables = $DB->get_tables();
    sort($all_tables);

    $raw_custom = get_config('local_admin_functions', 'custom_tables');
    if ($raw_custom !== false && $raw_custom !== '' && $raw_custom !== 'null') {
        $decoded = json_decode($raw_custom, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(stripslashes($raw_custom), true);
        }
        if (is_array($decoded) && !empty($decoded)) {
            // Filter to ensure only valid existing tables are returned.
            $valid = array_intersect($decoded, $all_tables);
            if (!empty($valid)) {
                sort($valid);
                return array_values($valid);
            }
        }
    }

    // Default auto-detection: Return tables matching local_*, custom_*, activemq_*, or custom plugin tables.
    $core_prefixes = array('user', 'course', 'enrol', 'context', 'config', 'grade', 'log', 'role', 'cohort', 'files', 'modules', 'question', 'quiz', 'badge', 'task', 'event', 'cache');
    
    $auto_custom = array();
    foreach ($all_tables as $tbl) {
        if (strpos($tbl, 'local_') === 0 || strpos($tbl, 'custom_') === 0 || strpos($tbl, 'activemq_') === 0 || strpos($tbl, 'tool_') === 0 || strpos($tbl, 'admin_') === 0) {
            $auto_custom[] = $tbl;
        } else {
            $is_core = false;
            foreach ($core_prefixes as $cp) {
                if ($tbl === $cp || strpos($tbl, $cp . '_') === 0) {
                    $is_core = true;
                    break;
                }
            }
            if (!$is_core) {
                $auto_custom[] = $tbl;
            }
        }
    }

    sort($auto_custom);
    return array_values($auto_custom);
}

/**
 * Save custom tables selection to plugin configuration.
 *
 * @param array $tables_array List of selected table names.
 * @return bool True on success.
 */
function local_admin_functions_save_custom_tables(array $tables_array) {
    global $DB;

    $all_tables = $DB->get_tables();
    $valid_tables = array_intersect($tables_array, $all_tables);
    sort($valid_tables);
    $valid_tables = array_values($valid_tables);

    return set_config('custom_tables', json_encode($valid_tables), 'local_admin_functions');
}

/**
 * Translate technical event name/class into user-friendly title.
 *
 * @param string $eventname Raw event class (e.g. \core\event\user_loggedin)
 * @param string $target Event target
 * @param string $action Event action
 * @param string $component Event component
 * @return string Friendly description
 */
function local_admin_functions_human_event_name($eventname, $target = '', $action = '', $component = '') {
    static $map = array(
        '\\core\\event\\user_loggedin' => 'User Logged In',
        '\\core\\event\\user_loggedout' => 'User Logged Out',
        '\\core\\event\\user_created' => 'User Account Created',
        '\\core\\event\\user_updated' => 'User Profile Updated',
        '\\core\\event\\user_deleted' => 'User Account Deleted',
        '\\core\\event\\user_password_updated' => 'User Password Changed',
        '\\core\\event\\course_viewed' => 'Course Viewed',
        '\\core\\event\\course_created' => 'Course Created',
        '\\core\\event\\course_updated' => 'Course Updated',
        '\\core\\event\\course_deleted' => 'Course Deleted',
        '\\core\\event\\course_module_viewed' => 'Activity Module Viewed',
        '\\core\\event\\course_module_created' => 'Activity Module Created',
        '\\core\\event\\course_module_updated' => 'Activity Module Updated',
        '\\core\\event\\course_module_completion_updated' => 'Activity Completion Updated',
        '\\core\\event\\user_enrolment_created' => 'User Enrolled in Course',
        '\\core\\event\\user_enrolment_deleted' => 'User Unenrolled from Course',
        '\\core\\event\\role_assigned' => 'Role Assigned',
        '\\core\\event\\role_unassigned' => 'Role Unassigned',
        '\\core\\event\\badge_awarded' => 'Badge Awarded',
        '\\core\\event\\cohort_member_added' => 'Added to Audience/Cohort',
        '\\core\\event\\cohort_member_removed' => 'Removed from Audience/Cohort',
        '\\core\\event\\assessable_submitted' => 'Assessment Submitted',
        '\\core\\event\\dashboard_viewed' => 'Dashboard Viewed',
        '\\core\\event\\grade_item_created' => 'Grade Item Created',
        '\\core\\event\\group_member_added' => 'Added to Group',
        '\\core\\event\\config_log_created' => 'Configuration Changed',
    );

    if (isset($map[$eventname])) {
        return $map[$eventname];
    }

    if (!empty($target) && !empty($action)) {
        $clean_target = str_replace('_', ' ', $target);
        $clean_action = str_replace('_', ' ', $action);
        return ucwords($clean_target . ' ' . $clean_action);
    }

    // Fallback: strip namespace slashes and format cleanly.
    $parts = explode('\\', $eventname);
    $last = end($parts);
    $clean = str_replace('_', ' ', $last);
    return ucwords($clean);
}

/**
 * Get distinct list of components present in logstore_standard_log.
 *
 * @return array Array of component names.
 */
function local_admin_functions_get_log_components() {
    global $DB;
    try {
        $sql = "SELECT DISTINCT component FROM {logstore_standard_log} WHERE component IS NOT NULL AND component != '' ORDER BY component ASC";
        $records = $DB->get_fieldset_sql($sql);
        return $records ? $records : array();
    } catch (\Exception $e) {
        return array('core', 'local_admin_functions');
    }
}

/**
 * Decode log 'other' field safely (handles JSON or PHP serialized format).
 *
 * @param string $other_raw
 * @return array
 */
function local_admin_functions_decode_log_other($other_raw) {
    if (empty($other_raw) || $other_raw === 'N;') {
        return array();
    }
    // Attempt JSON decode first.
    $json = json_decode($other_raw, true);
    if (is_array($json)) {
        return $json;
    }
    // Attempt PHP unserialize cleanly.
    $unserialized = @unserialize($other_raw);
    if (is_array($unserialized)) {
        return $unserialized;
    }
    return array('raw' => (string)$other_raw);
}

/**
 * Export filtered logs to CSV file for download.
 *
 * @param string $search
 * @param string $crud
 * @param string $component
 * @param string $fromdate
 * @param string $todate
 * @param string $userfilter
 */
function local_admin_functions_export_logs_csv($search = '', $crud = '', $component = '', $fromdate = '', $todate = '', $userfilter = '') {
    global $DB;

    // Build SQL query.
    $where = array("1=1");
    $params = array();

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

    if ($component !== '') {
        $where[] = "l.component = :comp";
        $params['comp'] = $component;
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

    $sql = "SELECT l.id, l.timecreated, l.eventname, l.component, l.action, l.target, l.crud, l.ip,
                   u.username, u.firstname, u.lastname, u.email,
                   c.shortname AS coursename
              FROM {logstore_standard_log} l
         LEFT JOIN {user} u ON u.id = l.userid
         LEFT JOIN {course} c ON c.id = l.courseid
             WHERE {$wherestr}
          ORDER BY l.id DESC";

    $filename = "totara_logs_export_" . date('Y-m-d_H-i-s') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Output UTF-8 BOM for Excel compatibility.
    fputs($output, "\xEF\xBB\xBF");

    // CSV Headers.
    fputcsv($output, array(
        'Log ID',
        'Time Created',
        'Event Description',
        'Raw Event Name',
        'Component',
        'CRUD Action',
        'Target',
        'User Full Name',
        'Username',
        'User Email',
        'Course Shortname',
        'IP Address'
    ));

    $recordset = $DB->get_recordset_sql($sql, $params, 0, 5000);
    foreach ($recordset as $r) {
        $fullname = trim($r->firstname . ' ' . $r->lastname);
        if (empty($fullname)) {
            $fullname = $r->username ? $r->username : 'CLI / System';
        }
        $human_event = local_admin_functions_human_event_name($r->eventname, $r->target, $r->action, $r->component);

        fputcsv($output, array(
            $r->id,
            date('Y-m-d H:i:s', $r->timecreated),
            $human_event,
            $r->eventname,
            $r->component,
            strtoupper($r->crud),
            $r->target,
            $fullname,
            $r->username,
            $r->email,
            $r->coursename,
            $r->ip
        ));
    }
    $recordset->close();
    fclose($output);
    exit;
}

