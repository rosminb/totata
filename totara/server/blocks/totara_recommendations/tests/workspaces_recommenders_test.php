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
use container_workspace\member\member;
use core_phpunit\testcase;
use ml_recommender\recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * Testing the behaviour of the courses block with the remote recommenders service.
 *
 * @group block_totara_recommendations
 */
class block_totara_recommendations_workspaces_recommenders_testcase extends testcase {
    /**
     * Assert that only visible, public & not-joined workspace are seen through workspace
     * recommendations.
     */
    public function test_workspaces_block(): void {
        global $CFG;
        list($workspaces, $users) = $this->generate_data();

        // Disable audience visibility rules
        $CFG->audiencevisibility = 0;

        // User 1 should not see any recommendations
        $records = recommendations_repository::get_recommended_workspaces(6, $users[1]->id);
        self::assertEmpty($records);

        // User 2 should see workspace 1 recommended
        $records = recommendations_repository::get_recommended_workspaces(6, $users[2]->id);
        self::assertNotEmpty($records);
        self::assertCount(1, $records);

        $record = current($records);
        self::assertEquals($workspaces[1]->id, $record->item_id);

        // Now unenrol user 1 from workspace 1, then see if it's recommended
        $member = member::from_user($users[1]->id, $workspaces[1]->id);
        $this->setAdminUser();
        $member->delete();

        // User 1 should see workspace 1 recommended
        $records = recommendations_repository::get_recommended_workspaces(6, $users[1]->id);
        self::assertNotEmpty($records);
        self::assertCount(1, $records);

        $record = current($records);
        self::assertEquals($workspaces[1]->id, $record->item_id);
    }

    /**
     * Assert that workspaces are filtered based on audience visibility rules
     */
    public function test_courses_with_audience_visibility(): void {
        global $CFG;
        list($workspaces, $users) = $this->generate_data();

        // Enable audience visibility rules
        $CFG->audiencevisibility = 1;

        // User 1 should not see any recommendations
        $records = recommendations_repository::get_recommended_workspaces(6, $users[1]->id);
        self::assertEmpty($records);

        // User 2 should see workspace 1 recommended
        $records = recommendations_repository::get_recommended_workspaces(6, $users[2]->id);
        self::assertNotEmpty($records);
        self::assertCount(1, $records);

        $record = current($records);
        self::assertEquals($workspaces[1]->id, $record->item_id);

        // Now unenrol user 1 from workspace 1, then see if it's recommended
        $member = member::from_user($users[1]->id, $workspaces[1]->id);
        $this->setAdminUser();
        $member->delete();

        // User 1 should see workspace 1 recommended
        $records = recommendations_repository::get_recommended_workspaces(6, $users[1]->id);
        self::assertNotEmpty($records);
        self::assertCount(1, $records);

        $record = current($records);
        self::assertEquals($workspaces[1]->id, $record->item_id);
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
        /** @var \container_workspace\testing\generator $wgen */
        $wgen = $this->getDataGenerator()->get_plugin_generator('container_workspace');

        $this->setAdminUser();

        $workspaces = [];
        $workspaces[1] = $wgen->create_workspace('recommended + public');
        $workspaces[2] = $wgen->create_private_workspace('recommended + private');
        $workspaces[3] = $wgen->create_hidden_workspace('recommended + hidden');
        $workspaces[4] = $wgen->create_workspace('not recommended + public');
        $workspaces[5] = $wgen->create_private_workspace('not recommended + private');
        $workspaces[6] = $wgen->create_hidden_workspace('not recommended + hidden');

        $users = [];
        $users[1] = $gen->create_user(['username' => 'user1']);
        $users[2] = $gen->create_user(['username' => 'user2']);

        // Recommend workspace 1, 2 & 3
        $workspace_recommendations = [];
        foreach ($users as $user) {
            foreach ([1, 2, 3] as $workspace_key) {
                $workspace_recommendations[] = $workspaces[$workspace_key]->id;
            }
        }
        $this->set_recommended_data($workspace_recommendations);

        // User 1 is a member, user 2 is not
        foreach ($workspaces as $workspace) {
            $wgen->add_member($workspace, $users[1]->id);
        }

        return [$workspaces, $users];
    }
}