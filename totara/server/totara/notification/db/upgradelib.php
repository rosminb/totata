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
 * @author  Kian Nguyen <kian.nguyen@totaralearning.com>
 * @package totara_notification
 */
defined('MOODLE_INTERNAL') || die();

use totara_core\extended_context;
use totara_notification\factory\built_in_notification_factory;
use totara_notification\notification\built_in_notification;

/**
 * A helper function to sync up any new built-in notification that are introduced within a component
 * in a system context.
 *
 * This function will try to invoke several APIs from the production code, which it should be discourage.
 * However, since its all encapsulated in this very function, hence when those APIs are deprecated/upgraded,
 * then this function can be tweaked to reflect the changes.
 *
 * Use this function in your upgrade step, when you are introducing a new built-in notification and want to
 * sync up with the database.
 *
 * Here are the list of static function that we are trying to invoke:
 * + @see built_in_notification::get_resolver_class_name()
 *
 * Note: PLEASE DO NOT DELETE THIS FUNCTION EVEN WHEN IT IS NOT USED IN upgrade.php FILE !!!
 *
 * @param string|null $component If this is null, then we are sync all the built-in notifications within the system.
 * @return void
 */
function totara_notification_sync_built_in_notification(?string $component = null): void {
    global $DB;

    // At this point, the context system should had been created.
    $context_system = context_system::instance();
    $notification_classes = built_in_notification_factory::get_notification_classes($component);

    if (empty($notification_classes)) {
        return;
    }

    foreach ($notification_classes as $notification_class) {
        $resolver_class_name = call_user_func([$notification_class, 'get_resolver_class_name']);
        $search_params = [
            'context_id' => $context_system->id,
            'resolver_class_name' => $resolver_class_name,
            'notification_class_name' => $notification_class,
        ];

        if ($DB->record_exists('notification_preference', $search_params)) {
            // Skip the records that are already existing in the system.
            continue;
        }

        $record = new stdClass();
        $record->resolver_class_name = $resolver_class_name;
        $record->context_id = $context_system->id;
        $record->component = extended_context::NATURAL_CONTEXT_COMPONENT;
        $record->area = extended_context::NATURAL_CONTEXT_AREA;
        $record->item_id = extended_context::NATURAL_CONTEXT_ITEM_ID;
        $record->notification_class_name = $notification_class;
        $record->time_created = time();

        $DB->insert_record('notification_preference', $record);
    }
}

/**
 * Migrates legacy notification preferences to new notifiable event configuration.
 *
 * !!! WARNING !!! This function should only be called immediately after a new notifiable event resolver
 *                 has been installed. It resets notifiable event preferences to those specified by the
 *                 legacy message provider.
 *
 * New notifiable event default outputs come from legacy notification default outputs.
 * New notification event status is NOT affected by legacy notification status.
 * New notification user output preferences come from legacy notification user output preferences.
 *
 * @param string $resolver_class_name
 * @param string $provider_name
 * @param string $provider_component
 */
function totara_notification_migrate_notifiable_event_prefs(
    string $resolver_class_name,
    string $provider_name,
    string $provider_component
) {
    global $DB;

    // Default outputs.
    $name = 'message_provider_' . $provider_component . '_' . $provider_name;
    $outputs_enabled_loggedin = explode(',', get_config('message', $name . '_loggedin'));
    $outputs_enabled_loggedin = $outputs_enabled_loggedin === false ? [] : $outputs_enabled_loggedin;
    $outputs_enabled_loggedoff = explode(',', get_config('message', $name . '_loggedoff'));
    $outputs_enabled_loggedoff = $outputs_enabled_loggedoff === false ? [] : $outputs_enabled_loggedoff;
    $outputs_enabled = array_unique(array_merge($outputs_enabled_loggedoff, $outputs_enabled_loggedin));

    $record = $DB->get_record('notifiable_event_preference', [
        'resolver_class_name' => ltrim($resolver_class_name, '\\'),
        'context_id' => context_system::instance()->id,
        'component' => extended_context::NATURAL_CONTEXT_COMPONENT,
        'area' => extended_context::NATURAL_CONTEXT_AREA,
        'item_id' => extended_context::NATURAL_CONTEXT_ITEM_ID,
    ], 'id, default_delivery_channels');

    $default_delivery_channels = ',' . implode(',', $outputs_enabled) . ',';

    if (empty($record)) {
        $record = [
            'resolver_class_name' => ltrim($resolver_class_name, '\\'),
            'context_id' => context_system::instance()->id,
            'component' => extended_context::NATURAL_CONTEXT_COMPONENT,
            'area' => extended_context::NATURAL_CONTEXT_AREA,
            'item_id' => extended_context::NATURAL_CONTEXT_ITEM_ID,
            'default_delivery_channels' => $default_delivery_channels,
        ];
        $DB->insert_record('notifiable_event_preference', $record);
    } else {
        $record->default_delivery_channels = $default_delivery_channels;
        $DB->update_record('notifiable_event_preference', $record);
    }

    // User preferences.
    $preferences_loggedin = $DB->get_recordset('user_preferences',  [
        'name' => 'message_provider_' . $provider_component . '_' . $provider_name . '_loggedin',
    ], 'userid', 'userid, name, value');
    $preferences_loggedoff = $DB->get_recordset('user_preferences',  [
        'name' => 'message_provider_' . $provider_component . '_' . $provider_name . '_loggedoff',
    ], 'userid', 'userid, name, value');

    while ($preferences_loggedin->valid() || $preferences_loggedoff->valid()) {
        if (!$preferences_loggedoff->valid()) {
            // Only logged-in is present, so just process that.
            totara_notification_migrate_notification_user_pref(
                $resolver_class_name,
                $preferences_loggedin->current()
            );
            $preferences_loggedin->next();
            continue;
        }

        if (!$preferences_loggedin->valid()) {
            // Only logged-off is present, so just process that.
            totara_notification_migrate_notification_user_pref(
                $resolver_class_name,
                $preferences_loggedoff->current()
            );
            $preferences_loggedoff->next();
            continue;
        }

        // Both records must be present.
        $preference_loggedin = $preferences_loggedin->current();
        $preference_loggedoff = $preferences_loggedoff->current();

        if ($preference_loggedin->userid === $preference_loggedoff->userid) {
            // Both records relate to the same user, so we combine their results (OR).
            totara_notification_migrate_notification_user_pref(
                $resolver_class_name,
                $preference_loggedin,
                $preference_loggedoff
            );
            $preferences_loggedin->next();
            $preferences_loggedoff->next();
            continue;
        }

        // There is a mismatch, so we process the lower userid only. The other record might get a match next time around.
        if ($preference_loggedin->userid < $preference_loggedoff->userid) {
            totara_notification_migrate_notification_user_pref(
                $resolver_class_name,
                $preference_loggedin
            );
            $preferences_loggedin->next();
        } else {
            totara_notification_migrate_notification_user_pref(
                $resolver_class_name,
                $preference_loggedoff
            );
            $preferences_loggedoff->next();
        }
    }

    $preferences_loggedin->close();
    $preferences_loggedoff->close();
}

/**
 * Converts one or two legacy message user preferences into a new notification user preference
 *
 * This function is used by totara_notification_migrate_notifiable_event_prefs and should not be
 * used elsewhere.
 *
 * If two records are provided then the delivery channels from both are combined.
 *
 * @param string $resolver_class_name
 * @param stdClass $preference1
 * @param stdClass|null $preference2
 */
function totara_notification_migrate_notification_user_pref(
    string $resolver_class_name,
    stdClass $preference1,
    stdClass $preference2 = null
) {
    global $DB;

    if (!empty($preference2)) {
        if ($preference1->userid != $preference2->userid) {
            throw new coding_exception(
                'When two preferences are provided to totara_notification_migrate_notification_user_pref they must match'
            );
        }
        // Check that the two records match.
        $preference1_name = str_replace('_loggedin', '', str_replace('_loggedoff', '', $preference1->name));
        $preference2_name = str_replace('_loggedin', '', str_replace('_loggedoff', '', $preference2->name));
        if ($preference1_name !== $preference2_name) {
            throw new coding_exception(
                'When two preferences are provided to totara_notification_migrate_notification_user_pref they must match'
            );
        }

        // Combine the two preferences (OR), and remove duplicates.
        $delivery_channels_list = array_unique(array_merge(
            explode(',', $preference1->value),
            explode(',', $preference2->value)
        ));
        $delivery_channels = implode(',', $delivery_channels_list);
    } else {
        $delivery_channels = $preference1->value;
    }

    $record = $DB->get_record('notifiable_event_user_preference', [
        'resolver_class_name' => ltrim($resolver_class_name, '\\'),
        'user_id' => $preference1->userid,
        'context_id' => context_system::instance()->id,
        'component' => extended_context::NATURAL_CONTEXT_COMPONENT,
        'area' => extended_context::NATURAL_CONTEXT_AREA,
        'item_id' => extended_context::NATURAL_CONTEXT_ITEM_ID,
    ], 'id, delivery_channels');

    if (empty($record)) {
        $record = [
            'resolver_class_name' => ltrim($resolver_class_name, '\\'),
            'user_id' => $preference1->userid,
            'context_id' => context_system::instance()->id,
            'component' => extended_context::NATURAL_CONTEXT_COMPONENT,
            'area' => extended_context::NATURAL_CONTEXT_AREA,
            'item_id' => extended_context::NATURAL_CONTEXT_ITEM_ID,
            'enabled' => true,
            'delivery_channels' => ',' . $delivery_channels . ',',
        ];
        $DB->insert_record('notifiable_event_user_preference', $record);
    } else {
        // Combine the legacy and new preferences (OR), and remove duplicates.
        $delivery_channels_list = array_unique(array_merge(
            explode(',', $delivery_channels),
            explode(',', $record->delivery_channels)
        ));
        $delivery_channels = implode(',', $delivery_channels_list);

        $record->delivery_channels = ',' . $delivery_channels . ',';
        $DB->update_record('notifiable_event_user_preference', $record);
    }
}

/**
 * Migrates legacy notification preferences to new notification preferences.
 *
 * New notification preference forced delivery is determined by legacy notification permissions.
 * New notification preference status comes from legacy notification status.
 *
 * @param int $notification_preference_id
 * @param string $provider_name
 * @param string $provider_component
 */
function totara_notification_migrate_notification_prefs(
    int $notification_preference_id,
    string $provider_name,
    string $provider_component
) {
    global $DB;

    // Normally we would only look at enabled and existing processors, but for migration we will take everything.
    $processors = $DB->get_records('message_processors', null, 'name DESC', 'name, id, enabled');

    // Migrate status.
    $name = $provider_component . '_' . $provider_name . '_disabled';
    $disabled = get_config('message', $name);

    // Migrate permissions to forced delivery.
    $forced_delivery_channels = [];
    foreach ($processors as $processor) {
        $name = $processor->name . '_provider_' . $provider_component . '_' . $provider_name . '_permitted';
        $permitted = get_config('message', $name);
        if ($permitted === 'forced') {
            $forced_delivery_channels[] = $processor->name;
        }
    }

    $record = $DB->get_record('notification_preference', [
        'id' => $notification_preference_id,
    ], 'id', MUST_EXIST);
    $record->enabled = !$disabled;
    $record->forced_delivery_channels = json_encode($forced_delivery_channels);
    $DB->update_record('notification_preference', $record);
}
