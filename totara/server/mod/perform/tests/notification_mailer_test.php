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

use core\entity\user as user_entity;
use mod_perform\constants;
use mod_perform\entity\activity\activity_type;
use mod_perform\entity\activity\external_participant as external_participant_entity;
use mod_perform\models\activity\activity_type as activity_type_model;
use mod_perform\models\activity\external_participant as external_participant_model;
use mod_perform\models\activity\notification;
use mod_perform\models\activity\notification_recipient as notification_recipient_model;
use mod_perform\models\activity\participant as participant_model;
use mod_perform\models\activity\participant_source as participant_source_model;
use mod_perform\models\activity\section_element as section_element_model;
use mod_perform\models\activity\subject_instance as subject_instance_model;
use mod_perform\notification\factory;
use mod_perform\notification\internals\message;
use mod_perform\notification\placeholder;
use mod_perform\state\activity\draft;
use totara_core\entity\relationship as relationship_entity;
use totara_core\relationship\relationship as relationship_model;
use totara_core\totara_user;
use totara_job\job_assignment;

require_once(__DIR__ . '/notification_testcase.php');

/**
 * @coversDefaultClass \mod_perform\notification\mailer
 * @group perform
 * @group perform_notifications
 */
class mod_perform_notification_mailer_testcase extends mod_perform_notification_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->override_template_strings('instance_created');
    }

    /**
     * Create a fake placeholder.
     *
     * @param stdClass $subject
     * @param integer|string $recipient internal user id or external user's full name
     * @return placeholder
     */
    private function placeholders(stdClass $subject, $recipient) {
        $activity_type = activity_type::repository()->where('name', 'appraisal')->one();
        $relationship = relationship_model::load_by_idnumber(constants::RELATIONSHIP_SUBJECT);

        return placeholder::from_data([
            'recipient_fullname' => is_number($recipient) ? core_user::get_user($recipient, 'username', MUST_EXIST)->username : $recipient,
            'activity_name' => 'My activity',
            'activity_type' => activity_type_model::load_by_entity($activity_type),
            'subject_fullname' => $subject->username,
            'participant_fullname' => 'Some One',
            'participant_relationship' => $relationship,
            'instance_duedate' => 1672484400,
            'instance_created_at' => 1640948400,
            'instance_days_active' => 3,
            'instance_days_remaining' => 1,
            'instance_days_overdue' => 4,
            'activity_url' => 'http://example.com/kia/ora.php',
            'participant_selection_url' => 'http://example.com/haere/mai.php',
        ]);
    }

    public function test_post_to_internal_participants() {
        $activity = $this->create_activity(['activity_status' => draft::get_code(), 'create_section' => false]);
        $section = $this->create_section($activity);
        $element = $this->perfgen->create_element();
        section_element_model::create($section, $element, 1);
        $track = $this->perfgen->create_activity_tracks($activity, 1)->first(true);
        $notification = notification::load_by_activity_and_class_key($activity, 'instance_created')->activate();
        $relationships = $this->create_section_relationships($section, [
            constants::RELATIONSHIP_SUBJECT,
            constants::RELATIONSHIP_APPRAISER,
            constants::RELATIONSHIP_MANAGER,
            constants::RELATIONSHIP_MANAGERS_MANAGER,
            constants::RELATIONSHIP_PEER,
            constants::RELATIONSHIP_REVIEWER,
            constants::RELATIONSHIP_MENTOR,
        ]);
        $sink = factory::create_sink();

        $user = $this->getDataGenerator()->create_user(['username' => 'subject']);
        $manager = $this->getDataGenerator()->create_user(['username' => 'manager']);
        $appraiser = $this->getDataGenerator()->create_user(['username' => 'appraiser']);
        $supervisor = $this->getDataGenerator()->create_user(['username' => 'supervisor']);
        $peer = $this->getDataGenerator()->create_user(['username' => 'peer']);
        $mentor = $this->getDataGenerator()->create_user(['username' => 'mentor']);
        $reviewer = $this->getDataGenerator()->create_user(['username' => 'reviewer']);

        $superja = job_assignment::create_default($supervisor->id);
        $manja = job_assignment::create_default($manager->id, ['managerjaid' => $superja->id]);
        $userja = job_assignment::create_default($user->id, ['appraiserid' => $appraiser->id, 'managerjaid' => $manja->id]);

        $this->perfgen->create_track_assignments_with_existing_groups($track, [], [], [], [$user->id]);

        $this->toggle_recipients($notification, [
            constants::RELATIONSHIP_SUBJECT => true,
            constants::RELATIONSHIP_MANAGER => false,
            constants::RELATIONSHIP_MANAGERS_MANAGER => true,
            constants::RELATIONSHIP_APPRAISER => true,
            constants::RELATIONSHIP_PEER => true,
            constants::RELATIONSHIP_MENTOR => true,
            constants::RELATIONSHIP_REVIEWER => true,
        ]);

        $recipients = notification_recipient_model::load_by_notification($notification, true);
        $this->assertCount(6, $recipients);

        $activity->activate();
        $this->assertTrue($activity->is_active());

        $sink->clear();
        $mailer = factory::create_mailer_on_notification($notification);
        $this->assertNotNull($mailer);
        $this->redirect_messages();

        $time = time();
        $this->assertTrue($mailer->post($user, $relationships[constants::RELATIONSHIP_SUBJECT], $this->placeholders($user, $user->id)));
        $this->assertFalse($mailer->post($manager, $relationships[constants::RELATIONSHIP_MANAGER], $this->placeholders($user, $manager->id)));
        $this->assertTrue($mailer->post($appraiser, $relationships[constants::RELATIONSHIP_APPRAISER], $this->placeholders($user, $appraiser->id)));
        $this->assertTrue($mailer->post($supervisor, $relationships[constants::RELATIONSHIP_MANAGERS_MANAGER], $this->placeholders($user, $supervisor->id)));

        $peer_user = user_entity::repository()->find_or_fail($peer->id);
        $this->assertTrue($mailer->post($peer_user, $relationships[constants::RELATIONSHIP_PEER], $this->placeholders($user, $peer->id)));

        $mentor_user = user_entity::repository()->find_or_fail($mentor->id);
        $mentor_participant = new participant_model($mentor_user, participant_source_model::INTERNAL);
        $this->assertTrue($mailer->post($mentor_participant, $relationships[constants::RELATIONSHIP_MENTOR], $this->placeholders($user, $mentor->id)));

        $reviewer_entity = relationship_entity::repository()->where('idnumber', constants::RELATIONSHIP_REVIEWER)->one(true);
        $reviewer_entity->idnumber = '';
        $mailer->post($reviewer, relationship_model::load_by_entity($reviewer_entity), $this->placeholders($user, $reviewer->id));

        try {
            $mailer->post('invalid user', $relationships[constants::RELATIONSHIP_SUBJECT], $this->placeholders($user, $user->id));
            $this->fail('coding_exception expected');
        } catch (coding_exception $ex) {
            $this->assertStringContainsString('invalid user passed', $ex->getMessage());
        }

        $messages = $this->get_messages();

        $this->assertCount(5, $messages);
        $filter_by_user = function (int $userid) {
            return function ($e) use ($userid) {
                return $e->useridto == $userid;
            };
        };

        // user
        $message = current(array_filter($messages, $filter_by_user($user->id)));
        $this->assertNotEmpty($message);
        $this->assertStringContainsString('body of instance_created as subject : subject to subject', $message->fullmessage);

        // appraiser
        $message = current(array_filter($messages, $filter_by_user($appraiser->id)));
        $this->assertNotEmpty($message);
        $this->assertStringContainsString('body of instance_created as appraiser : appraiser to subject', $message->fullmessage);

        // manager's manager
        $message = current(array_filter($messages, $filter_by_user($supervisor->id)));
        $this->assertNotEmpty($message);
        $this->assertStringContainsString('body of instance_created as managers_manager : supervisor to subject', $message->fullmessage);

        // peer
        $message = current(array_filter($messages, $filter_by_user($peer->id)));
        $this->assertNotEmpty($message);
        $this->assertStringContainsString('body of instance_created as perform_peer : peer to subject', $message->fullmessage);

        // mentor
        $message = current(array_filter($messages, $filter_by_user($mentor->id)));
        $this->assertNotEmpty($message);
        $this->assertStringContainsString('body of instance_created as perform_mentor : mentor to subject', $message->fullmessage);

        $messages = $sink->get_all();
        $this->assertCount(5, $messages);

        $message = $sink->get_by_relationship(constants::RELATIONSHIP_SUBJECT)->first();
        /** @var message|null $message */
        $this->assertNotNull($message);
        $this->assertEqualsWithDelta($time, $message->sent_at, 2);

        $message = $sink->get_by_relationship(constants::RELATIONSHIP_MANAGER)->first();
        /** @var message|null $message */
        $this->assertNull($message);

        $message = $sink->get_by_relationship(constants::RELATIONSHIP_APPRAISER)->first();
        /** @var message|null $message */
        $this->assertNotNull($message);
        $this->assertEqualsWithDelta($time, $message->sent_at, 2);

        $message = $sink->get_by_relationship(constants::RELATIONSHIP_MANAGERS_MANAGER)->first();
        /** @var message|null $message */
        $this->assertNotNull($message);
        $this->assertEqualsWithDelta($time, $message->sent_at, 2);

        $message = $sink->get_by_relationship(constants::RELATIONSHIP_PEER)->first();
        /** @var message|null $message */
        $this->assertNotNull($message);
        $this->assertEqualsWithDelta($time, $message->sent_at, 2);

        $message = $sink->get_by_relationship(constants::RELATIONSHIP_REVIEWER)->first();
        /** @var message|null $message */
        $this->assertNull($message);

        $message = $sink->get_by_relationship(constants::RELATIONSHIP_MENTOR)->first();
        /** @var message|null $message */
        $this->assertNotNull($message);
        $this->assertEqualsWithDelta($time, $message->sent_at, 2);
    }

    public function test_post_to_external_participants() {
        $activity = $this->create_activity(['activity_status' => draft::get_code(), 'create_section' => false]);
        $subject_relationship = $this->get_core_relationship(constants::RELATIONSHIP_SUBJECT);
        $external_relationship = $this->get_core_relationship(constants::RELATIONSHIP_EXTERNAL);
        $this->perfgen->create_manual_relationships_for_activity($activity, [[
            'selector' => $subject_relationship->id,
            'manual' => $external_relationship->id
        ]]);
        $section = $this->create_section($activity);
        $element = $this->perfgen->create_element();
        section_element_model::create($section, $element, 1);
        $track = $this->perfgen->create_activity_tracks($activity, 1)->first(true);
        $notification = notification::load_by_activity_and_class_key($activity, 'instance_created')->activate();
        $relationships = $this->create_section_relationships($section, [
            constants::RELATIONSHIP_EXTERNAL,
        ]);
        $sink = factory::create_sink();

        $user = $this->getDataGenerator()->create_user(['username' => 'subject', 'firstname' => 'Subb', 'lastname' => 'Gekkto']);
        $this->perfgen->create_track_assignments_with_existing_groups($track, [], [], [], [$user->id]);
        $this->toggle_recipients($notification, [
            constants::RELATIONSHIP_EXTERNAL => true,
        ]);
        $recipients = notification_recipient_model::load_by_notification($notification, true);
        $this->assertCount(1, $recipients);

        $subject_instance = $this->perfgen->create_subject_instance_with_pending_selections($activity, $user, [$external_relationship]);

        $this->assertNotNull($subject_instance, $activity->name);
        $subject_instance = subject_instance_model::load_by_entity($subject_instance);
        $data = [[
            'manual_relationship_id' => $external_relationship->id,
            'users' => [[
                'name' => 'Xternl Uzre',
                'email' => 'xternl.uzre@example.com',
            ]]
        ]];
        $subject_instance->set_participant_users($subject_instance->subject_user_id, $data);
        $participant_instance = $subject_instance->participant_instances->find('core_relationship_id', $external_relationship->id);
        $this->assertNotNull($participant_instance);
        $external_participant = external_participant_model::load_by_entity(external_participant_entity::repository()->get()->first());

        $activity->activate();
        $this->assertTrue($activity->is_active());

        $sink->clear();
        $mailer = factory::create_mailer_on_notification($notification);
        $this->assertNotNull($mailer);
        $this->redirect_messages();

        $time = time();
        $placeholders = placeholder::from_participant_instance($participant_instance);
        $mailer->post($external_participant, $relationships[constants::RELATIONSHIP_EXTERNAL], $placeholders);
        $messages = $this->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals(core_user::NOREPLY_USER, $messages[0]->useridfrom);
        $this->assertEquals(totara_user::EXTERNAL_USER, $messages[0]->useridto);
        $this->assertStringContainsString('body of instance_created as perform_external', $messages[0]->fullmessage);
        $this->assertStringContainsString('Xternl Uzre to Subb Gekkto', $messages[0]->fullmessage);

        $messages = $sink->get_all()->all();
        $this->assertCount(1, $messages);
        $message = $sink->get_by_relationship(constants::RELATIONSHIP_EXTERNAL)->first();
        $this->assertNotNull($message);
        $this->assertEqualsWithDelta($time, $message->sent_at, 2);
    }
}
