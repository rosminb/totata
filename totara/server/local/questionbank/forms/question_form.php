<?php
/**
 * Form class for adding questions in local_questionbank plugin using core tables.
 *
 * @package   local_questionbank
 * @copyright 2026 Rosmin Babu
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class local_questionbank_question_form extends moodleform {
    protected function definition() {
        global $CFG, $PAGE;
        $mform = $this->_form;

        $PAGE->requires->jquery();
        $PAGE->requires->css(new moodle_url('/local/questionbank/summernote/summernote-lite.min.css'));

        // Load Summernote and inject sync/toggle script.
        $summernoteassets = '
        require(["jquery"], function($) {
            // Convert image to WebP - max 1200px on longest side, 80% quality
            var convertToWebP = function(base64Str, callback) {
                var maxSize = 1200;
                var quality = 0.8;

                var img = new Image();
                img.src = base64Str;
                img.onload = function() {
                    var width = img.width;
                    var height = img.height;

                    // Scale down proportionally if either dimension exceeds 1200px
                    if (width > maxSize || height > maxSize) {
                        if (width > height) {
                            height = Math.round((height * maxSize) / width);
                            width = maxSize;
                        } else {
                            width = Math.round((width * maxSize) / height);
                            height = maxSize;
                        }
                    }

                    var canvas = document.createElement("canvas");
                    canvas.width = width;
                    canvas.height = height;

                    var ctx = canvas.getContext("2d");
                    ctx.drawImage(img, 0, 0, width, height);

                    // Convert to WebP at 80% quality
                    var webpBase64 = canvas.toDataURL("image/webp", quality);

                    // Fallback: if browser does not support WebP output, keep original
                    if (webpBase64.indexOf("data:image/webp") === 0) {
                        callback(webpBase64);
                    } else {
                        callback(base64Str);
                    }
                };
            };

            var initSummernote = function() {
                var textareas = $("textarea.summernote-editor:not(.summernote-initialized)");
                textareas.each(function() {
                    var textarea = this;
                    $(textarea).addClass("summernote-initialized");
                    var height = $(textarea).attr("data-editor-height") || 150;

                    $(textarea).summernote({
                        height: parseInt(height),
                        toolbar: [
                            ["style", ["style"]],
                            ["font", ["bold", "italic", "underline", "clear"]],
                            ["para", ["ul", "ol", "paragraph"]],
                            ["table", ["table"]],
                            ["insert", ["link", "picture"]],
                            ["view", ["codeview"]]
                        ],
                        callbacks: {
                            onImageUpload: function(files) {
                                var editor = $(textarea).next();
                                var imgs = editor.find(".note-editable img");
                                
                                // 1. Enforce 1 image limit per editor.
                                if (imgs.length + files.length > 1) {
                                    alert("Only one image is allowed in this editor.");
                                    return;
                                }

                                if (files.length > 0) {
                                    var file = files[0];
                                    var reader = new FileReader();
                                    reader.onload = function(e) {
                                        convertToWebP(e.target.result, function(webpBase64) {
                                            $(textarea).summernote("insertImage", webpBase64, function($image) {
                                                $image.addClass("summernote-webp");
                                            });
                                        });
                                    };
                                    reader.readAsDataURL(file);
                                }
                            },
                            onChange: function(contents, $editable) {
                                var imgs = $editable.find("img");
                                var changed = false;

                                // 1. Enforce 1 image limit per editor.
                                if (imgs.length > 1) {
                                    alert("Only one image is allowed in this editor. Extra images have been removed.");
                                    for (var j = 1; j < imgs.length; j++) {
                                        imgs[j].remove();
                                    }
                                    imgs = $editable.find("img");
                                    changed = true;
                                }

                                // 2. Convert base64 images to WebP if pasted/dropped.
                                imgs.each(function() {
                                    var img = this;
                                    var src = img.getAttribute("src");
                                    if (src && src.indexOf("data:image/") === 0 && !img.classList.contains("summernote-webp")) {
                                        img.classList.add("summernote-webp");
                                        convertToWebP(src, function(webpBase64) {
                                            img.setAttribute("src", webpBase64);
                                            $(textarea).val($(textarea).summernote("code"));
                                        });
                                    }
                                });

                                // Sync content back to original textarea
                                $(textarea).val($(textarea).summernote("code"));
                            }
                        }
                    });
                });

                var qtypeSelect = document.querySelector("select[name=\'qtype\']");
                if (qtypeSelect) {
                    qtypeSelect.removeEventListener("change", toggleFields);
                    qtypeSelect.addEventListener("change", toggleFields);
                    toggleFields();
                }
            };

            // Dynamically show/hide fields based on qtype
            var toggleFields = function() {
                var qtypeSelect = document.querySelector("select[name=\'qtype\']");
                if (!qtypeSelect) return;
                var qtype = qtypeSelect.value;

                for (var i = 1; i <= 6; i++) {
                    var hdr = document.getElementById("fheader_id_answerhdr_" + i);
                    var checkboxDiv = document.getElementById("fitem_id_iscorrect_" + i);
                    var tArea = document.getElementById("id_answer_" + i);
                    var itemDiv = document.getElementById("fitem_id_answer_" + i);

                    if (qtype === "truefalse") {
                        if (i > 2) {
                            if (hdr) hdr.style.display = "none";
                            if (itemDiv) itemDiv.style.display = "none";
                            if (checkboxDiv) checkboxDiv.style.display = "none";
                        } else {
                            if (hdr) hdr.style.display = "";
                            if (checkboxDiv) checkboxDiv.style.display = "";
                            
                            // Hide the answer editor wrapper since text is static True/False
                            if (itemDiv) itemDiv.style.display = "none";

                            var val = (i === 1) ? "<p>True</p>" : "<p>False</p>";
                            if (tArea) {
                                tArea.value = val;
                                if ($(tArea).hasClass("summernote-initialized")) {
                                    $(tArea).summernote("code", val);
                                }
                            }
                        }
                    } else {
                        // multichoice
                        if (hdr) hdr.style.display = "";
                        if (itemDiv) itemDiv.style.display = "";
                        if (checkboxDiv) checkboxDiv.style.display = "";
                    }
                }
            };

            var loadSummernote = function(callback) {
                if (typeof $.fn.summernote !== "undefined") {
                    callback();
                    return;
                }

                // Ensure jQuery is set globally for Summernote non-AMD registration
                window.jQuery = window.$ = $;

                var oldDefine = window.define;
                window.define = undefined;

                var script = document.createElement("script");
                script.type = "text/javascript";
                script.src = M.cfg.wwwroot + "/local/questionbank/summernote/summernote-lite.min.js";
                script.onload = function() {
                    window.define = oldDefine;
                    callback();
                };
                script.onerror = function() {
                    window.define = oldDefine;
                    console.error("Failed to load Summernote JS from: " + script.src);
                };
                document.head.appendChild(script);
            };

            $(document).ready(function() {
                loadSummernote(function() {
                    initSummernote();
                });
            });

            // True/False checkbox exclusivity helper
            document.addEventListener("change", function(e) {
                if (e.target && e.target.type === "checkbox" && e.target.name.indexOf("iscorrect_") === 0) {
                    var qtypeSelect = document.querySelector("select[name=\'qtype\']");
                    if (qtypeSelect && qtypeSelect.value === "truefalse") {
                        if (e.target.checked) {
                            var myName = e.target.name;
                            document.querySelectorAll("input[type=\'checkbox\'][name^=\'iscorrect_\']").forEach(function(cb) {
                                if (cb.name !== myName) {
                                    cb.checked = false;
                                }
                            });
                        }
                    }
                }
            });
        });';
        $PAGE->requires->js_init_code($summernoteassets);

        // Header.
        $mform->addElement('header', 'general', get_string('addquestion', 'local_questionbank'));

        // Question ID (hidden element for edit mode).
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Category ID (int).
        $mform->addElement('text', 'categoryid', get_string('categoryid', 'local_questionbank'));
        $mform->setType('categoryid', PARAM_INT);
        $mform->addRule('categoryid', null, 'required', null, 'client');

        // Question Name (varchar).
        $mform->addElement('text', 'name', 'Question Name');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Question Type (select).
        $mform->addElement('select', 'qtype', 'Question Type', array(
            'multichoice' => 'Multiple Choice',
            'truefalse' => 'True/False'
        ));
        $mform->setType('qtype', PARAM_ALPHA);
        $mform->addRule('qtype', null, 'required', null, 'client');

        // Question Text (textarea).
        $mform->addElement('textarea', 'questiontext', get_string('questiontext', 'local_questionbank'), array(
            'id' => 'id_questiontext',
            'class' => 'summernote-editor',
            'data-editor-height' => '220'
        ));
        $mform->setType('questiontext', PARAM_RAW);
        $mform->addRule('questiontext', null, 'required', null, 'client');

        // Answers (Minimum 2, Maximum 6).
        for ($i = 1; $i <= 6; $i++) {
            $mform->addElement('header', 'answerhdr_' . $i, get_string('answerheading', 'local_questionbank', $i));

            // Answer text (textarea).
            $mform->addElement('textarea', 'answer_' . $i, get_string('answertext', 'local_questionbank'), array(
                'id' => 'id_answer_' . $i,
                'class' => 'summernote-editor',
                'data-editor-height' => '130'
            ));
            $mform->setType('answer_' . $i, PARAM_RAW);
            if ($i <= 2) {
                $mform->addRule('answer_' . $i, null, 'required', null, 'client');
            }

            // Is correct.
            $mform->addElement('checkbox', 'iscorrect_' . $i, get_string('iscorrect', 'local_questionbank'));
            $mform->setType('iscorrect_' . $i, PARAM_BOOL);
        }

        // Action buttons.
        $this->add_action_buttons(true, get_string('savequestion', 'local_questionbank'));
    }
}
