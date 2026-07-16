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

use core\orm\query\builder;
use core_phpunit\testcase;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use totara_oauth2\entity\access_token;
use totara_oauth2\facade\response_interface;
use totara_oauth2\grant_type;
use totara_oauth2\io\request;
use totara_oauth2\server;
use totara_oauth2\testing\generator;

/**
 * @group totara_oauth2
 */
class totara_oauth2_server_testcase extends testcase {
    /**
     * @return void
     */
    protected function setUp(): void {
        generator::setup_required_configuration();
    }

    /**
     * @return void
     */
    public function test_request_token(): void {
        $generator = generator::instance();
        $client = $generator->create_client_provider();

        $server = server::create();
        $request = request::create_from_global(
            [],
            [
                "grant_type" => grant_type::get_client_credentials(),
                "client_id" => $client->client_id,
                "client_secret" => $client->client_secret
            ],
            [],
            ["REQUEST_METHOD" => "POST"]
        );

        $db = builder::get_db();
        self::assertEquals(0, $db->count_records(access_token::TABLE, ["client_provider_id" => $client->id]));

        // Once response is being processed, then we will get a new record of access token.
        $response = $server->handle_token_request($request);
        self::assertEquals(1, $db->count_records(access_token::TABLE, ["client_provider_id" => $client->id]));

        self::assertInstanceOf(response_interface::class, $response);
        $body = $response->getBody()->__toString();
        $parameters = json_decode($body, true);

        self::assertIsArray($parameters);

        self::assertArrayHasKey("access_token", $parameters);
        self::assertNotNull($parameters["access_token"]);

        $jwt_configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText('')
        );

        $token = $jwt_configuration->parser()->parse($parameters["access_token"]);
        $token_entity = access_token::repository()->find_by_identifier($token->claims()->get("jti"));

        self::assertNotNull($token_entity);

        self::assertArrayHasKey("token_type", $parameters);
        self::assertEquals("Bearer", $parameters["token_type"]);
    }

    /**
     * @return void
     */
    public function test_verify_token(): void {
        $generator = generator::instance();
        $time_now = time();

        $client = $generator->create_client_provider();
        $token = $generator->create_access_token_from_client_provider($client, $time_now + HOURSECS);

        $request = request::create_from_global(
            [],
            [],
            ["AUTHORIZATION" => "Bearer {$token}"],
        );

        $server = server::create($time_now);
        $result = $server->is_request_verified($request);

        self::assertTrue($result);
    }

    /**
     * @return void
     */
    public function test_cannot_verify_token_due_to_expired(): void {
        $time_now = time();
        $generator = generator::instance();

        $client = $generator->create_client_provider();
        $token = $generator->create_access_token_from_client_provider($client, $time_now - HOURSECS);

        $request = request::create_from_global(
            [],
            [],
            ["AUTHORIZATION" => "Bearer {$token}"]
        );

        $server = server::create($time_now + (HOURSECS * 2));
        $result = $server->is_request_verified($request);

        self::assertFalse($result);
    }
}