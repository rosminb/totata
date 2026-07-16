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

use core\orm\query\exceptions\record_not_found_exception;
use mod_perform\constants;
use mod_perform\entity\activity\manual_relationship_selection;
use mod_perform\entity\activity\manual_relationship_selection_progress as manual_relationship_selection_progress_entity;
use mod_perform\entity\activity\manual_relationship_selector as manual_relationship_selector_entity;
use mod_perform\entity\activity\participant_instance as participant_instance_entity;
use mod_perform\entity\activity\subject_instance as subject_instance_entity;
use mod_perform\entity\activity\track_user_assignment as track_user_assignment_entity;
use mod_perform\models\activity\notification;
use mod_perform\models\activity\notification_recipient as notification_recipient_model;
use mod_perform\models\activity\section_element as section_element_model;
use mod_perform\models\activity\subject_instance as subject_instance_model;
use mod_perform\notification\dealer_participant_selection;
use mod_perform\notification\factory;
use mod_perform\notification\recipient;
use mod_perform\notification\trigger;
use mod_perform\state\activity\draft;
use mod_perform\state\subject_instance\pending;
use totara_core\relationship\relationship as relationship_model;
use totara_job\job_assignment;

require_once(__DIR__ . '/notification_testcase.php');

/**
 * @coversDefaultClass \mod_perform\notification\dealer_participant_selection
 * @covers \mod_perform\notification\factory
 * @group perform
 * @group perform_notifications
 */
class mod_perform_notification_dealer_participant_selection_testcase extends mod_perform_notification_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->mock_loader([
            'kia_ora_koutou_katoa' => [
                'class' => mod_perform_mock_broker_one::class,
                'name' => 'participant selection',
                'trigger_type' => trigger::TYPE_ONCE,
                'recipients' => recipient::STANDARD,
                'all_possible_recipients' => true,
            ],
        ]);
        $this->setAdminUser();
    }

    /**
     * @covers ::__construct
     */
    public function test_constructor() {
        $activity = $this->create_activity(['activity_status' => draft::get_code()]);
        $track = $this->perfgen->create_activity_tracks($activity, 1)->first(true);
        $user = $this->getDataGenerator()->create_user(['username' => 'subject']);

        $user_assignment = new track_user_assignment_entity();
        $user_assignment->track_id = $track->id;
        $user_assignment->subject_user_id = $user->id;
        $user_assignment->deleted = false;
        $user_assignment->save();

        $subject_instance = new subject_instance_entity();
        $subject_instance->track_user_assignment_id = $user_assignment->id;
        $subject_instance->subject_user_id = $user_assignment->subject_user_id; // Purposeful denormalization
        $subject_instance->status = pending::get_code();
        $subject_instance->save();

        $subject_instance->refresh();

        $prop = new ReflectionProperty(dealer_participant_selection::class, 'subject_instances');
        $prop->setAccessible(true);

        $dealer = new dealer_participant_selection([$subject_instance]);
        $this->assertCount(1, $prop->getValue($dealer));

        // passing an empty array succeeds.
        $dealer = new dealer_participant_selection([]);
        $this->assertCount(0, $prop->getValue($dealer));

        try {
            new dealer_participant_selection([subject_instance_model::load_by_entity($subject_instance)]);
            $this->fail('coding_exception expected');
        } catch (coding_exception $ex) {
            $this->assertStringContainsString('subject_instances must be an array of subject_instance entities', $ex->getMessage());
        }
    }

    /**
     * @covers ::dispatch
     */
    public function test_dispatch() {
        $subject_relationship = $this->get_core_relationship(constants::RELATIONSHIP_SUBJECT);
        $appraiser_relationship = $this->get_core_relationship(constants::RELATIONSHIP_APPRAISER);
        $manager_relationship = $this->get_core_relationship(constants::RELATIONSHIP_MANAGER);
        $supervisor_relationship = $this->get_core_relationship(constants::RELATIONSHIP_MANAGERS_MANAGER);
        $peer_relationship = $this->get_core_relationship(constants::RELATIONSHIP_PEER);
        $mentor_relationship = $this->get_core_relationship(constants::RELATIONSHIP_MENTOR);
        $reviewer_relationship = $this->get_core_relationship(constants::RELATIONSHIP_REVIEWER);
        $external_relationship = $this->get_core_relationship(constants::RELATIONSHIP_EXTERNAL);
        $activity = $this->create_activity([
            'activity_status' => draft::get_code(),
            'manual_relationships' => [
                [
                    'selector' => $subject_relationship->id,
                    'manual' => $peer_relationship->id
                ],
                [
                    'selector' => $subject_relationship->id,
                    'manual' => $mentor_relationship->id
                ],
                [
                    'selector' => $manager_relationship->id,
                    'manual' => $reviewer_relationship->id
                ],
                [
                    'selector' => $manager_relationship->id,
                    'manual' => $external_relationship->id
                ],
            ]
        ]);
        $section = $this->create_section($activity);
        $element = $this->perfgen->create_element();
        section_element_model::create($section, $element, 1);
        $track = $this->perfgen->create_activity_tracks($activity, 1)->first(true);
        $notification = notification::load_by_activity_and_class_key($activity, 'kia_ora_koutou_katoa')->activate();
        $this->create_section_relationships($section, [
            constants::RELATIONSHIP_PEER,
            constants::RELATIONSHIP_REVIEWER,
            constants::RELATIONSHIP_MENTOR,
            constants::RELATIONSHIP_EXTERNAL,
        ]);
        $sink = factory::create_sink();

        $user = $this->getDataGenerator()->create_user(['username' => 'subject']);
        $manager = $this->getDataGenerator()->create_user(['username' => 'manager']);
        $appraiser = $this->getDataGenerator()->create_user(['username' => 'appraiser']);

        $manja = job_assignment::create_default($manager->id);
        $userja = job_assignment::create_default($user->id, ['appraiserid' => $appraiser->id, 'managerjaid' => $manja->id]);

        $this->perfgen->create_track_assignments_with_existing_groups($track, [], [], [], [$user->id]);
        $this->toggle_recipients($notification, [
            constants::RELATIONSHIP_SUBJECT => true,
            constants::RELATIONSHIP_APPRAISER => true,
            constants::RELATIONSHIP_MANAGER => true,
            constants::RELATIONSHIP_MANAGERS_MANAGER => true,
        ]);
        $recipients = notification_recipient_model::load_by_notification($notification, true);
        $this->assertCount(4, $recipients);

        $user_assignment = new track_user_assignment_entity();
        $user_assignment->track_id = $track->id;
        $user_assignment->subject_user_id = $user->id;
        $user_assignment->deleted = false;
        $user_assignment->save();

        $subject_instance = new subject_instance_entity();
        $subject_instance->track_user_assignment_id = $user_assignment->id;
        $subject_instance->subject_user_id = $user_assignment->subject_user_id; // Purposeful denormalization
        $subject_instance->status = pending::get_code();
        $subject_instance->save();

        $subject_instance->refresh();

        foreach (manual_relationship_selection::repository()->get() as $selection) {
            /** @var manual_relationship_selection $selection */
            $progress_entity = new manual_relationship_selection_progress_entity();
            $progress_entity->subject_instance_id = $subject_instance->id;
            $progress_entity->manual_relation_selection_id = $selection->id;
            $progress_entity->status = manual_relationship_selection_progress_entity::STATUS_PENDING;
            $progress_entity->save();

            $relationship = relationship_model::load_by_entity($selection->selector_relationship);
            $users = $relationship->get_users(['user_id' => $user->id], context_user::instance($user->id));
            foreach ($users as $user_dto) {
                $selector = new manual_relationship_selector_entity();
                $selector->user_id = $user_dto->get_user_id();
                $selector->manual_relation_select_progress_id = $progress_entity->id;
                $selector->save();
            }
        }

        $subject_instance = $this->perfgen->create_subject_instance_with_pending_selections($activity, $user, [
            $subject_relationship,
            $appraiser_relationship,
            $manager_relationship,
            $supervisor_relationship,
            $reviewer_relationship,
            $peer_relationship,
            $mentor_relationship,
            $external_relationship,
        ]);
        // Make sure no participant instances are created.
        $this->assertFalse(participant_instance_entity::repository()->exists());

        $sink = factory::create_sink();

        $dealer = factory::create_dealer_on_subject_instances_for_manual_participants([$subject_instance]);
        $sink->clear();
        $dealer->dispatch('kia_ora_koutou_katoa');
        // Subject can select for 2 roles (peer & mentor), but should only get one notification.
        $this->assertCount(1, $sink->get_by_relationship(constants::RELATIONSHIP_SUBJECT));
        $this->assertCount(0, $sink->get_by_relationship(constants::RELATIONSHIP_APPRAISER));
        // Manager can select for 2 roles (reviewer & external), but should only get one notification.
        $this->assertCount(1, $sink->get_by_relationship(constants::RELATIONSHIP_MANAGER));
        $this->assertCount(0, $sink->get_by_relationship(constants::RELATIONSHIP_MANAGERS_MANAGER));
        $this->assertCount(0, $sink->get_by_relationship(constants::RELATIONSHIP_REVIEWER));
        $this->assertCount(0, $sink->get_by_relationship(constants::RELATIONSHIP_PEER));
        $this->assertCount(0, $sink->get_by_relationship(constants::RELATIONSHIP_MENTOR));
        $this->assertCount(0, $sink->get_by_relationship(constants::RELATIONSHIP_EXTERNAL));

        $notification->deactivate();
        $sink->clear();
        $dealer->dispatch('kia_ora_koutou_katoa');
        $this->assertCount(0, $sink->get_all());

        $notification->activate();
        $this->toggle_recipients($notification, [
            constants::RELATIONSHIP_SUBJECT => false,
            constants::RELATIONSHIP_APPRAISER => false,
            constants::RELATIONSHIP_MANAGER => false,
            constants::RELATIONSHIP_MANAGERS_MANAGER => false,
        ]);
        $sink->clear();
        $dealer->dispatch('kia_ora_koutou_katoa');
        $this->assertCount(0, $sink->get_all());

        $this->expectException(record_not_found_exception::class);
        $dealer->dispatch('i_do_not_exist');
    }
}
