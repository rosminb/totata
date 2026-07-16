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

use core\collection;
use mod_perform\constants;
use mod_perform\entity\activity\activity as activity_entity;
use mod_perform\entity\activity\notification as notification_entity;
use mod_perform\entity\activity\notification_recipient as notification_recipient_entity;
use mod_perform\entity\activity\participant_instance;
use mod_perform\entity\activity\participant_section as participant_section_entity;
use mod_perform\entity\activity\section as section_entity;
use mod_perform\entity\activity\section_element as section_element_entity;
use mod_perform\entity\activity\subject_instance as subject_instance_entity;
use mod_perform\models\activity\activity as activity_model;
use mod_perform\models\activity\notification;
use mod_perform\models\activity\participant_instance as participant_instance_model;
use mod_perform\models\activity\section_element as section_element_model;
use mod_perform\models\activity\subject_instance as subject_instance_model;
use mod_perform\models\response\participant_section;
use mod_perform\models\response\section_element_response as section_element_response_model;
use mod_perform\notification\broker;
use mod_perform\notification\factory;
use mod_perform\notification\recipient;
use mod_perform\notification\trigger;
use mod_perform\state\activity\active;
use mod_perform\state\activity\draft;
use mod_perform\state\condition;
use mod_perform\state\participant_instance\in_progress;
use mod_perform\state\participant_instance\not_started;
use mod_perform\state\participant_instance\progress_not_applicable;
use mod_perform\state\subject_instance\closed as subject_instance_closed;
use mod_perform\state\subject_instance\open as subject_instance_open;
use mod_perform\task\check_notification_trigger_task;
use totara_job\job_assignment;

require_once(__DIR__ . '/notification_testcase.php');

/**
 * @group perform
 * @group perform_notifications
 */
class mod_perform_notification_check_trigger_task_testcase extends mod_perform_notification_testcase {
    /** @var stdClass */
    private $user;

    /** @var stdClass */
    private $manager;

    /** @var stdClass */
    private $supervisor;

    /** @var stdClass */
    private $cohort;

    /** @var activity_model */
    private $activity_draft;

    /** @var activity_model */
    private $activity_open;

    /** @var activity_model */
    private $activity_completed;

    /** @var activity_model */
    private $activity_closed;

    public function setUp(): void {
        parent::setUp();
        $this->setAdminUser();
        $this->mock_loader([
            'no_condition_no_trigger' => [
                'class' => mod_perform_mock_broker_one::class,
                'name' => ['notification_broker::mock_nn', 'mod_perform'],
                'trigger_type' => trigger::TYPE_ONCE,
                'recipients' => recipient::STANDARD,
            ],
            'yes_condition_no_trigger' => [
                'class' => mod_perform_mock_broker_two::class,
                'name' => ['notification_broker::mock_yn', 'mod_perform'],
                'condition' => mod_perform_mock_condition::class,
                'recipients' => recipient::STANDARD,
                'trigger_type' => trigger::TYPE_ONCE,
            ],
            'yes_condition_yes_trigger' => [
                'class' => mod_perform_mock_broker_three::class,
                'name' => ['notification_broker::mock_yy', 'mod_perform'],
                'condition' => mod_perform_mock_condition::class,
                'recipients' => recipient::STANDARD,
                'trigger_type' => trigger::TYPE_AFTER,
                'trigger_label' => ['ok'],
            ],
            'broken_broker' => [
                'class' => mod_perform_mock_broker_broken::class,
                'name' => ['notification_broker::mock_broken', 'mod_perform'],
                'condition' => mod_perform_mock_condition::class,
                'recipients' => recipient::STANDARD,
                'trigger_type' => trigger::TYPE_AFTER,
                'trigger_label' => ['ok'],
            ],
            // we need a broker with the exact name 'completion'
            'completion' => [
                'name' => ['notification_broker_completion', 'mod_perform'],
                'class' => mod_perform_mock_broker_four::class,
                'trigger_type' => trigger::TYPE_ONCE,
                'recipients' => recipient::STANDARD,
            ]
        ]);
        $this->overrideLangString('notification_broker::mock_nn', 'mod_perform', 'no condition, no trigger', true);
        $this->overrideLangString('notification_broker::mock_yn', 'mod_perform', 'yes condition, no trigger', true);
        $this->overrideLangString('notification_broker::mock_yy', 'mod_perform', 'yes condition, yes trigger', true);
        $this->overrideLangString('notification_broker::mock_broken', 'mod_perform', 'broker without triggerable', true);
        $this->overrideLangString('template_yes_condition_no_trigger_subject_subject', 'mod_perform', 'subject of yes/no to subject', true);
        $this->overrideLangString('template_yes_condition_no_trigger_subject_body', 'mod_perform', 'body of yes/no to subject', true);
        $this->overrideLangString('template_yes_condition_yes_trigger_subject_subject', 'mod_perform', 'subject of yes/yes to subject', true);
        $this->overrideLangString('template_yes_condition_yes_trigger_subject_body', 'mod_perform', 'body of yes/yes to subject', true);
        $this->overrideLangString('template_yes_condition_no_trigger_manager_subject', 'mod_perform', 'subject of yes/no to manager', true);
        $this->overrideLangString('template_yes_condition_no_trigger_manager_body', 'mod_perform', 'body of yes/no to manager', true);
        $this->overrideLangString('template_yes_condition_yes_trigger_manager_subject', 'mod_perform', 'subject of yes/yes to manager', true);
        $this->overrideLangString('template_yes_condition_yes_trigger_manager_body', 'mod_perform', 'body of yes/yes to manager', true);
        $this->overrideLangString('template_yes_condition_no_trigger_managers_manager_subject', 'mod_perform', "subject of yes/no to manager's manager", true);
        $this->overrideLangString('template_yes_condition_no_trigger_managers_manager_body', 'mod_perform', "body of yes/no to manager's manager", true);
        $this->overrideLangString('template_yes_condition_yes_trigger_managers_manager_subject', 'mod_perform', "subject of yes/yes to manager's manager", true);
        $this->overrideLangString('template_yes_condition_yes_trigger_managers_manager_body', 'mod_perform', "body of yes/yes to manager's manager", true);

        $this->user = $this->getDataGenerator()->create_user(['username' => 'subject']);
        $this->manager = $this->getDataGenerator()->create_user(['username' => 'manager']);
        $this->supervisor = $this->getDataGenerator()->create_user(['username' => 'supervisor']);

        $superja = job_assignment::create_default($this->supervisor->id);
        $manja = job_assignment::create_default($this->manager->id, ['managerjaid' => $superja->id]);
        $userja = job_assignment::create_default($this->user->id, ['managerjaid' => $manja->id]);

        $this->cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($this->cohort->id, $this->user->id);
        $this->activity_draft = $this->create(['activity_name' => 'draft']);
        $this->activity_open = $this->create(['activity_name' => 'open']);
        $this->activity_completed = $this->create(['activity_name' => 'completed']);
        $this->activity_closed = $this->create(['activity_name' => 'closed']);

        $this->activate($this->activity_open, $this->activity_completed, $this->activity_closed);

        $this->submit($this->activity_completed);
        $this->submit($this->activity_closed);
        $this->close($this->activity_closed);
    }

    public function tearDown(): void {
        parent::tearDown();
        $this->user = $this->manager = $this->supervisor = $this->cohort = null;
        $this->activity_draft = $this->activity_open = $this->activity_completed = $this->activity_closed = null;
    }

    /**
     * @param array $data
     * @return activity_model
     */
    private function create(array $data = []): activity_model {
        if (!isset($data['activity_status'])) {
            $data['activity_status'] = draft::get_code();
        }
        if (!isset($data['create_section'])) {
            $data['create_section'] = false;
        }
        $activity = $this->create_activity($data);
        $section = $this->create_section($activity, ['title' => $data['activity_name'] . "'s section"]);
        $this->perfgen->create_section_relationship($section, ['relationship' => constants::RELATIONSHIP_SUBJECT]);
        $this->perfgen->create_section_relationship($section, ['relationship' => constants::RELATIONSHIP_MANAGER]);
        $this->perfgen->create_section_relationship($section, ['relationship' => constants::RELATIONSHIP_MANAGERS_MANAGER]);
        $element = $this->perfgen->create_element(['title' => $data['activity_name'] . "'s element"]);
        section_element_model::create($section, $element, 1);
        $track = $this->perfgen->create_activity_tracks($activity, 1)->first(true);
        $this->perfgen->create_track_assignments_with_existing_groups($track, [$this->cohort->id]);

        foreach (factory::create_loader()->get_class_keys() as $class_key) {
            $notification = notification::load_by_activity_and_class_key($activity, $class_key)->activate();
            $this->perfgen->create_notification_recipient($notification, ['idnumber' => constants::RELATIONSHIP_SUBJECT], true);
            $this->perfgen->create_notification_recipient($notification, ['idnumber' => constants::RELATIONSHIP_MANAGER], true);
            $this->perfgen->create_notification_recipient($notification, ['idnumber' => constants::RELATIONSHIP_MANAGERS_MANAGER], true);
        }
        return $activity;
    }

    /**
     * @param activity_model $activity
     */
    private function assert_activatable(activity_model $activity): void {
        $this->assertTrue($activity->get_status_state()->can_potentially_activate());
        $transition = $activity->get_status_state()->get_transition_to(active::class);
        if ($transition->is_possible()) {
            return;
        }
        // Dig into the conditions to find out which one is failing.
        $prop = new ReflectionProperty($transition, 'conditions');
        $prop->setAccessible(true);
        $conditions = $prop->getValue($transition);
        /** @var condition[] $conditions */
        foreach ($conditions as $condition_class) {
            $condition = new $condition_class($transition->get_to()->get_object());
            $method = new ReflectionMethod($condition_class, 'pass');
            $this->assertTrue($condition->pass(), $method->getFileName() . ':' . $method->getStartLine() . "\n" . $condition_class . ' shall not pass');
        }
    }

    /**
     * @param activity_model ...$activities
     */
    private function activate(activity_model ...$activities): void {
        foreach ($activities as $activity) {
            $this->assert_activatable($activity);
            $activity->activate();
            $subject_instance = $this->perfgen->create_subject_instance([
                'activity_id' => $activity->id,
                'subject_user_id' => $this->user->id,
                'include_questions' => false,
            ]);
            $this->assertNotNull($subject_instance, $activity->name);
            $section = $activity->get_sections()->first();

            $manager_section_relationship = $this->perfgen->create_section_relationship(
                $section,
                ['relationship' => constants::RELATIONSHIP_MANAGER]
            );
            $managers_manager_section_relationship = $this->perfgen->create_section_relationship(
                $section,
                ['relationship' => constants::RELATIONSHIP_MANAGERS_MANAGER]
            );
            $subject_section_relationship = $this->perfgen->create_section_relationship(
                $section,
                ['relationship' => constants::RELATIONSHIP_SUBJECT]
            );

            $this->perfgen->create_participant_instance_and_section(
                $activity,
                $this->user,
                $subject_instance->id,
                $section,
                $subject_section_relationship->core_relationship_id
            );
            $this->perfgen->create_participant_instance_and_section(
                $activity,
                $this->manager,
                $subject_instance->id,
                $section,
                $manager_section_relationship->core_relationship_id
            );
            $this->perfgen->create_participant_instance_and_section(
                $activity,
                $this->supervisor,
                $subject_instance->id,
                $section,
                $managers_manager_section_relationship->core_relationship_id
            );
        }
    }

    /**
     * @param activity_model ...$activities
     */
    private function submit(activity_model ...$activities): void {
        foreach ($activities as $activity) {
            $subject_instance = subject_instance_entity::repository()
                ->filter_by_activity_id($activity->id)
                ->with('participant_instances')
                ->one(true);
            $subject_instance = subject_instance_model::load_by_entity($subject_instance);
            $participant_instances = $subject_instance->participant_instances;
            foreach ($participant_instances as $participant_instance) {
                $this->set_participant_instance_complete($participant_instance);
            }
        }

        $this->setAdminUser();
    }

    /**
     * @param participant_instance_model $participant_instance
     * @return void
     */
    private function set_participant_instance_complete(participant_instance_model $participant_instance): void {
        /** @var section_element_entity $section_element */
        $section_element = section_element_entity::repository()
            ->join([section_entity::TABLE, 's'], 'section_id', 'id')
            ->join([activity_entity::TABLE, 'a'], 's.activity_id', 'id')
            ->where('a.id', $participant_instance->subject_instance->get_activity()->id)
            ->one(true);

        $idnumber = $participant_instance->core_relationship->idnumber;
        if ($idnumber === constants::RELATIONSHIP_SUBJECT) {
            $this->setUser($this->user);
        } else if ($idnumber === constants::RELATIONSHIP_MANAGER) {
            $this->setUser($this->manager);
        } else if ($idnumber === constants::RELATIONSHIP_MANAGERS_MANAGER) {
            $this->setUser($this->supervisor);
        }

        /** @var participant_section_entity $participant_section_entity */
        $participant_section_entity = participant_section_entity::repository()
            ->where('participant_instance_id', $participant_instance->id)
            ->where('section_id', $section_element->section_id)
            ->one(true);
        $participant_section = new participant_section($participant_section_entity);

        $element_response = new section_element_response_model(
            $participant_instance,
            section_element_model::load_by_entity($section_element),
            null,
            new collection()
        );
        $participant_section->set_section_element_responses(new collection([$element_response]));
        $participant_section->get_progress_state()->complete();
    }

    /**
     * @param activity_model ...$activities
     */
    private function close(activity_model ...$activities): void {
        foreach ($activities as $activity) {
            $subject_instances = subject_instance_entity::repository()
                ->filter_by_activity_id($activity->id)
                ->get()
                ->map_to(subject_instance_model::class)
                ->all();
            foreach ($subject_instances as $subject_instance) {
                /** @var subject_instance_model $subject_instance */
                $subject_instance->manually_close();
                $this->assertInstanceOf(subject_instance_closed::class, $subject_instance->availability_state);
            }
        }
    }

    /**
     * Sanity check for create, activate, submit and close functions.
     */
    public function test_sanity_check() {
        $this->assertTrue($this->activity_draft->is_draft());
        $this->assertTrue($this->activity_open->is_active());
        $this->assertTrue($this->activity_completed->is_active());
        $this->assertTrue($this->activity_closed->is_active());

        $this->assertEquals(0, subject_instance_entity::repository()->filter_by_activity_id($this->activity_draft->id)->count());
        $this->assertEquals(1, subject_instance_entity::repository()->filter_by_activity_id($this->activity_open->id)->count());
        $this->assertEquals(1, subject_instance_entity::repository()->filter_by_activity_id($this->activity_completed->id)->count());
        $this->assertEquals(1, subject_instance_entity::repository()->filter_by_activity_id($this->activity_closed->id)->count());

        $this->assertFalse(subject_instance_model::load_by_entity(subject_instance_entity::repository()->filter_by_activity_id($this->activity_open->id)->one(true))->is_complete());
        $this->assertTrue(subject_instance_model::load_by_entity(subject_instance_entity::repository()->filter_by_activity_id($this->activity_completed->id)->one(true))->is_complete());
        $this->assertTrue(subject_instance_model::load_by_entity(subject_instance_entity::repository()->filter_by_activity_id($this->activity_closed->id)->one(true))->is_complete());

        $this->assertInstanceOf(subject_instance_open::class, subject_instance_model::load_by_entity(subject_instance_entity::repository()->filter_by_activity_id($this->activity_completed->id)->one(true))->availability_state);
        $this->assertInstanceOf(subject_instance_closed::class, subject_instance_model::load_by_entity(subject_instance_entity::repository()->filter_by_activity_id($this->activity_closed->id)->one(true))->availability_state);
    }

    public function test_get_name() {
        $task = new check_notification_trigger_task();
        $this->assertIsString($task->get_name());
    }

    public function test_execute(): void {
        //    notification  recipient  trigger  sent
        //    ------------  ---------  -------  ----
        // 1      -            -          -     no
        //        -            -          x     -
        //        -            x          -     -
        //        -            x          x     -
        // 2      x            -          -     no
        //        x            -          x     -
        // 3      x            x          -     no
        // 4      x            x          x     yes

        $sink = factory::create_sink();

        // 1
        $messages = $this->execute_with_settings(false, false, false);
        $this->assertCount(0, $sink->get_all());
        $this->assertCount(0, $messages);

        // 2
        $messages = $this->execute_with_settings(true, false, false);
        $this->assertCount(0, $sink->get_all());
        $this->assertCount(0, $messages);

        // 3
        $messages = $this->execute_with_settings(true, true, false);
        $this->assertCount(0, $sink->get_all());
        $this->assertCount(0, $messages);

        // 4
        $messages = $this->execute_with_settings(true, true, true);
        $this->assertCount(6, $sink->get_all());
        $this->assertCount(0, $sink->get_by_class_key('no_condition_no_trigger'));
        $this->assertCount(1, $sink->get_by_relationship(constants::RELATIONSHIP_SUBJECT)->filter('class_key', 'yes_condition_no_trigger'));
        $this->assertCount(1, $sink->get_by_relationship(constants::RELATIONSHIP_SUBJECT)->filter('class_key', 'yes_condition_yes_trigger'));
        $this->assertCount(1, $sink->get_by_relationship(constants::RELATIONSHIP_MANAGER)->filter('class_key', 'yes_condition_no_trigger'));
        $this->assertCount(1, $sink->get_by_relationship(constants::RELATIONSHIP_MANAGER)->filter('class_key', 'yes_condition_yes_trigger'));
        $this->assertCount(1, $sink->get_by_relationship(constants::RELATIONSHIP_MANAGERS_MANAGER)->filter('class_key', 'yes_condition_no_trigger'));
        $this->assertCount(1, $sink->get_by_relationship(constants::RELATIONSHIP_MANAGERS_MANAGER)->filter('class_key', 'yes_condition_yes_trigger'));
        $this->assertCount(0, $sink->get_by_class_key('broken_broker'));
        $this->assertCount(0, $sink->get_by_class_key('completion'));
        $this->assertCount(6, $messages);
        foreach (['subject' => $this->user, 'manager' => $this->manager, "manager's manager" => $this->supervisor] as $role => $user) {
            $messages_user = array_values(array_filter($messages, function (stdClass $message) use ($user) {
                return $message->useridto == $user->id;
            }));
            $this->assertCount(2, $messages_user);
            $this->assertEquals(core_user::NOREPLY_USER, $messages_user[0]->useridfrom);
            $this->assertEquals("subject of yes/no to {$role}", $messages_user[0]->subject);
            $this->assertStringContainsString("body of yes/no to {$role}", $messages_user[0]->fullmessage);
            $this->assertEquals(core_user::NOREPLY_USER, $messages_user[1]->useridfrom);
            $this->assertEquals("subject of yes/yes to {$role}", $messages_user[1]->subject);
            $this->assertStringContainsString("body of yes/yes to {$role}", $messages_user[1]->fullmessage);
        }
    }

    public function test_execute_closed(): void {
        // Create an additional activity.
        $additional_activity = $this->create(['activity_name' => 'additional_activity']);
        $this->activate($additional_activity);

        $sink = factory::create_sink();

        // We should be getting 12 messages before closing the activity.
        $messages = $this->execute_with_settings(true, true, true);
        $this->assertCount(12, $sink->get_all());
        $this->assertCount(12, $messages);

        // Close the activity.
        $this->close($additional_activity);

        // We should now only be getting 6.
        $messages = $this->execute_with_settings(true, true, true);
        $this->assertCount(6, $sink->get_all());
        $this->assertCount(6, $messages);
    }

    /**
     * Provide a variety of functions that change the state of a participant instance.
     *
     * @return array
     */
    public function participant_instance_state_data_provider(): array {
        return [
            [
                'should be sent' => false,
                'complete participant instance' => function (participant_instance_model $participant_instance) {
                    $this->set_participant_instance_complete($participant_instance);
                }
            ],
            [
                'should be sent' => false,
                'close participant instance' => function (participant_instance_model $participant_instance) {
                    $participant_instance->manually_close();
                }
            ],
            [
                'should be sent' => false,
                'not applicable (view only)' => function (participant_instance_model $participant_instance) {
                    participant_instance::repository()
                        ->where('id', $participant_instance->id)
                        ->update(['progress' => progress_not_applicable::get_code()]);
                }
            ],
            [
                'should be sent' => true,
                'in progress' => function (participant_instance_model $participant_instance) {
                    participant_instance::repository()
                        ->where('id', $participant_instance->id)
                        ->update(['progress' => in_progress::get_code()]);
                }
            ],
        ];
    }

    /**
     * @dataProvider participant_instance_state_data_provider
     */
    public function test_execute_depending_on_participant_instance_state(
        bool $should_be_sent,
        Closure $process_participant_instance
    ): void {
        $sink = factory::create_sink();

        // Adjust subject's and manager's participant instances.
        /** @var participant_instance $subject_participant_instance */
        $subject_participant_instance = participant_instance::repository()
            ->where('participant_id', $this->user->id)
            ->where('progress', not_started::get_code())
            ->one(true);
        /** @var participant_instance $manager_participant_instance */
        $manager_participant_instance = participant_instance::repository()
            ->where('participant_id', $this->manager->id)
            ->where('progress', not_started::get_code())
            ->one(true);
        $process_participant_instance(participant_instance_model::load_by_entity($subject_participant_instance));
        $process_participant_instance(participant_instance_model::load_by_entity($manager_participant_instance));

        // Only manager's manager still gets notifications when sending is prevented for subject and manager.
        $expected_number_of_notifications = $should_be_sent ? 6 : 2;
        $expected_single_check = (int)$should_be_sent;

        $messages = $this->execute_with_settings(true, true, true);
        $this->assertCount($expected_number_of_notifications, $sink->get_all());
        $this->assertCount(0, $sink->get_by_class_key('no_condition_no_trigger'));
        $this->assertCount($expected_single_check, $sink->get_by_relationship(constants::RELATIONSHIP_SUBJECT)->filter('class_key', 'yes_condition_no_trigger'));
        $this->assertCount($expected_single_check, $sink->get_by_relationship(constants::RELATIONSHIP_SUBJECT)->filter('class_key', 'yes_condition_yes_trigger'));
        $this->assertCount($expected_single_check, $sink->get_by_relationship(constants::RELATIONSHIP_MANAGER)->filter('class_key', 'yes_condition_no_trigger'));
        $this->assertCount($expected_single_check, $sink->get_by_relationship(constants::RELATIONSHIP_MANAGER)->filter('class_key', 'yes_condition_yes_trigger'));
        $this->assertCount(1, $sink->get_by_relationship(constants::RELATIONSHIP_MANAGERS_MANAGER)->filter('class_key', 'yes_condition_no_trigger'));
        $this->assertCount(1, $sink->get_by_relationship(constants::RELATIONSHIP_MANAGERS_MANAGER)->filter('class_key', 'yes_condition_yes_trigger'));
        $this->assertCount(0, $sink->get_by_class_key('broken_broker'));
        $this->assertCount(0, $sink->get_by_class_key('completion'));
        $this->assertCount($expected_number_of_notifications, $messages);

        $expected_recipients = ["manager's manager" => $this->supervisor];
        if ($should_be_sent) {
            $expected_recipients['subject'] = $this->user;
            $expected_recipients['manager'] = $this->manager;
        }
        foreach ($expected_recipients as $role => $user) {
            $messages_user = array_values(array_filter($messages, function (stdClass $message) use ($user) {
                return $message->useridto == $user->id;
            }));
            $this->assertCount(2, $messages_user);
            $this->assertEquals(core_user::NOREPLY_USER, $messages_user[0]->useridfrom);
            $this->assertEquals("subject of yes/no to {$role}", $messages_user[0]->subject);
            $this->assertStringContainsString("body of yes/no to {$role}", $messages_user[0]->fullmessage);
            $this->assertEquals(core_user::NOREPLY_USER, $messages_user[1]->useridfrom);
            $this->assertEquals("subject of yes/yes to {$role}", $messages_user[1]->subject);
            $this->assertStringContainsString("body of yes/yes to {$role}", $messages_user[1]->fullmessage);
        }
    }

    /**
     * Check the edge case where recipients exist but none of the participant instances qualify for receiving notifications.
     *
     * @return void
     */
    public function test_execute_without_qualifying_participant_instances(): void {
        $sink = factory::create_sink();

        $participant_instance_ids = participant_instance::repository()
            ->where('progress', not_started::get_code())
            ->get()
            ->pluck('id');
        $this->assertCount(3, $participant_instance_ids);
        participant_instance::repository()
            ->where_in('id', $participant_instance_ids)
            ->update(['progress' => progress_not_applicable::get_code()]);

        $messages = $this->execute_with_settings(true, true, true);

        $this->assertCount(0, $messages);
        $this->assertCount(0, $sink->get_all());
    }

    /**
     * @param bool $notification
     * @param bool $recipient
     * @param bool $triggerable
     * @return array
     */
    private function execute_with_settings(bool $notification, bool $recipient, bool $triggerable): array {
        notification_entity::repository()->update(['active' => $notification]);
        notification_recipient_entity::repository()->update(['active' => $recipient]);
        (new mod_perform_mock_broker_one())->set_triggerable($triggerable);
        (new mod_perform_mock_broker_two())->set_triggerable($triggerable);
        (new mod_perform_mock_broker_three())->set_triggerable($triggerable);
        (factory::create_sink())->clear();
        $this->redirect_messages();
        (new check_notification_trigger_task())->execute();
        $messages = $this->get_messages();
        $this->assertDebuggingCalled(array_fill(0, 1, 'mod_perform_mock_broker_broken does not implement triggerable'));
        $this->resetDebugging();
        return $messages;
    }
}

class mod_perform_mock_broker_broken implements broker {
    public function get_default_triggers(): array {
        return [];
    }
}
