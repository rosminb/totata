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
