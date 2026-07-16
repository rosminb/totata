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
use engage_article\totara_engage\resource\article;
use ml_recommender\recommendations;
use totara_engage\access\access;
use totara_engage\timeview\time_view;

defined('MOODLE_INTERNAL') || die();

/**
 * Testing the behaviour of the microlearning block with the remote recommenders service.
 *
 * @group block_totara_recommendations
 */
class block_totara_recommendations_microlearning_recommenders_testcase extends testcase {
    /**
     * Assert that only public & time to read <= 5 min resources are seen through microlearning
     * recommendations.
     */
    public function test_microlearning_block(): void {
        list($resources, $users) = $this->generate_data();

        // User 1 should see resource 1 recommended
        $records = recommendations_repository::get_recommended_micro_learning(12, $users[1]->id);
        self::assertNotEmpty($records);
        $record = current($records);
        self::assertEquals($resources[1]->get_id(), $record->item_id);

        // Now change resource 7 to <5
        /** @var article $resource_7 */
        $resource_7 = $resources[7];
        $resource_7->update(['timeview' => time_view::LESS_THAN_FIVE]);

        // User 1 should see resource 1 & 7 recommended
        $records = recommendations_repository::get_recommended_micro_learning(12, $users[1]->id);
        self::assertNotEmpty($records);
        self::assertCount(2, $records);

        // Assert the ids are correct
        self::assertEqualsCanonicalizing(
            [$resources[1]->get_id(), $resources[7]->get_id()],
            array_column($records, 'item_id')
        );
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
        /** @var \engage_article\testing\generator $egen */
        $egen = $this->getDataGenerator()->get_plugin_generator('engage_article');

        $this->setAdminUser();

        $resources = [];
        $resources[1] = $egen->create_public_article([
            'name' => 'recommended + public + <5',
            'timeview' => time_view::LESS_THAN_FIVE
        ]);
        $resources[2] = $egen->create_article([
            'name' => 'recommended + private + <5',
            'timeview' => time_view::LESS_THAN_FIVE,
            'access' => access::PRIVATE
        ]);
        $resources[3] = $egen->create_restricted_article([
            'name' => 'recommended + restricted + <5',
            'timeview' => time_view::LESS_THAN_FIVE
        ]);
        $resources[4] = $egen->create_public_article([
            'name' => 'not recommended + public + <5',
            'timeview' => time_view::LESS_THAN_FIVE
        ]);
        $resources[5] = $egen->create_article([
            'name' => 'not recommended + private + <5',
            'timeview' => time_view::LESS_THAN_FIVE,
            'access' => access::PRIVATE
        ]);
        $resources[6] = $egen->create_restricted_article([
            'name' => 'not recommended + restricted + <5',
            'timeview' => time_view::LESS_THAN_FIVE
        ]);
        $resources[7] = $egen->create_public_article([
            'name' => 'recommended + public + >5',
            'timeview' => time_view::FIVE_TO_TEN
        ]);
        $resources[8] = $egen->create_article([
            'name' => 'recommended + private + >5',
            'timeview' => time_view::FIVE_TO_TEN,
            'access' => access::PRIVATE
        ]);
        $resources[9] = $egen->create_restricted_article([
            'name' => 'recommended + restricted + >5',
            'timeview' => time_view::FIVE_TO_TEN
        ]);
        $resources[10] = $egen->create_public_article([
            'name' => 'not recommended + public + >5',
            'timeview' => time_view::FIVE_TO_TEN
        ]);
        $resources[11] = $egen->create_article([
            'name' => 'not recommended + private + >5',
            'timeview' => time_view::FIVE_TO_TEN,
            'access' => access::PRIVATE
        ]);
        $resources[12] = $egen->create_restricted_article([
            'name' => 'not recommended + restricted + >5',
            'timeview' => time_view::FIVE_TO_TEN
        ]);

        $users = [];
        $users[1] = $gen->create_user(['username' => 'user1']);
        $users[2] = $gen->create_user(['username' => 'user2']);

        $recommendations = [];
        foreach ([1, 2, 3, 7, 8, 9] as $key) {
            $recommendations[] = $resources[$key]->get_id();
        }
        $this->set_recommended_data($recommendations);

        return [$resources, $users];
    }
}