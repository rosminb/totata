<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_admin_functions',
        get_string('pluginname', 'local_admin_functions'),
        new moodle_url('/local/admin_functions/index.php')
    ));
}
