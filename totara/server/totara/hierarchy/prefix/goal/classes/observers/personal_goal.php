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
 * @author Matthias Bonk <matthias.bonk@totaralearning.com>
 * @package hierarchy_goal
 */

namespace hierarchy_goal\observers;

use core\event\base;
use goal;
use hierarchy_goal\entity\goal_item_target_date_history as goal_item_target_date_history_entity;
use hierarchy_goal\event\personal_created;
use hierarchy_goal\event\personal_updated;
use hierarchy_goal\models\goal_item_target_date_history;
use stdClass;

class personal_goal {

    /**
     * @param base|personal_created $event
     */
    public static function add_target_date_history_personal_created(base $event): void {
        // Get the new goal item out of the event
        $snapshot = $event->get_record_snapshot($event->objecttable, $event->objectid);
        $target_date = $snapshot->targetdate ?? null;
        goal_item_target_date_history::create(goal::SCOPE_PERSONAL, $event->objectid, $target_date);
    }

    /**
     * @param base|personal_updated $event
     */
    public static function add_target_date_history_personal_updated(base $event): void {
        // Get the changed goal item out of the event
        $snapshot = $event->get_record_snapshot($event->objecttable, $event->objectid);

        // Only if the target date changed create a history record.
        if (self::has_target_date_changed($event, $snapshot)) {
            goal_item_target_date_history::create(goal::SCOPE_PERSONAL, $event->objectid, $snapshot->targetdate);
        }
    }

    /**
     * Check if the target date changed.
     *
     * @param base $event
     * @param stdClass $snapshot already updated goal item
     * @return bool
     */
    private static function has_target_date_changed(base $event, stdClass $snapshot): bool {
        if (!property_exists($snapshot, 'targetdate')) {
            return false;
        }

        /** @var goal_item_target_date_history_entity $most_recent_target_date_history */
        $most_recent_target_date_history = goal_item_target_date_history_entity::repository()
            ->where('scope', goal::SCOPE_PERSONAL)
            ->where('itemid', $event->objectid)
            ->order_by('id', 'DESC')
            ->first();

        if (!$most_recent_target_date_history) {
            // No history recorded yet.
            return true;
        }

        /*
         * Even though targetdate is nullable in the goal tables, integer zero seems to be always used to represent "No target date".
         * To be safe, we regard zero and null as different values for recording the history. Just in case there is custom code
         * that does differentiate between null and zero.
         */
        $old_targetdate = is_null($most_recent_target_date_history->targetdate) ? null : (int)$most_recent_target_date_history->targetdate;
        $new_targetdate = is_null($snapshot->targetdate) ? null : (int)$snapshot->targetdate;

        return $old_targetdate !== $new_targetdate;
    }
}
