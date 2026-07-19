<?php
/**
 * Redirect legacy view.php calls to view_record.php.
 *
 * @package    local_admin_functions
 * @copyright  2026 Rosmin Babu
 */

require_once(__DIR__ . '/../../config.php');

$tablename = optional_param('table', '', PARAM_ALPHANUMEXT);
$id        = optional_param('id', 0, PARAM_INT);

$redirect_url = new moodle_url('/local/admin_functions/view_record.php', array('table' => $tablename, 'id' => $id));
redirect($redirect_url);
