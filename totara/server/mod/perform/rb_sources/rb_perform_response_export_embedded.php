<?php
/*
 * This file is part of Totara Perform
 *
 * Copyright (C) 2020 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author: Simon Coggins <simon.coggins@totaralearning.com>
 * @package: mod_perform
 */

use mod_perform\util;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/perform/rb_sources/rb_source_perform_response.php');

class rb_perform_response_export_embedded extends rb_base_embedded {

    /**
     * @var string {report_builder}.defaultsortcolumn
     */
    public $defaultsortcolumn = '';

    public function __construct($data) {
        $this->url = '/mod/perform/reporting/performance/export.php';
        $this->source = 'perform_response';
        $this->shortname = 'perform_response_export';
        $this->fullname = get_string('embedded_perform_response_export', 'mod_perform');
        $this->columns = $this->define_columns();
        $this->filters = $this->define_filters();
        $this->defaultsortcolumn = 'response_default_sort';

        // Pass any restrictions applied in $data through as embedded params.
        if (isset($data['element_id'])) {
            $this->embeddedparams['element_id'] = $data['element_id'];
        }
        if (isset($data['activity_id'])) {
            $this->embeddedparams['activity_id'] = $data['activity_id'];
        }
        if (isset($data['subject_user_id'])) {
            $this->embeddedparams['subject_user_id'] = $data['subject_user_id'];
        }
        if (isset($data['subject_instance_id'])) {
            $this->embeddedparams['subject_instance_id'] = $data['subject_instance_id'];
        }
        if (isset($data['element_identifier'])) {
            $this->embeddedparams['element_identifier'] = $data['element_identifier'];
        }

        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_ALL;

        $this->contentsettings = [
            'user_visibility' => [
                'enable' => 1,
            ]
        ];

        parent::__construct();
    }

    /**
     * Define the default columns for this report.
     *
     * @return array
     */
    protected function define_columns() {
        return \rb_source_perform_response::get_default_columns();
    }

    /**
     * Define the default filters for this report.
     *
     * @return array
     */
    protected function define_filters() {
        return \rb_source_perform_response::get_default_filters();
    }

    /**
     * Clarify if current embedded report support global report restrictions.
     * Override to true for reports that support GRR
     *
     * @return boolean
     */
    public function embedded_global_restrictions_supported() {
        return true;
    }

    /**
     * Can searches be saved?
     *
     * @return bool
     */
    public static function is_search_saving_allowed(): bool {
        return false;
    }

    /**
     * We explicitly don't allow this report to be cloned.
     *
     * @return bool
     */
    public static function is_cloning_allowed(): bool {
        return false;
    }

    /**
     * Message to display indicating why this report can't be cloned.
     *
     * @return string
     */
    public function get_cloning_not_allowed_message(): string {
        return get_string('embedded_perform_response_export_cloning_not_allowed', 'mod_perform');
    }

    /**
     * Check if the user is capable of accessing this report.
     *
     * @param int $reportfor userid of the user that this report is being generated for
     * @param reportbuilder $report the report object - can use get_param_value to get params
     * @return boolean true if the user can access this report
     */
    public function is_capable($reportfor, $report): bool {
        return util::can_potentially_report_on_subjects($reportfor);
    }
}
