<?php
/**
 * Admin settings page for the local_questionbank plugin.
 *
 * @package   local_questionbank
 * @copyright 2026 Rosmin Babu
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_questionbank',
        get_string('pluginname', 'local_questionbank'),
        new moodle_url('/local/questionbank/index.php')
    ));
}
