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
