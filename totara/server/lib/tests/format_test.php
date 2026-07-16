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
 * @author Fabian Derschatta <fabian.derschatta@totaralearning.com>
 * @package core
 */

use core\format;

defined('MOODLE_INTERNAL') || die();

class core_format_testcase extends basic_testcase {

    public function test_constants() {
        $reflection = new ReflectionClass(format::class);
        $constants = $reflection->getConstants();

        $format_constants = [];
        foreach ($constants as $constant => $value) {
            if (strpos($constant, 'FORMAT_') === 0) {
                $format_constants[] = $value;
                $this->assertTrue(format::is_defined($value), "Format $value not defined?");
            }
        }

        // All constants should be part of the available array
        $this->assertEqualsCanonicalizing($format_constants, format::get_available());
    }

}
