<?php
/**
 * Page to delete a question in local_questionbank plugin using core tables and cascading file deletions.
 *
 * @package   local_questionbank
 * @copyright 2026 Rosmin Babu
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$context = context_system::instance();
require_login();

// Restrict access to admins or managers.
require_capability('moodle/site:config', $context);

$id = required_param('id', PARAM_INT);

global $DB;

// Retrieve the question first to know the category ID for redirecting.
$question = $DB->get_record('question', array('id' => $id), '*', MUST_EXIST);

// 1. Delete associated options based on qtype.
if ($question->qtype === 'truefalse') {
    $DB->delete_records('question_truefalse', array('question' => $id));
} else if ($question->qtype === 'multichoice') {
    $DB->delete_records('qtype_multichoice_options', array('questionid' => $id));
}

// 2. Fetch answer records, delete their files, and drop the answer entries.
$fs = get_file_storage();
$answers = $DB->get_records('question_answers', array('question' => $id));
if ($answers) {
    foreach ($answers as $answer) {
        $fs->delete_area_files($context->id, 'local_questionbank', 'questionbank', $answer->id);
    }
}
$DB->delete_records('question_answers', array('question' => $id));

// 3. Delete the question files and the question itself.
$fs->delete_area_files($context->id, 'local_questionbank', 'questionbank', $id);
$DB->delete_records('question', array('id' => $id));

// Redirect back to the index page filtered by the deleted question's category.
redirect(new moodle_url('/local/questionbank/index.php', array('categoryid' => $question->category)));
