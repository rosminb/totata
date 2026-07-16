<?php
/*
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralearning.com>
 * @package totara_mobile
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Test GraphQL resolver of mobile queries
 */
class totara_mobile_webapi_resolver_query_language_strings_testcase extends advanced_testcase {
    /**
     * Test the results of the embedded mobile query through the GraphQL stack.
     */
    public function test_embedded_query() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $result = \totara_webapi\graphql::execute_operation(
            \core\webapi\execution_context::create('mobile', 'totara_mobile_language_strings'),
            ['lang' => 'en']
        );

        $data = $result->toArray()['data'];

        $this->assertNotEmpty($data['json_string']);
        $strings = json_decode($data['json_string']);
        $this->assertNotEmpty($strings);
    }
}