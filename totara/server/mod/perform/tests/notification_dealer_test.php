<?php
/**
 * This file is part of Totara Learn
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Tatsuhiro Kirihara <tatsuhiro.kirihara@totaralearning.com>
 * @package mod_perform
 * @category test
 */

use core\orm\query\builder;
use mod_perform\constants;
use mod_perform\entity\activity\participant_instance as participant_instance_entity;
use mod_perform\entity\activity\subject_instance as subject_instance_entity;
use mod_perform\expand_task;
use mod_perform\models\activity\notification;
use mod_perform\models\activity\participant_instance as participant_instance_model;
use mod_perform\notification\dealer;
use mod_perform\notification\factory;
use totara_job\job_assignment;
use totara_tenant\local\util;

require_once(__DIR__ . '/notification_testcase.php');

/**
 * @coversDefaultClass \mod_perform\notification\dealer
 * @covers \mod_perform\notification\factory
 * @group perform
 * @group perform_notifications
 */
class mod_perform_notification_dealer_testcase extends mod_perform_notification_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->mock_loader(null);
        $this->setAdminUser();
    }

    /**
     * @covers ::__construct
     */
    public function test_constructor() {
        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $manager = $this->getDataGenerator()->create_user(['username' => 'manager']);
        $appraiser = $this->getDataGenerator()->create_user(['username' => 'appraiser']);
        $supervisor = $this->getDataGenerator()->create_user(['username' => 'supervisor']);
        $manmanja = job_assignment::create_default($supervisor->id);
        $manja = job_assignment::create_default($manager->id, ['managerjaid' => $manmanja->id]);
        $user1ja = job_assignment::create_default($user1->id, ['appraiserid' => $appraiser->id, 'managerjaid' => $manja->id]);
        $activity = $this->create_activity();
        $section = $this->create_section($activity);
        $this->create_section_relationships($section);
        $track = $this->create_single_activity_track_and_assignment($activity, [$user1->id]);
        $element = $this->perfgen->create_element(['title' => 'Question one', 'plugin_name' => 'short_text']);
        $this->perfgen->create_section_element($section, $element);
        expand_task::create()->expand_all();

        $entities = $this->create_participant_instances_on_track($track);
        $this->assertCount(4, $entities);
        $this->assertCount(1, $entities->filter('core_relationship_id', $this->get_core_relationship(constants::RELATIONSHIP_SUBJECT)->id));
        $this->assertCount(1, $entities->filter('core_relationship_id', $this->get_core_relationship(constants::RELATIONSHIP_APPRAISER)->id));
        $this->assertEquals(1, subject_instance_entity::repository()->count());

        $prop = new ReflectionProperty(dealer::class, 'participant_instances');
        $prop->setAccessible(true);

        $dealer = factory::create_dealer_on_participant_instances($entities->all());
        $this->assertCount(4, $prop->getValue($dealer));

        $dealer = new dealer($entities->map_to(participant_instance_model::class)->all());
        $this->assertCount(4, $prop->getValue($dealer));

        // passing an empty array succeeds.
        $dealer = new dealer([]);
        $this->assertCount(0, $prop->getValue($dealer));

        try {
            new dealer($entities->all());
            $this->fail('coding_exception expected');
        } catch (coding_exception $ex) {
            $this->assertStringContainsString('participant_instances must be an array of participant_instance models', $ex->getMessage());
        }

        try {
            factory::create_dealer_on_participant_instances($entities->map(function(participant_instance_entity $e) {
                return $e->id;
            })->all());
            $this->fail('coding_exception expected');
        } catch (coding_exception $ex) {
            $this->assertStringContainsString('unknown element at 0', $ex->getMessage());
        }
    }

    public function delete_or_suspend_data_provider(): array {
        return [
            [
                function (stdClass $user) {
                    delete_user($user);
                }
            ],
            [
                function (stdClass $user) {
                    set_config('perform_hide_suspended_users', 1);
                    user_suspend_user($user->id);
                }
            ],
            [
                function (stdClass $user) {
                    set_config('perform_hide_suspended_users', 0);
                    user_suspend_user($user->id);
                },
                false
            ],
        ];
    }

    public function test_post_multi_tenancy_enabled() {
        $generator = $this->getDataGenerator();
        /** @var \totara_tenant\testing\generator $tenant_generator */
        $tenant_generator = $generator->get_plugin_generator('totara_tenant');

        $tenant_generator->enable_tenants();

        $tenant1 = $tenant_generator->create_tenant();
        $tenant2 = $tenant_generator->create_tenant();

        $tenant1_manager = $generator->create_user(['tenantid' => $tenant1->id]);
        $tenant2_manager = $generator->create_user(['tenantid' => $tenant2->id]);

        $tenant_dm_role = builder::table('role')->where('shortname', 'tenantdomainmanager')->one();

        role_assign($tenant_dm_role->id, $tenant1_manager->id, context_coursecat::instance($tenant1->categoryid));
        role_assign($tenant_dm_role->id, $tenant2_manager->id, context_coursecat::instance($tenant2->categoryid));

        $user11 = $this->getDataGenerator()->create_user(['tenantid' => $tenant1->id]);
        $user12 = $this->getDataGenerator()->create_user(['tenantid' => $tenant1->id]);
        $manager1 = $this->getDataGenerator()->create_user(['tenantid' => $tenant1->id]);
        $appraiser1 = $this->getDataGenerator()->create_user(['tenantid' => $tenant1->id]);
        $user11ja = job_assignment::create_default($user11->id, ['appraiserid' => $appraiser1->id]);
        job_assignment::create_default($manager1->id, ['managerjaid' => $user11ja->id]);
        $user12ja = job_assignment::create_default($user12->id);
        job_assignment::create_default($manager1->id, ['managerjaid' => $user12ja->id]);

        $user21 = $this->getDataGenerator()->create_user(['tenantid' => $tenant2->id]);
        $user22 = $this->getDataGenerator()->create_user(['tenantid' => $tenant2->id]);
        $manager2 = $this->getDataGenerator()->create_user(['tenantid' => $tenant2->id]);
        $appraiser2 = $this->getDataGenerator()->create_user(['tenantid' => $tenant2->id]);
        $user21ja = job_assignment::create_default($user21->id, ['appraiserid' => $appraiser2->id]);
        job_assignment::create_default($manager2->id, ['managerjaid' => $user21ja->id]);
        $user22ja = job_assignment::create_default($user22->id);
        job_assignment::create_default($manager2->id, ['managerjaid' => $user22ja->id]);

        $this->setUser($tenant1_manager);

        $activity1 = $this->create_activity();
        $section1 = $this->create_section($activity1);
        $this->create_section_relationships($section1);
        $track1 = $this->create_single_activity_track_and_assignment($activity1, [$user11->id, $user12->id]);
        $element1 = $this->perfgen->create_element(['title' => 'Question one', 'plugin_name' => 'short_text']);
        $this->perfgen->create_section_element($section1, $element1);

        expand_task::create()->expand_all();

        $notif11 = notification::load_by_activity_and_class_key($activity1, 'mock_one');
        $notif12 = notification::load_by_activity_and_class_key($activity1, 'mock_two')->activate();
        $this->toggle_recipients($notif11, [constants::RELATIONSHIP_SUBJECT => true]);
        $this->toggle_recipients($notif12, [constants::RELATIONSHIP_SUBJECT => true, constants::RELATIONSHIP_APPRAISER => true]);

        $activity1->activate();
        $this->assertTrue($activity1->is_active());

        $entities1 = $this->create_participant_instances_on_track($track1);

        $this->setUser($tenant2_manager);

        $activity2 = $this->create_activity();
        $section2 = $this->create_section($activity2);
        $this->create_section_relationships($section2);
        $track2 = $this->create_single_activity_track_and_assignment($activity2, [$user21->id, $user22->id]);
        $element2 = $this->perfgen->create_element(['title' => 'Question two', 'plugin_name' => 'short_text']);
        $this->perfgen->create_section_element($section2, $element2);

        expand_task::create()->expand_all();

        $notif21 = notification::load_by_activity_and_class_key($activity2, 'mock_one');
        $notif22 = notification::load_by_activity_and_class_key($activity2, 'mock_two')->activate();
        $this->toggle_recipients($notif21, [constants::RELATIONSHIP_SUBJECT => true]);
        $this->toggle_recipients($notif22, [constants::RELATIONSHIP_SUBJECT => true, constants::RELATIONSHIP_APPRAISER => true]);

        $activity2->activate();
        $this->assertTrue($activity2->is_active());

        $entities2 = $this->create_participant_instances_on_track($track2);

        $this->setAdminUser();

        $dealer1 = factory::create_dealer_on_participant_instances($entities1->all());

        $sink = $this->redirectMessages();

        $dealer1->dispatch('mock_one');
        $this->assertCount(0, $sink->get_messages());
        $sink->clear();

        $dealer1->dispatch('mock_two');
        // The user does not receive a notification
        $this->assertEqualsCanonicalizing(
            [$user11->id, $user12->id, $appraiser1->id],
            array_column($sink->get_messages(), 'useridto')
        );
        $sink->clear();

        $dealer1->dispatch('mock_three');
        $this->assertCount(0, $sink->get_messages());
        $sink->clear();

        $dealer2 = factory::create_dealer_on_participant_instances($entities2->all());

        $dealer2->dispatch('mock_one');
        $this->assertCount(0, $sink->get_messages());
        $sink->clear();

        $dealer2->dispatch('mock_two');
        $this->assertEqualsCanonicalizing(
            [$user21->id, $user22->id, $appraiser2->id],
            array_column($sink->get_messages(), 'useridto')
        );
        $sink->clear();

        $dealer2->dispatch('mock_three');
        $this->assertCount(0, $sink->get_messages());
        $sink->clear();

        // Now migrate user 1 to the other tenant
        util::migrate_user_to_tenant($user11->id, $tenant2->id);

        $dealer1->dispatch('mock_one');
        $this->assertCount(0, $sink->get_messages());
        $sink->clear();

        $dealer1->dispatch('mock_two');
        // The user does not receive a notification
        $this->assertEqualsCanonicalizing(
            [$user12->id, $appraiser1->id],
            array_column($sink->get_messages(), 'useridto')
        );
        $sink->clear();

        $dealer1->dispatch('mock_three');
        $this->assertCount(0, $sink->get_messages());
        $sink->clear();

        // And the other one still sends the same messages
        $dealer2->dispatch('mock_one');
        $this->assertCount(0, $sink->get_messages());
        $sink->clear();

        $dealer2->dispatch('mock_two');
        $this->assertEqualsCanonicalizing(
            [$user21->id, $user22->id, $appraiser2->id],
            array_column($sink->get_messages(), 'useridto')
        );
        $sink->clear();

        $dealer2->dispatch('mock_three');
        $this->assertCount(0, $sink->get_messages());
        $sink->clear();

        // Move the user out of the tenant but keep him as participant
        util::set_user_participation($user12->id, [$tenant1->id, $tenant2->id]);

        $dealer1->dispatch('mock_one');
        $this->assertCount(0, $sink->get_messages());
        $sink->clear();

        $dealer1->dispatch('mock_two');
        // The user should still receive a notification as he's a participant
        $this->assertEqualsCanonicalizing(
            [$user12->id, $appraiser1->id],
            array_column($sink->get_messages(), 'useridto')
        );
        $sink->clear();

        $dealer1->dispatch('mock_three');
        $this->assertCount(0, $sink->get_messages());
        $sink->clear();
    }

    /**
     * @dataProvider delete_or_suspend_data_provider
     * @param closure $delete_or_suspend
     * @param bool $should_prevent_sending
     * @return void
     * @covers ::dispatch
     */
    public function test_dispatch(closure $delete_or_suspend, bool $should_prevent_sending = true): void {
        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2']);
        $manager = $this->getDataGenerator()->create_user(['username' => 'manager']);
        $appraiser = $this->getDataGenerator()->create_user(['username' => 'appraiser']);
        $manja = job_assignment::create_default($manager->id);
        $user1ja = job_assignment::create_default($user1->id, ['appraiserid' => $appraiser->id, 'managerjaid' => $manja->id]);
        $user2ja = job_assignment::create_default($user2->id, ['managerjaid' => $manja->id]);

        $activity = $this->create_activity();
        $section = $this->create_section($activity);
        $this->create_section_relationships($section);
        $track = $this->create_single_activity_track_and_assignment($activity, [$user1->id, $user2->id]);
        $element = $this->perfgen->create_element(['title' => 'Question one', 'plugin_name' => 'short_text']);
        $this->perfgen->create_section_element($section, $element);

        expand_task::create()->expand_multiple($track->assignments->pluck('id'));

        $notif1 = notification::load_by_activity_and_class_key($activity, 'mock_one');
        $notif2 = notification::load_by_activity_and_class_key($activity, 'mock_two')->activate();
        $this->toggle_recipients($notif1, [constants::RELATIONSHIP_SUBJECT => true]);
        $this->toggle_recipients($notif2, [constants::RELATIONSHIP_SUBJECT => true, constants::RELATIONSHIP_APPRAISER => true]);

        $activity->activate();
        $this->assertTrue($activity->is_active());

        $entities = $this->create_participant_instances_on_track($track);
        $this->assertCount(5, $entities);
        $this->assertCount(2, $entities->filter('core_relationship_id', $this->get_core_relationship(constants::RELATIONSHIP_SUBJECT)->id));
        $this->assertCount(1, $entities->filter('core_relationship_id', $this->get_core_relationship(constants::RELATIONSHIP_APPRAISER)->id));
        $this->assertCount(2, $entities->filter('core_relationship_id', $this->get_core_relationship(constants::RELATIONSHIP_MANAGER)->id));
        $this->assertEquals(2, subject_instance_entity::repository()->count());
        $dealer = factory::create_dealer_on_participant_instances($entities->all());

        $sink = factory::create_sink();

        $sink->clear();
        $dealer->dispatch('mock_one');
        $this->assertEquals(0, $sink->get_by_class_key('mock_one')->count());
        $this->assertEquals(0, $sink->get_by_class_key('mock_two')->count());
        $this->assertEquals(0, $sink->get_by_class_key('mock_three')->count());

        $sink->clear();
        $dealer->dispatch('mock_two');
        $this->assertEquals(0, $sink->get_by_class_key('mock_one')->count());
        $this->assertEquals(3, $sink->get_by_class_key('mock_two')->count());
        $this->assertEquals(0, $sink->get_by_class_key('mock_three')->count());

        $sink->clear();
        $dealer->dispatch('mock_three');
        $this->assertEquals(0, $sink->get_by_class_key('mock_one')->count());
        $this->assertEquals(0, $sink->get_by_class_key('mock_two')->count());
        $this->assertEquals(0, $sink->get_by_class_key('mock_three')->count());

        try {
            $dealer->dispatch('mock_zero');
            $this->fail('record_not_found_exception expected');
        } catch (\core\orm\query\exceptions\record_not_found_exception $ex) {
            // Mock class doesn't exist so it won't be in the db
        }

        $delete_or_suspend($user1);

        // Trigger again
        $sink->clear();
        $entities = participant_instance_entity::repository()->get();
        $dealer = factory::create_dealer_on_participant_instances($entities->all());
        $dealer->dispatch('mock_two');
        $this->assertEquals(0, $sink->get_by_class_key('mock_one')->count());
        $this->assertEquals($should_prevent_sending ? 1 : 3, $sink->get_by_class_key('mock_two')->count());
        $this->assertEquals(0, $sink->get_by_class_key('mock_three')->count());
    }

}
