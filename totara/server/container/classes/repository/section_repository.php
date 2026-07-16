<?php
/**
 * This file is part of Totara Learn
 *
 * Copyright (C) 2019 onwards Totara Learning Solutions LTD
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
 * @author Kian Nguyen <kian.nguyen@totaralearning.com>
 * @package core_container
 */
namespace core_container\repository;

use core\orm\entity\repository;
use core\orm\query\builder;
use core_container\entity\section;

/**
 * Repository for section
 */
final class section_repository extends repository {
    /**
     * @param int $course
     * @param int $sectionnumber
     * @param bool $strict
     *
     * @return section|null
     */
    public function find_by_section_number_and_course(int $course, int $sectionnumber, bool $strict = true): ?section {
        $builder = builder::table($this->get_table());
        $builder->where('section', $sectionnumber);
        $builder->where('course', $course);

        $builder->map_to(section::class);

        /** @var section|null $section */
        $section = $builder->one($strict);
        return $section;
    }
}