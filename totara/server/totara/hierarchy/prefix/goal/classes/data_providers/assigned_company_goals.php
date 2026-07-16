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
 * @author Murali Nair <murali.nair@totaralearning.com>
 * @package hierarchy_goal
 */

namespace hierarchy_goal\data_providers;

use Closure;

use core\orm\entity\repository;

use hierarchy_goal\entity\company_goal;
use hierarchy_goal\entity\company_goal_assignment;

/**
 * Handles company goal assignments.
 */
class assigned_company_goals {
    // Mapping of sort field display names to physical entity _columns_.
    public const SORT_FIELDS = [
        'assignment_id' => 'id',
        'user_id' => 'userid',
        'goal_id' => 'goalid',
        'goal_name' => company_goal::TABLE . '.fullname'
    ];

    /**
     * Creates an instance of the data provider.
     *
     * @return goal_data_provider the dataprovider.
     */
    public static function create(): goal_data_provider {
        return new goal_data_provider(
            company_goal_assignment::class,
            self::SORT_FIELDS,
            'hierarchy_goal\entity\filters\company_goal_assignment_filters::for',
            Closure::fromCallable([self::class, 'repo_factory'])
        );
    }

    /**
     * Company goal assignment repository factory.
     *
     * @return repository the repository.
     */
    private static function repo_factory(): repository {
        // The join with the goal table is needed so that results can be filtered
        // and ordered by goal names.
        return company_goal_assignment::repository()
            ->join(company_goal::TABLE, 'goalid', '=', 'id')
            ->select(company_goal_assignment::TABLE . '.*')
            ->add_select(company_goal::TABLE . '.fullname as goal_fullname');
    }
}
