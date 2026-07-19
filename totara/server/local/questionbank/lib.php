<?php
/**
 * Library functions for the local_questionbank plugin.
 *
 * @package   local_questionbank
 * @copyright 2026 Rosmin Babu
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the global navigation structure to add the "Question Bank" menu node.
 *
 * @param global_navigation $navigation The global navigation object.
 */
function local_questionbank_extend_navigation(global_navigation $navigation) {
    // Add custom menu link to the main navigation sidebar.
    $node = navigation_node::create(
        get_string('pluginname', 'local_questionbank'),
        new moodle_url('/local/questionbank/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_questionbank',
        new pix_icon('i/questions', '')
    );
    $navigation->add_node($node);
}

/**
 * Serves files for the local_questionbank plugin.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool|void
 */
function local_questionbank_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    require_login();

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_questionbank', $filearea, $itemid, $filepath, $filename);

    if (!$file) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
