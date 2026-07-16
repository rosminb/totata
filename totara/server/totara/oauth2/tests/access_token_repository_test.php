<?php
/**
 * This file is part of Totara Core
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
 * @package totara_oauth2
 */
use core_phpunit\testcase;
use totara_oauth2\entity\access_token;
use totara_oauth2\testing\generator;

/**
 * @group totara_oauth2
 */
class totara_oauth2_access_token_repository_testcase extends testcase {
    /**
     * @return void
     */
    public function test_find_access_token_without_providing_time_now(): void {
        $generator = generator::instance();
        $created_token = $generator->create_access_token(null, ["access_token" => "hello_world"]);

        $repository = access_token::repository();
        $result_one = $repository->find_by_identifier("hello_world");

        self::assertEquals($created_token->getIdentifier(), $result_one->identifier);
        self::assertEquals($created_token->getClient()->getIdentifier(), $result_one->client_provider->client_id);
        self::assertEquals($created_token->getExpiryDateTime()->getTimestamp(), $result_one->expires);
        self::assertNull($result_one->scope);
    }

    /**
     * @return void
     */
    public function test_find_access_token_with_strict(): void {
        $generator = generator::instance();
        $generator->create_access_token(null, ["expires" => time() + HOURSECS]);

        $this->expectException(dml_missing_record_exception::class);

        $repository = access_token::repository();
        $repository->find_by_identifier("something_not_existing", true);
    }
}