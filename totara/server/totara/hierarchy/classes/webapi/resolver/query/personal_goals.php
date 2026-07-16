<?php
/*
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
 * @author Murali Nair <murali.nair@totaralearning.com>
 * @package totara_hierarchy
 */

namespace totara_hierarchy\webapi\resolver\query;

use coding_exception;
use context_system;
use context_user;
use core\entity\user;

use core\pagination\cursor;
use core\webapi\execution_context;
use core\webapi\query_resolver;
use core\webapi\middleware\require_advanced_feature;
use core\webapi\middleware\require_login;
use core\webapi\resolver\has_middleware;

use hierarchy_goal\personal_goal_assignment_type;
use hierarchy_goal\data_providers\goal_data_provider;
use hierarchy_goal\data_providers\personal_goals as personal_goals_provider;
use required_capability_exception;
use totara_core\hook\component_access_check;

/**
 * Handles the "totara_hierarchy_personal_goals" GraphQL query.
 */
class personal_goals implements query_resolver, has_middleware {
    /**
     * {@inheritdoc}
     */
    public static function resolve(array $args, execution_context $ec) {
        $input = $args['input'] ?? [];
        $logged_on_user_id = user::logged_in()->id;

        $filters = $input['filters'] ?? [];
        $target_user_id = $filters['user_id'] ?? null;
        if (!$target_user_id) {
            $target_user_id = $logged_on_user_id;
            $filters['user_id'] = $target_user_id;
        }
        self::authorize($logged_on_user_id, $target_user_id);

        $raw_order_by = strtolower($input['order_by'] ?? 'goal_name');
        $order_by = personal_goals_provider::SORT_FIELDS[$raw_order_by] ?? null;
        if (!$order_by) {
            throw new coding_exception("unknown sort order: $raw_order_by");
        }

        $order_dir = $input['order_dir'] ?? 'ASC';
        $result_size = $input['result_size'] ?? goal_data_provider::DEFAULT_PAGE_SIZE;

        $enc_cursor = $input['cursor'] ?? null;
        $cursor = $enc_cursor ? cursor::decode($enc_cursor) : null;

        $assignment_type = $filters['assignment_type'] ?? null;
        if ($assignment_type) {
            $filters['assignment_type'] = personal_goal_assignment_type::by_name($assignment_type)
                ->get_value();
        }
        $filters['deleted'] = $filters['deleted'] ?? false;

        return personal_goals_provider::create()
            ->set_page_size($result_size)
            ->set_filters($filters)
            ->set_order($order_by, $order_dir)
            ->fetch_paginated($cursor);
    }

    /**
     * Checks the user's authorization.
     *
     * @param int $logged_on_user_id currently logged on user.
     * @param int $target_user_id user whose goals are to be retrieved.
     */
    private static function authorize(int $logged_on_user_id, int $target_user_id): void {
        if (has_capability('totara/hierarchy:viewallgoals', context_system::instance())) {
            return;
        }

        if ($logged_on_user_id === $target_user_id) {
            $context = context_user::instance($logged_on_user_id);
            $capability = 'totara/hierarchy:viewownpersonalgoal';
        } else {
            $context = context_user::instance($target_user_id);
            $capability = 'totara/hierarchy:viewstaffpersonalgoal';
        }
        if (!has_capability($capability, $context)) {
            $hook = new component_access_check(
                'hierarchy_goal',
                $logged_on_user_id,
                $target_user_id,
                ['content_type' => 'personal_goal']
            );
            if (!$hook->execute()->has_permission()) {
                throw new required_capability_exception($context, $capability, 'nopermissions', '');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function get_middleware(): array {
        return [
            new require_advanced_feature('goals'),
            new require_login()
        ];
    }
}
