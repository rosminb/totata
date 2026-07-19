<?php
/**
 * Page to add/edit questions in local_questionbank plugin using templates and native file storage.
 *
 * @package   local_questionbank
 * @copyright 2026 Rosmin Babu
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/forms/question_form.php');

$context = context_system::instance();
require_login();

// Restrict access to admins or managers.
require_capability('moodle/site:config', $context);

$id = optional_param('id', 0, PARAM_INT);

$url = new moodle_url('/local/questionbank/add.php');
if ($id) {
    $url->param('id', $id);
}
$PAGE->set_url($url);
$PAGE->set_context($context);

if ($id) {
    $PAGE->set_title(get_string('pluginname', 'local_questionbank') . ' - Edit');
} else {
    $PAGE->set_title(get_string('addquestion', 'local_questionbank'));
}
$PAGE->set_heading(get_string('pluginname', 'local_questionbank'));

$mform = new local_questionbank_question_form();

// Initialise default values.
$defaultdata = new stdClass();

if ($id) {
    global $DB;
    $question = $DB->get_record('question', array('id' => $id), '*', MUST_EXIST);
    $defaultdata->id = $id;
    $defaultdata->name = $question->name;
    $defaultdata->qtype = $question->qtype;
    $defaultdata->categoryid = $question->category;
    $defaultdata->questiontext = $question->questiontext;

    $answers = $DB->get_records('question_answers', array('question' => $id), 'id ASC');
    $i = 1;
    foreach ($answers as $answer) {
        if ($i <= 6) {
            $defaultdata->{"answer_$i"} = $answer->answer;
            $defaultdata->{"iscorrect_$i"} = ($answer->fraction > 0) ? 1 : 0;
            $i++;
        }
    }
}

$mform->set_data($defaultdata);

/**
 * Parses HTML content, extracts Base64 inline images, saves them to Moodle file storage,
 * and rewrites the image src attributes to use standard Moodle pluginfile URLs.
 *
 * @param string $html The HTML content to process.
 * @param int $itemid The question ID or answer ID.
 * @param context $context The context.
 * @return string The processed HTML with rewritten URLs.
 */
function local_questionbank_save_draft_files($html, $itemid, $context) {
    global $USER, $CFG;
    require_once($CFG->libdir . '/filelib.php');

    if (empty($html)) {
        return '';
    }

    $fs = get_file_storage();

    // Regex to match base64 img tags
    // Matches: src="data:image/png;base64,iVBOR..."
    $pattern = '/src="data:image\/([a-zA-Z0-9\+\-\.]+);base64,([a-zA-Z0-9\/\+=\s]+)"/i';

    $html = preg_replace_callback($pattern, function($matches) use ($fs, $context, $itemid, $USER) {
        $extension = $matches[1];
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        
        $base64data = str_replace(array("\r", "\n", ' '), '', $matches[2]);
        $binarydata = base64_decode($base64data);

        if (!$binarydata) {
            return $matches[0];
        }

        $filename = 'image_' . uniqid() . '.' . $extension;

        $filerecord = array(
            'contextid' => $context->id,
            'component' => 'local_questionbank',
            'filearea' => 'questionbank',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $USER->id
        );

        try {
            $fs->create_file_from_string($filerecord, $binarydata);
            $url = moodle_url::make_pluginfile_url($context->id, 'local_questionbank', 'questionbank', $itemid, '/', $filename);
            return 'src="' . $url->out(false) . '"';
        } catch (Exception $e) {
            return $matches[0];
        }
    }, $html);

    return $html;
}

/**
 * Scans HTML content and deletes any files in local_questionbank filearea that are no longer referenced.
 *
 * @param string $html The HTML content to check.
 * @param int $itemid The question ID or answer ID.
 * @param context $context The context.
 */
function local_questionbank_cleanup_orphan_files($html, $itemid, $context) {
    if (empty($html)) {
        return;
    }
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'local_questionbank', 'questionbank', $itemid, 'filename ASC', false);
    if (empty($files)) {
        return;
    }
    foreach ($files as $file) {
        $filename = $file->get_filename();
        if (strpos($html, $filename) === false) {
            $file->delete();
        }
    }
}

if ($mform->is_cancelled()) {
    $backurl = new moodle_url('/local/questionbank/index.php');
    if ($id && isset($question)) {
        $backurl->param('categoryid', $question->category);
    }
    redirect($backurl);
} else if ($data = $mform->get_data()) {
    global $DB, $USER;

    // 1. Resolve category ID.
    $category = $DB->get_record('question_categories', array('id' => $data->categoryid));
    if (!$category) {
        // Automatically create a category in the system context.
        $cat = new stdClass();
        $cat->name = 'Category ' . $data->categoryid;
        $cat->contextid = $context->id;
        $cat->info = 'Auto-created by Question Bank Plugin';
        $cat->infoformat = FORMAT_HTML;
        $cat->stamp = make_unique_id_code();
        $cat->parent = 0;
        $cat->sortorder = 999;
        $data->categoryid = $DB->insert_record('question_categories', $cat);
    }

    // 2. Prepare core question record.
    $question = new stdClass();
    $question->category = $data->categoryid;
    $question->parent = 0;
    $question->name = $data->name;
    $question->questiontext = $data->questiontext;
    $question->questiontextformat = FORMAT_HTML;
    $question->generalfeedback = '';
    $question->generalfeedbackformat = FORMAT_HTML;
    $question->defaultmark = '1.0000000';
    $question->penalty = '0.3333333';
    $question->qtype = $data->qtype;
    $question->length = 1;
    $question->hidden = 0;
    $question->timemodified = time();
    $question->modifiedby = $USER->id;

    if (!empty($data->id)) {
        global $DB;
        $existing = $DB->get_record('question', array('id' => $data->id), '*', MUST_EXIST);
        $question->id = $data->id;
        $question->stamp = $existing->stamp;
        $question->version = $existing->version;
        $question->timecreated = $existing->timecreated;
        $question->createdby = $existing->createdby;
        $DB->update_record('question', $question);
        $questionid = $data->id;
    } else {
        $question->stamp = make_unique_id_code();
        $question->version = md5(uniqid());
        $question->timecreated = time();
        $question->createdby = $USER->id;
        $questionid = $DB->insert_record('question', $question);
    }

    // Process and save question text files (extract Base64 and write to storage).
    $questiontext = local_questionbank_save_draft_files($data->questiontext, $questionid, $context);
    // Cleanup any orphan files for the question.
    local_questionbank_cleanup_orphan_files($questiontext, $questionid, $context);
    // Update the question text in the database with the processed text.
    $DB->set_field('question', 'questiontext', $questiontext, array('id' => $questionid));

    // 3. Clear old answer configurations and their files.
    if (!empty($data->id)) {
        $oldanswers = $DB->get_records('question_answers', array('question' => $data->id));
        $fs = get_file_storage();
        foreach ($oldanswers as $oldans) {
            $fs->delete_area_files($context->id, 'local_questionbank', 'questionbank', $oldans->id);
        }
    }
    $DB->delete_records('question_answers', array('question' => $questionid));

    // 4. Save answers based on qtype.
    if ($data->qtype === 'truefalse') {
        // Insert True answer.
        $anstrue = new stdClass();
        $anstrue->question = $questionid;
        $anstrue->answer = '<p>True</p>';
        $anstrue->answerformat = FORMAT_HTML;
        $anstrue->fraction = !empty($data->iscorrect_1) ? '1.0000000' : '0.0000000';
        $anstrue->feedback = '';
        $anstrue->feedbackformat = FORMAT_HTML;
        $trueid = $DB->insert_record('question_answers', $anstrue);

        // Insert False answer.
        $ansfalse = new stdClass();
        $ansfalse->question = $questionid;
        $ansfalse->answer = '<p>False</p>';
        $ansfalse->answerformat = FORMAT_HTML;
        $ansfalse->fraction = !empty($data->iscorrect_2) ? '1.0000000' : '0.0000000';
        $ansfalse->feedback = '';
        $ansfalse->feedbackformat = FORMAT_HTML;
        $falseid = $DB->insert_record('question_answers', $ansfalse);

        // Save truefalse options.
        $DB->delete_records('question_truefalse', array('question' => $questionid));
        $tfopts = new stdClass();
        $tfopts->question = $questionid;
        $tfopts->trueanswer = $trueid;
        $tfopts->falseanswer = $falseid;
        $DB->insert_record('question_truefalse', $tfopts);

    } else {
        // multichoice
        // Calculate count of correct answers.
        $correctcount = 0;
        for ($i = 1; $i <= 6; $i++) {
            $iscorrectfield = "iscorrect_$i";
            if (!empty($data->$iscorrectfield)) {
                $correctcount++;
            }
        }

        $correctfraction = ($correctcount > 0) ? (1.0 / $correctcount) : 0.0;

        for ($i = 1; $i <= 6; $i++) {
            $answerfield = "answer_$i";
            if (isset($data->$answerfield)) {
                $rawcontent = trim($data->$answerfield);
                $cleancontent = str_replace(array('<p>', '</p>', '<br>', '<br />', ' '), '', $rawcontent);
                if ($cleancontent !== '') {
                    $ans = new stdClass();
                    $ans->question = $questionid;
                    $ans->answer = $data->$answerfield;
                    $ans->answerformat = FORMAT_HTML;

                    $iscorrectfield = "iscorrect_$i";
                    $ans->fraction = !empty($data->$iscorrectfield) ? number_format($correctfraction, 7, '.', '') : '0.0000000';
                    $ans->feedback = '';
                    $ans->feedbackformat = FORMAT_HTML;

                    $answerid = $DB->insert_record('question_answers', $ans);

                    // Process answer text files.
                    $answertext = local_questionbank_save_draft_files($data->$answerfield, $answerid, $context);
                    // Cleanup any orphan files for the answer.
                    local_questionbank_cleanup_orphan_files($answertext, $answerid, $context);
                    // Update answer in database.
                    $DB->set_field('question_answers', 'answer', $answertext, array('id' => $answerid));
                }
            }
        }

        // Save multichoice options.
        $DB->delete_records('qtype_multichoice_options', array('questionid' => $questionid));
        $mcopts = new stdClass();
        $mcopts->questionid = $questionid;
        $mcopts->layout = 0;
        $mcopts->single = ($correctcount === 1) ? 1 : 0;
        $mcopts->shuffleanswers = 1;
        $mcopts->correctfeedback = '';
        $mcopts->correctfeedbackformat = FORMAT_HTML;
        $mcopts->partiallycorrectfeedback = '';
        $mcopts->partiallycorrectfeedbackformat = FORMAT_HTML;
        $mcopts->incorrectfeedback = '';
        $mcopts->incorrectfeedbackformat = FORMAT_HTML;
        $mcopts->answernumbering = 'abc';
        $mcopts->shownumcorrect = 0;
        $DB->insert_record('qtype_multichoice_options', $mcopts);
    }

    // Redirect to index page filtered by category.
    redirect(new moodle_url('/local/questionbank/index.php', array('categoryid' => $data->categoryid)));
}

// Render form inside mustache template using output buffering.
ob_start();
$mform->display();
$formhtml = ob_get_clean();

$templatecontext = new stdClass();
$templatecontext->formhtml = $formhtml;

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_questionbank/add', $templatecontext);
echo $OUTPUT->footer();
