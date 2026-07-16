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
 * @author matthias.bonk@totaralearning.com
 * @package core
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/login/lib.php');

class core_login_lib_testcase extends advanced_testcase {

    public function test_core_login_email_exists_multiple_times(): void {
        global $CFG;

        $CFG->allowaccountssameemail = 1;

        self::getDataGenerator()->create_user([
            'email' => 'unique.email@example.com',
        ]);
        self::getDataGenerator()->create_user([
            'email' => 'duplicate.email@example.com',
        ]);
        self::getDataGenerator()->create_user([
            'email' => 'DupLiCATE.email@example.com',
        ]);

        self::assertTrue(core_login_email_exists_multiple_times('duplicate.email@example.com'));
        self::assertTrue(core_login_email_exists_multiple_times('DUPLICATE.EMAIL@EXAMPLE.COM'));
        self::assertFalse(core_login_email_exists_multiple_times('unique.email@example.com'));
        self::assertFalse(core_login_email_exists_multiple_times('nonexistent.email@example.com'));
        self::assertFalse(core_login_email_exists_multiple_times(''));
        self::assertFalse(core_login_email_exists_multiple_times('   '));
    }
}
