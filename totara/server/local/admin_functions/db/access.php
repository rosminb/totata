<?php
/**
 * Capabilities defined for local_admin_functions plugin.
 * Compatible with traditional PHP 5.3+ array syntax.
 *
 * @package    local_admin_functions
 * @copyright  2026 Rosmin Babu
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    'local/admin_functions:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
        ),
    ),
);
