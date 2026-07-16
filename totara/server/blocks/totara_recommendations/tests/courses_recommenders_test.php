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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Cody Finegan <cody.finegan@totaralearning.com>
 * @package block_totara_recommendations
 */

use block_totara_recommendations\repository\recommendations_repository;
use core_phpunit\testcase;
use ml_recommender\recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * Testing the behaviour of the courses block with the remote recommenders service.
 *
 * @group block_totara_recommendations
 */
class block_totara_recommendations_courses_recommenders_testcase extends testcase {
    /**
     * Assert that only visible, self-enrollment enabled & not-enrolled courses are seen through courses
     * recommendations.
     */
    public function test_courses_block(): void {
        global $DB;
        list($courses, $users) = $this->generate_data();

        // User 1 should not see any recommendations
        $records = recommendations_repository::get_recommended_courses(8, $users[1]->id);
        self::assertEmpty($records);

        // User 2 should see Course 1 recommended
        $records = recommendations_repository::get_recommended_courses(8, $users[2]->id);
        self::assertNotEmpty($records);
        self::assertCount(1, $records);

        $record = current($records);
        self::assertEquals($courses[1]->id, $record->item_id);

        // Now unenrol user 1 from course 1, then see if it's recommended
        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $courses[1]->id, 'enrol' => 'manual']);
        $plugin->unenrol_user($instance, $users[1]->id);

        // User 1 should see Course 1 recommended
        $records = recommendations_repository::get_recommended_courses(8, $users[1]->id);
        self::assertNotEmpty($records);
        self::assertCount(1, $records);

        $record = current($records);
        self::assertEquals($courses[1]->id, $record->item_id);

        // Now make visible course 2 and see if it's recommended to user 2
        $courses[2]->visible = 1;
        $courses[2]->visibleold = 1;
        $DB->update_record('course', $courses[2]);

        $records = recommendations_repository::get_recommended_courses(8, $users[2]->id);
        self::assertNotEmpty($records);
        self::assertCount(2, $records);
    }

    /**
     * Assert that courses are filtered based on audience visibility rules
     */
    public function test_courses_with_audience_visibility(): void {
        global $CFG, $DB;

        // Enable audience visibility rules
        $CFG->audiencevisibility = 1;

        // Courses will default to audience visibility of COHORT_VISIBLE_ALL
        list($courses, $users) = $this->generate_data();

        // User 1 should not see any recommendations
        $records = recommendations_repository::get_recommended_courses(8, $users[1]->id);
        self::assertEmpty($records);

        // User 2 should see course 1 & course 2 recommended (audience visibility took over)
        $records = recommendations_repository::get_recommended_courses(8, $users[2]->id);
        self::assertNotEmpty($records);
        self::assertCount(2, $records);

        self::assertEqualsCanonicalizing([$courses[1]->id, $courses[2]->id], array_column($records, 'item_id'));

        // Set courses to be audience visibility = Nobody
        $DB->execute("UPDATE {course} SET audiencevisible = ?", [COHORT_VISIBLE_NOUSERS]);

        // User 1 should not see any recommendations
        $records = recommendations_repository::get_recommended_courses(8, $users[1]->id);
        self::assertEmpty($records);

        // User 2 should not see any recommendations
        $records = recommendations_repository::get_recommended_courses(8, $users[2]->id);
        self::assertEmpty($records);

        // Set courses to be audience visibility = Enrolled
        $DB->execute("UPDATE {course} SET audiencevisible = ?", [COHORT_VISIBLE_ENROLLED]);

        // This is a trick - only enrolled users should see the course, but the act of enrolling should hide it
        // therefore we should not see anything for either user
        $records = recommendations_repository::get_recommended_courses(8, $users[1]->id);
        self::assertEmpty($records);
        $records = recommendations_repository::get_recommended_courses(8, $users[2]->id);
        self::assertEmpty($records);

        // Set courses to be audience visibility = Audience
        $DB->execute("UPDATE {course} SET audiencevisible = ?", [COHORT_VISIBLE_AUDIENCE]);

        // Create a new audience
        $audience = $this->getDataGenerator()->create_cohort();

        // Attach the audience to each course
        foreach ($courses as $course) {
            totara_cohort_add_association(
                $audience->id,
                $course->id,
                COHORT_ASSN_ITEMTYPE_COURSE,
                COHORT_ASSN_VALUE_PERMITTED
            );
        }

        // Confirm the courses are not visible
        $records = recommendations_repository::get_recommended_courses(8, $users[1]->id);
        self::assertEmpty($records);
        $records = recommendations_repository::get_recommended_courses(8, $users[2]->id);
        self::assertEmpty($records);

        // Enrol user 2 in the audience
        cohort_add_member($audience->id, $users[2]->id);

        // User 1 should not see any recommendations
        $records = recommendations_repository::get_recommended_courses(8, $users[1]->id);
        self::assertEmpty($records);

        // User 2 should see course 1 & course 2 recommended (audience visibility took over)
        $records = recommendations_repository::get_recommended_courses(8, $users[2]->id);
        self::assertNotEmpty($records);
        self::assertCount(2, $records);

        self::assertEqualsCanonicalizing([$courses[1]->id, $courses[2]->id], array_column($records, 'item_id'));
    }

    /**
     * Pre-test step to include the local library for enrollment
     */
    protected function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/locallib.php');
    }

    /**
     * Cleanup
     */
    protected function tearDown(): void {
        $this->set_recommended_data(null);
    }

    /**
     * @param array|null $data
     */
    protected function set_recommended_data(?array $data): void {
        $mock_helper = null;
        if (null !== $data) {
            $mock_helper = $this->createMock(recommendations::class);
            $mock_helper
                ->method('get_user_recommendations')
                ->willReturn($data);
        }

        $reflection = new ReflectionProperty(recommendations_repository::class, 'recommendations_helper');
        $reflection->setAccessible(true);
        $reflection->setValue($mock_helper);
        $reflection->setAccessible(false);
    }

    /**
     * Generate the courses & users & test data
     *
     * @return array
     */
    private function generate_data(): array {
        $gen = $this->getDataGenerator();

        $courses = [];
        $courses[1] = $gen->create_course(['fullname' => 'self-enrol + recommended + visible']);
        $courses[2] = $gen->create_course(['fullname' => 'self-enrol + recommended + not visible', 'visible' => 0]);
        $courses[3] = $gen->create_course(['fullname' => 'self-enrol + not recommended + visible']);
        $courses[4] = $gen->create_course(['fullname' => 'self-enrol + not recommended + not visible', 'visible' => 0]);
        $courses[5] = $gen->create_course(['fullname' => 'no self-enrol + recommended + visible']);
        $courses[6] = $gen->create_course(['fullname' => 'no self-enrol + recommended + not visible']);
        $courses[7] = $gen->create_course(['fullname' => 'no self-enrol + not recommended + visible']);
        $courses[8] = $gen->create_course(['fullname' => 'no self-enrol + not recommended + not visible']);

        $users = [];
        $users[1] = $gen->create_user(['username' => 'user1']);
        $users[2] = $gen->create_user(['username' => 'user2']);

        // Enable self-enrollments for Course 1 - 4
        foreach ([1, 2, 3, 4] as $course_key) {
            $this->enable_self_enrollment($courses[$course_key]->id);
        }

        // Recommend course 1, 2, 5 & 6
        $course_recommendations = [];
        foreach ([1, 2, 5, 6] as $course_key) {
            $course_recommendations[] = $courses[$course_key]->id;
        }
        $this->set_recommended_data($course_recommendations);

        // User 1 is enrolled, user 2 is not
        foreach ($courses as $course) {
            $gen->enrol_user($users[1]->id, $course->id);
        }

        return [$courses, $users];
    }

    /**
     * Enable the self-enrollment plugin for the specified course
     *
     * @param int $course_id
     */
    private function enable_self_enrollment(int $course_id): void {
        global $DB;
        $enrol = $DB->get_record('enrol', array('courseid' => $course_id, 'enrol' => 'self'), '*', MUST_EXIST);
        $enrol->status = ENROL_INSTANCE_ENABLED;
        $DB->update_record('enrol', $enrol);
    }
}