<?php
/*
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
 * @author Sam Hemelryk <sam.hemelryk@totaralearning.com>
 * @package totara_job
 */

namespace totara_job\webapi\resolver;

/**
 * Helper trait containing functions useful to the job resolvers
 */
trait helper {

    /**
     * Returns a user given its ID in the args array.
     * @param array $args
     * @param string $name
     * @param bool $defaulttocurrent
     * @return \stdClass
     */
    private static function get_user_from_args(array $args, string $name = 'userid', bool $defaulttocurrent = true) {
        global $DB, $USER;
        $userid = $args[$name] ?? null;
        if ($userid === null) {
            if ($defaulttocurrent) {
                return $USER;
            }
            throw new \moodle_exception('missingparam', '', '', $name, join(',', array_keys($args)));
        }
        if ($USER->id == $userid) {
            return $USER;
        }
        return $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
    }
}