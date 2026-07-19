<?php
/**
 * Page to list questions filtered by category in local_questionbank plugin using templates.
 *
 * @package   local_questionbank
 * @copyright 2026 Rosmin Babu
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$categoryid = optional_param('categoryid', 0, PARAM_INT);

$context = context_system::instance();
require_login();

$url = new moodle_url('/local/questionbank/index.php', array('categoryid' => $categoryid));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('viewquestions', 'local_questionbank'));
$PAGE->set_heading(get_string('pluginname', 'local_questionbank'));

global $DB;

$templatecontext = new stdClass();
$templatecontext->addurl = (new moodle_url('/local/questionbank/add.php'))->out(false);

if ($categoryid <= 0) {
    $templatecontext->hascategory = false;
} else {
    $templatecontext->hascategory = true;
    $templatecontext->categoryid = $categoryid;
    $templatecontext->questionslistheading = get_string('questionslist', 'local_questionbank', $categoryid);
    $templatecontext->backurl = (new moodle_url('/local/questionbank/index.php'))->out(false);

    // Query core question bank tables.
    $questions = $DB->get_records_select(
        'question',
        "category = :category AND qtype IN ('multichoice', 'truefalse') AND parent = 0",
        array('category' => $categoryid),
        'id DESC'
    );

    if (empty($questions)) {
        $templatecontext->hasquestions = false;
    } else {
        $templatecontext->hasquestions = true;
        $templatecontext->questions = array();

        foreach ($questions as $question) {
            $qdata = new stdClass();
            $qdata->id = $question->id;
            $qdata->name = $question->name;
            $qdata->typename = ($question->qtype === 'truefalse') ? 'True/False' : 'Multi-Choice';
            $qdata->typeclass = ($question->qtype === 'truefalse') ? 'info' : 'primary';
            
            // Question text (bypass format_text to preserve inline Base64 images).
            $qdata->questiontext = $question->questiontext;

            // Answers list.
            $answers = $DB->get_records('question_answers', array('question' => $question->id), 'id ASC');
            if ($answers) {
                $qdata->hasanswers = true;
                $qdata->answers = array();
                foreach ($answers as $answer) {
                    $adata = new stdClass();
                    $adata->answertext = $answer->answer;
                    $adata->iscorrect = ($answer->fraction > 0);
                    $qdata->answers[] = $adata;
                }
            } else {
                $qdata->hasanswers = false;
            }

            // Action Links.
            $qdata->editurl = (new moodle_url('/local/questionbank/add.php', array('id' => $question->id)))->out(false);
            $qdata->deleteurl = (new moodle_url('/local/questionbank/delete.php', array('id' => $question->id)))->out(false);

            $templatecontext->questions[] = $qdata;
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_questionbank/index', $templatecontext);
echo $OUTPUT->footer();
