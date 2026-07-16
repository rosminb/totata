<?php
/**
 * This file is part of Totara Learn
 *
 * Copyright (C) 2021 onwards Totara Learning Solutions LTD
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
 * @author Riana Rossouw <riana.rossouw@totaralearning.com>
 * @package core_course
 */

use container_course\course as course_container;
use core_course\totara_notification\resolver\course_due_date_resolver;
use core_phpunit\testcase;
use totara_core\extended_context;

global $CFG;
require_once($CFG->libdir . '/dml/array_recordset.php');

defined('MOODLE_INTERNAL') || die();

/**
 * @group totara_notification
 */
class totara_notification_course_due_date_resolver_test extends testcase {

    public function test_resolver(): void {
        global $CFG, $DB;

        $generator = self::getDataGenerator();

        $now = time();

        // Create a base user.
        $user1 = $generator->create_user(['lastname' => 'User1 last name']);
        $user2 = $generator->create_user(['lastname' => 'User2 last name']);
        $user3 = $generator->create_user(['lastname' => 'User3 last name']);

        // Create courses.
        $course1 = $generator->create_course([
            'fullname' => 'Test course with completion',
            'enablecompletion' => COMPLETION_ENABLED,
        ]);
        $course2 = $generator->create_course([
            'fullname' => 'Test course with no completion',
            'enablecompletion' => COMPLETION_DISABLED,
        ]);

        // Directly create course_completion records with specific status and due date values
        $to_insert = [
            [
                'course' => $course1->id,
                'userid' => $user1->id,
                'timeenrolled' => $now,
                'timestarted' => $now,
                'reaggregate' => 0,
                'status' => COMPLETION_STATUS_INPROGRESS,
                'duedate' => $now + DAYSECS * 3,
            ],
            [
                'course' => $course1->id,
                'userid' => $user2->id,
                'timeenrolled' => $now,
                'timestarted' => $now,
                'reaggregate' => 0,
                'status' => COMPLETION_STATUS_NOTYETSTARTED,
                'duedate' => $now + DAYSECS * 6,
            ],
            [
                'course' => $course1->id,
                'userid' => $user3->id,
                'timeenrolled' => $now,
                'timestarted' => $now,
                'reaggregate' => 0,
                'status' => COMPLETION_STATUS_COMPLETE,
                'duedate' => $now + DAYSECS * 6,
            ],
            [
                'course' => $course2->id,
                'userid' => $user1->id,
                'timeenrolled' => $now,
                'timestarted' => $now,
                'reaggregate' => 0,
                'status' => COMPLETION_STATUS_INPROGRESS,
                'duedate' => $now + DAYSECS * 3,
            ],
            [
                'course' => $course2->id,
                'userid' => $user2->id,
                'timeenrolled' => $now,
                'timestarted' => $now,
                'reaggregate' => 0,
                'status' => COMPLETION_STATUS_NOTYETSTARTED,
                'duedate' => $now + DAYSECS * 6,
            ],
            [
                'course' => $course2->id,
                'userid' => $user3->id,
                'timeenrolled' => $now,
                'timestarted' => $now,
                'reaggregate' => 0,
                'status' => COMPLETION_STATUS_COMPLETEVIARPL,
                'duedate' => $now + DAYSECS * 6,
            ],
        ];

        $DB->insert_records('course_completions', $to_insert);

        $resolver_class_name = course_due_date_resolver::class;

        // Empty result for min_time after time due.
        self::assert_scheduled_events($resolver_class_name, $now + DAYSECS * 7, $now + DAYSECS * 9, []);
        // Empty result for max_time before time due.
        self::assert_scheduled_events($resolver_class_name, $now - DAYSECS * 3, $now + DAYSECS * 2, []);
        // Empty result for max_time = time due.
        self::assert_scheduled_events($resolver_class_name, $now + DAYSECS * 2, $now + DAYSECS * 3, []);

        // Result expected for min_time = time due.
        self::assert_scheduled_events($resolver_class_name, $now + DAYSECS * 3, $now + DAYSECS * 4, [
            ['course_id' => $course1->id, 'user_id' => $user1->id, 'duedate' => $now + DAYSECS * 3],
        ]);
        // Result expected for min_time < time due.
        self::assert_scheduled_events($resolver_class_name, $now + DAYSECS * 2, $now + DAYSECS * 4, [
            ['course_id' => $course1->id, 'user_id' => $user1->id, 'duedate' => $now + DAYSECS * 3],
        ]);

        // Include both due dates
        self::assert_scheduled_events($resolver_class_name, $now + DAYSECS * 2, $now + DAYSECS * 7, [
            ['course_id' => $course1->id, 'user_id' => $user1->id, 'time_due' => $now + DAYSECS * 3],
            ['course_id' => $course1->id, 'user_id' => $user2->id, 'time_due' => $now + DAYSECS * 6],
        ]);

        // Only second due date
        self::assert_scheduled_events($resolver_class_name, $now + DAYSECS * 5, $now + DAYSECS * 7, [
            ['course_id' => $course1->id, 'user_id' => $user2->id, 'time_due' => $now + DAYSECS * 6],
        ]);

        // Now disable completion on site level and re-evaluate the last 3 tests
        $CFG->enablecompletion = 0;
        self::assert_scheduled_events($resolver_class_name, $now + DAYSECS * 2, $now + DAYSECS * 4, []);
        self::assert_scheduled_events($resolver_class_name, $now + DAYSECS * 2, $now + DAYSECS * 7, []);
        self::assert_scheduled_events($resolver_class_name, $now + DAYSECS * 5, $now + DAYSECS * 7, []);
    }

    public function test_warnings(): void {
        $course_completion_disabled = self::getDataGenerator()->create_course(['enablecompletion' => 0]);
        $course_completion_enabled = self::getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course_completion_enabled_with_duedate = self::getDataGenerator()->create_course([
            'enablecompletion' => 1,
            'duedate_op' => course_container::DUEDATEOPERATOR_FIXED,
            'duedate' => strtotime('+7 days'),
        ]);

        $system_context = extended_context::make_system();
        $course_context_disabled = extended_context::make_with_context(
            context_course::instance($course_completion_disabled->id)
        );
        $course_context_enabled = extended_context::make_with_context(
            context_course::instance($course_completion_enabled->id)
        );
        $course_context_enabled_with_duedate = extended_context::make_with_context(
            context_course::instance($course_completion_enabled_with_duedate->id)
        );
        $extended_course_context = extended_context::make_with_context(
            context_course::instance($course_completion_disabled->id),
            'test_component',
            'test_area',
            123
        );

        self::assertEmpty(course_due_date_resolver::get_warnings($system_context));
        self::assertNotEmpty(course_due_date_resolver::get_warnings($course_context_disabled));
        self::assertNotEmpty(course_due_date_resolver::get_warnings($course_context_enabled));
        self::assertEmpty(course_due_date_resolver::get_warnings($course_context_enabled_with_duedate));
        self::assertEmpty(course_due_date_resolver::get_warnings($extended_course_context));
    }
}
