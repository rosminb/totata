<?php
/**
 * Navigation extensions and debug utilities for local_admin_functions plugin.
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
    $context = context_system::instance();
    if (is_siteadmin() || has_capability('moodle/site:config', $context) || has_capability('local/admin_functions:view', $context)) {
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
