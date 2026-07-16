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
 * @author Nathan Lewis <nathan.lewis@totaralearning.com>
 * @package totara_notification
 */

use core_phpunit\testcase;
use totara_core\extended_context;
use totara_notification\entity\notifiable_event_preference as notification_event_preference_entity;
use totara_notification\entity\notifiable_event_user_preference as notifiable_event_user_preference_entity;
use totara_notification\model\notifiable_event_preference as notification_event_preference_model;
use totara_notification\model\notifiable_event_user_preference as notifiable_event_user_preference_model;
use totara_notification\model\notification_preference as notification_preference_model;
use totara_notification\testing\generator;
use totara_notification_mock_notifiable_event_resolver as mock_resolver;
use totara_notification_mock_scheduled_aware_event_resolver as scheduled_resolver;

class totara_notification_upgradelib_testcase extends testcase {
    /**
     * @return void
     */
    protected function setUp(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/totara/notification/db/upgradelib.php");

        $generator = generator::instance();
        $generator->include_mock_notifiable_event_resolver();
        $generator->include_mock_scheduled_aware_notifiable_event_resolver();
    }

    /**
     * @return notification_preference_model
     */
    private function create_new_notification_preference(): notification_preference_model {
        $generator = generator::instance();

        return $generator->create_notification_preference(mock_resolver::class);
    }

    /**
     * @param string $provider_name
     * @param string $provider_component
     * @param bool   $enabled
     *
     * @return void
     */
    private function set_legacy_preference_status(string $provider_name, string $provider_component, bool $enabled): void {
        $name = $provider_component . '_' . $provider_name . '_disabled';
        set_config($name, (int)(!$enabled), 'message');
    }

    /**
     * @param string $provider_name
     * @param string $provider_component
     * @param string $output
     * @param string $permitted
     *
     * @return void
     */
    private function set_legacy_preference_permissions(
        string $provider_name,
        string $provider_component,
        string $output,
        string $permitted // 'disallowed', 'permitted' or 'forced'
    ): void {
        $name = $output . '_provider_' . $provider_component . '_' . $provider_name . '_permitted';
        set_config($name, $permitted, 'message');
    }

    private function set_legacy_preference_default_outputs(
        string $provider_name,
        string $provider_component,
        string $outputs_enabled_loggedin,
        string $outputs_enabled_loggedoff
    ): void {
        $name = 'message_provider_' . $provider_component . '_' . $provider_name;
        set_config($name . '_loggedin', $outputs_enabled_loggedin, 'message');
        set_config($name . '_loggedoff', $outputs_enabled_loggedoff, 'message');
    }

    /**
     * Test that the correct outputs are enabled and disabled in the new notifiable event.
     * @return void
     */
    public function test_totara_notification_migrate_notifiable_event_prefs_with_existing_record(): void {
        $control_notifiable_event_entity = new notification_event_preference_entity();
        $control_notifiable_event_entity->resolver_class_name = mock_resolver::class;
        $control_notifiable_event_entity->context_id = context_system::instance()->id;
        $control_notifiable_event_entity->save();
        $control_notifiable_event = notification_event_preference_model::from_entity($control_notifiable_event_entity);
        $control_enabled_delivery_channels = $control_notifiable_event->get_default_delivery_channels();

        $target_notifiable_event_entity = new notification_event_preference_entity();
        $target_notifiable_event_entity->resolver_class_name = scheduled_resolver::class;
        $target_notifiable_event_entity->context_id = context_system::instance()->id;
        $target_notifiable_event_entity->save();
        $target_notifiable_event = notification_event_preference_model::from_entity($target_notifiable_event_entity);

        $this->set_legacy_preference_default_outputs(
            'alert',
            'totara_message',
            'totara_alert,msteams',
            'totara_alert,email'
        );

        totara_notification_migrate_notifiable_event_prefs(
            $target_notifiable_event->resolver_class_name,
            'alert',
            'totara_message'
        );
        $target_notifiable_event->refresh();
        $target_delivery_channels_enabled = $target_notifiable_event->get_default_delivery_channels();

        // Check that the control is unaffected.
        $control_notifiable_event->refresh();
        self::assertEquals($control_enabled_delivery_channels, $control_notifiable_event->get_default_delivery_channels());

        // Case where both loggedin and loggedoff are off.
        self::assertFalse($target_delivery_channels_enabled['popup']->is_enabled);

        // Case where both loggedin and loggedoff are on.
        self::assertTrue($target_delivery_channels_enabled['totara_alert']->is_enabled);

        // Case where loggedin is on and loggedoff is off.
        self::assertTrue($target_delivery_channels_enabled['msteams']->is_enabled);

        // Case where loggedin is off and loggedoff is on.
        self::assertTrue($target_delivery_channels_enabled['email']->is_enabled);
    }

    /**
     * @return void
     */
    public function test_totara_notification_migrate_notifiable_event_prefs_with_no_record(): void {
        $extended_context = extended_context::make_with_context(context_system::instance());

        $this->set_legacy_preference_default_outputs(
            'alert',
            'totara_message',
            'totara_alert,msteams',
            'totara_alert,email'
        );

        totara_notification_migrate_notifiable_event_prefs(
            mock_resolver::class,
            'alert',
            'totara_message'
        );
        $target_notifiable_event_entity = notification_event_preference_entity::repository()
            ->for_context(mock_resolver::class, $extended_context);
        $target_notifiable_event = notification_event_preference_model::from_entity($target_notifiable_event_entity);
        $target_delivery_channels_enabled = $target_notifiable_event->get_default_delivery_channels();

        // Check that the control is unaffected.
        $control_notifiable_event_entity = notification_event_preference_entity::repository()
            ->for_context(scheduled_resolver::class, $extended_context);
        self::assertEmpty($control_notifiable_event_entity);

        // Case where both loggedin and loggedoff are off.
        self::assertFalse($target_delivery_channels_enabled['popup']->is_enabled);

        // Case where both loggedin and loggedoff are on.
        self::assertTrue($target_delivery_channels_enabled['totara_alert']->is_enabled);

        // Case where loggedin is on and loggedoff is off.
        self::assertTrue($target_delivery_channels_enabled['msteams']->is_enabled);

        // Case where loggedin is off and loggedoff is on.
        self::assertTrue($target_delivery_channels_enabled['email']->is_enabled);
    }

    /**
     * Tests that legacy message preferences are correctly copied over to notifiable event user preferences.
     */
    public function test_totara_notification_migrate_notifiable_event_prefs_user_prefs(): void {
        // Set up.
        $user0 = self::getDataGenerator()->create_user();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();
        $user5 = self::getDataGenerator()->create_user();
        $user6 = self::getDataGenerator()->create_user();

        set_user_preferences([
            'message_provider_resolver_mock_loggedin' => '',
        ], $user0->id);

        set_user_preferences([
            'message_provider_resolver_mock_loggedin' => 'one,two',
            'message_provider_resolver_mock_loggedoff' => 'two,three',
        ], $user1->id);

        set_user_preferences([
            'message_provider_resolver_mock_loggedin' => 'one',
        ], $user2->id);

        set_user_preferences([
            'message_provider_resolver_mock_loggedoff' => 'two',
        ], $user3->id);

        set_user_preferences([
            'message_provider_resolver_mock_loggedin' => 'one',
            'message_provider_resolver_mock_loggedoff' => 'two',
        ], $user4->id);

        set_user_preferences([
            'message_provider_resolver_mock_loggedoff' => 'five',
        ], $user5->id);

        // Control records - not migrated.
        set_user_preferences([
            'message_provider_resolver_control_loggedin' => 'one,two',
            'message_provider_resolver_control_loggedoff' => 'two,three',
        ], $user3->id);

        set_user_preferences([
            'message_provider_resolver_control_loggedin' => 'six',
        ], $user6->id);

        // Do the migration (tests 2nd to 5th code paths).
        totara_notification_migrate_notifiable_event_prefs(mock_resolver::class, 'mock', 'resolver');

        // Check results.
        $preference_entities = notifiable_event_user_preference_entity::repository()->get();
        self::assertCount(6, $preference_entities);

        /** @var notifiable_event_user_preference_entity $preference_entity */
        foreach ($preference_entities as $preference_entity) {
            self::assertEquals(mock_resolver::class, $preference_entity->resolver_class_name);
            self::assertEquals(extended_context::make_system()->get_context_id(), $preference_entity->context_id);
            self::assertEquals(extended_context::NATURAL_CONTEXT_COMPONENT, $preference_entity->component);
            self::assertEquals(extended_context::NATURAL_CONTEXT_AREA, $preference_entity->area);
            self::assertEquals(extended_context::NATURAL_CONTEXT_ITEM_ID, $preference_entity->item_id);
            self::assertEquals(1, $preference_entity->enabled);
            switch ($preference_entity->user_id) {
                case $user0->id:
                    self::assertEqualsCanonicalizing([], $preference_entity->delivery_channels);
                    break;
                case $user1->id:
                    self::assertEqualsCanonicalizing(['one', 'two', 'three'], $preference_entity->delivery_channels);
                    break;
                case $user2->id:
                    self::assertEqualsCanonicalizing(['one'], $preference_entity->delivery_channels);
                    break;
                case $user3->id:
                    self::assertEqualsCanonicalizing(['two'], $preference_entity->delivery_channels);
                    break;
                case $user4->id:
                    self::assertEqualsCanonicalizing(['one', 'two'], $preference_entity->delivery_channels);
                    break;
                case $user5->id:
                    self::assertEqualsCanonicalizing(['five'], $preference_entity->delivery_channels);
                    break;
                default:
                    self::fail('Unexpected user id found');
            }
        }

        // Do the migration (tests first code path).
        totara_notification_migrate_notifiable_event_prefs(mock_resolver::class, 'control', 'resolver');

        // Check results.
        $preference_entities = notifiable_event_user_preference_entity::repository()
            ->where('user_id', $user6->id)
            ->get();
        self::assertCount(1, $preference_entities);

        /** @var notifiable_event_user_preference_entity $preference_entity */
        $preference_entity = $preference_entities->first();
        self::assertEqualsCanonicalizing(['six'], $preference_entity->delivery_channels);
    }

    /**
     * Tests that one legacy user preference is correctly migrated with the correct values.
     */
    public function test_totara_notification_migrate_notification_user_pref_with_one_legacy_preference(): void {
        // Set up.
        $user = self::getDataGenerator()->create_user();

        $legacy_preference = new stdClass();
        $legacy_preference->userid = $user->id;
        $legacy_preference->value = 'one,two';

        // Do the migration.
        totara_notification_migrate_notification_user_pref(
            mock_resolver::class,
            $legacy_preference
        );

        // Check results.
        $preference_entities = notifiable_event_user_preference_entity::repository()->get();
        self::assertCount(1, $preference_entities);
        /** @var notifiable_event_user_preference_entity $preference_entity */
        $preference_entity = $preference_entities->first();
        self::assertEquals(mock_resolver::class, $preference_entity->resolver_class_name);
        self::assertEquals($user->id, $preference_entity->user_id);
        self::assertEquals(extended_context::make_system()->get_context_id(), $preference_entity->context_id);
        self::assertEquals(extended_context::NATURAL_CONTEXT_COMPONENT, $preference_entity->component);
        self::assertEquals(extended_context::NATURAL_CONTEXT_AREA, $preference_entity->area);
        self::assertEquals(extended_context::NATURAL_CONTEXT_ITEM_ID, $preference_entity->item_id);
        self::assertEquals(1, $preference_entity->enabled);
        self::assertEqualsCanonicalizing(['one', 'two'], $preference_entity->delivery_channels);
    }

    /**
     * Tests that two legacy user preferences (loggedin and loggedoff) are correctly combined into one notification preference.
     */
    public function test_totara_notification_migrate_notification_user_pref_with_two_legacy_preferences(): void {
        // Set up.
        $user = self::getDataGenerator()->create_user();

        $legacy_preference1 = new stdClass();
        $legacy_preference1->userid = $user->id;
        $legacy_preference1->name = 'pref_loggedin';
        $legacy_preference1->value = 'one,two';

        $legacy_preference2 = new stdClass();
        $legacy_preference2->userid = $user->id;
        $legacy_preference2->name = 'pref_loggedoff';
        $legacy_preference2->value = 'two,three';

        // Do the migration.
        totara_notification_migrate_notification_user_pref(
            mock_resolver::class,
            $legacy_preference1,
            $legacy_preference2
        );

        // Check results.
        $preference_entities = notifiable_event_user_preference_entity::repository()->get();
        self::assertCount(1, $preference_entities);
        /** @var notifiable_event_user_preference_entity $preference_entity */
        $preference_entity = $preference_entities->first();
        self::assertEquals(mock_resolver::class, $preference_entity->resolver_class_name);
        self::assertEquals($user->id, $preference_entity->user_id);
        self::assertEquals(extended_context::make_system()->get_context_id(), $preference_entity->context_id);
        self::assertEquals(extended_context::NATURAL_CONTEXT_COMPONENT, $preference_entity->component);
        self::assertEquals(extended_context::NATURAL_CONTEXT_AREA, $preference_entity->area);
        self::assertEquals(extended_context::NATURAL_CONTEXT_ITEM_ID, $preference_entity->item_id);
        self::assertEquals(1, $preference_entity->enabled);
        self::assertEqualsCanonicalizing(['one', 'two', 'three'], $preference_entity->delivery_channels);
    }

    /**
     * Tests that one legacy user preference is correctly migrated with the correct values.
     */
    public function test_totara_notification_migrate_notification_user_pref_with_existing_new_preference(): void {
        // Set up.
        $user = self::getDataGenerator()->create_user();

        $legacy_preference = new stdClass();
        $legacy_preference->userid = $user->id;
        $legacy_preference->value = 'one,two';

        notifiable_event_user_preference_model::create(
            $user->id,
            mock_resolver::class,
            extended_context::make_system(),
            true,
            ['two', 'three']
        );

        // Do the migration.
        totara_notification_migrate_notification_user_pref(
            mock_resolver::class,
            $legacy_preference
        );

        // Check results.
        $preference_entities = notifiable_event_user_preference_entity::repository()->get();
        self::assertCount(1, $preference_entities);
        /** @var notifiable_event_user_preference_entity $preference_entity */
        $preference_entity = $preference_entities->first();
        self::assertEquals(mock_resolver::class, $preference_entity->resolver_class_name);
        self::assertEquals($user->id, $preference_entity->user_id);
        self::assertEquals(extended_context::make_system()->get_context_id(), $preference_entity->context_id);
        self::assertEquals(extended_context::NATURAL_CONTEXT_COMPONENT, $preference_entity->component);
        self::assertEquals(extended_context::NATURAL_CONTEXT_AREA, $preference_entity->area);
        self::assertEquals(extended_context::NATURAL_CONTEXT_ITEM_ID, $preference_entity->item_id);
        self::assertEquals(1, $preference_entity->enabled);
        self::assertEqualsCanonicalizing(['one', 'two', 'three'], $preference_entity->delivery_channels);
    }

    /**
     * Test that the enabled/disabled legacy notification preference results in a new notification that is enabled or disabled.
     */
    public function test_totara_notification_migrate_notification_prefs_status(): void {
        $control_notif_preference = $this->create_new_notification_preference();
        $control_enabled = $control_notif_preference->get_enabled();

        $new_notif_preference = $this->create_new_notification_preference();

        // Case where legacy notification is enabled.
        $this->set_legacy_preference_status('alert', 'totara_message', true);
        totara_notification_migrate_notification_prefs(
            $new_notif_preference->get_id(),
            'alert',
            'totara_message'
        );
        $new_notif_preference->refresh();
        self::assertTrue($new_notif_preference->get_enabled());

        // The control is unaffected.
        $control_notif_preference->refresh();
        self::assertEquals($control_enabled, $control_notif_preference->get_enabled());

        // Case where legacy notification is disabled.
        $this->set_legacy_preference_status('alert', 'totara_message', false);
        totara_notification_migrate_notification_prefs(
            $new_notif_preference->get_id(),
            'alert',
            'totara_message'
        );
        $new_notif_preference->refresh();
        self::assertFalse($new_notif_preference->get_enabled());

        // The control is unaffected.
        $control_notif_preference->refresh();
        self::assertEquals($control_enabled, $control_notif_preference->get_enabled());
    }

    /**
     * Test that a legacy notification that is 'locked' results in forced delivery in the new notification.
     * Also tests that 'disallowed' and 'permitted' have no effect.
     */
    public function test_totara_notification_migrate_notification_prefs_permissions(): void {
        $control_notif_preference = $this->create_new_notification_preference();
        $control_forced_delivery_channels = $control_notif_preference->get_forced_delivery_channels();
        $new_notif_preference = $this->create_new_notification_preference();

        $this->set_legacy_preference_permissions(
            'alert',
            'totara_message',
            'totara_alert',
            'forced'
        );

        $this->set_legacy_preference_permissions(
            'alert',
            'totara_message',
            'email',
            'disallowed'
        );

        $this->set_legacy_preference_permissions(
            'alert',
            'totara_message',
            'popup',
            'permitted'
        );

        totara_notification_migrate_notification_prefs(
            $new_notif_preference->get_id(),
            'alert',
            'totara_message'
        );
        $new_notif_preference->refresh();
        $forcer_delivery_channels = $new_notif_preference->get_forced_delivery_channels();

        // The control is unaffected.
        $control_notif_preference->refresh();
        self::assertEquals($control_forced_delivery_channels, $control_notif_preference->get_forced_delivery_channels());

        // Check that a forced legacy output results in forced delivery in the new notification.
        self::assertContains('totara_alert', $forcer_delivery_channels);

        // Check that a permitted legacy output results in no forced delivery in the new notification.
        self::assertNotContains('email', $forcer_delivery_channels);

        // Check that a disabled legacy output results in no forced delivery in the new notification.
        self::assertNotContains('popup', $forcer_delivery_channels);
    }
}