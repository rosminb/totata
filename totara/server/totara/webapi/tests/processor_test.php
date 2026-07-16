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
 * @author Kunle Odusan <kunle.odusan@totaralearning.com>
 */

use core\webapi\execution_context;
use core_phpunit\testcase;
use GraphQL\Error\DebugFlag;
use GraphQL\Executor\ExecutionResult;
use totara_webapi\processor;
use totara_webapi\graphql;
use totara_webapi\request;

class totara_webapi_processor_testcase extends testcase {

    public function test_handle_successful_request() {
        $processor = processor::instance(execution_context::create(graphql::TYPE_AJAX, 'totara_webapi_status_nosession'));

        $request_params = [
            'operationName' => 'totara_webapi_status_nosession',
            'variables' => []
        ];
        $request = new request(graphql::TYPE_AJAX, $request_params);

        $result = $processor->process_request($request);
        $this->assertInstanceOf(ExecutionResult::class, $result);

        $result = $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE);
        $this->assertArrayHasKey('data', $result);
        $data = $result['data'];
        $this->assertArrayHasKey('totara_webapi_status',  $data);
        $data = $data['totara_webapi_status'];
        $this->assertEquals('ok',  $data['status']);
        $this->assertArrayHasKey('status',  $data);
        $this->assertEquals('ok',  $data['status']);
        $this->assertArrayHasKey('timestamp',  $data);
        $this->assertGreaterThan(0,  $data['timestamp']);
    }

    public function test_batched_queries() {
        $processor = processor::instance(execution_context::create(graphql::TYPE_AJAX));

        $request_params = [
            [
                'operationName' => 'totara_webapi_status_nosession',
                'variables' => []
            ],
            [
                'operationName' => 'totara_webapi_status_nosession',
                'variables' => []
            ],
            [
                'operationName' => 'totara_webapi_status_nosession',
                'variables' => []
            ],
        ];
        $request = new request(graphql::TYPE_AJAX, $request_params);

        $results = $processor->process_request($request);
        $this->assertIsArray($results);
        $this->assertContainsOnlyInstancesOf(ExecutionResult::class, $results);
        $this->assertCount(3, $results);

        foreach ($results as $result) {
            $result = $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE);
            $this->assertArrayHasKey('data', $result);
            $data = $result['data'];
            $this->assertArrayHasKey('totara_webapi_status', $data);
            $data = $data['totara_webapi_status'];
            $this->assertEquals('ok',  $data['status']);
            $this->assertArrayHasKey('status',  $data);
            $this->assertEquals('ok',  $data['status']);
            $this->assertArrayHasKey('timestamp',  $data);
            $this->assertGreaterThan(0,  $data['timestamp']);

            $this->assertArrayNotHasKey('extensions', $result);
        }
    }

    public function test_invalid_type() {
        $types = graphql::get_available_types();

        foreach ($types as $type) {
            // This should not throw an exception
            processor::instance(execution_context::create($type));
        }

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Invalid webapi type given');
        processor::instance(execution_context::create('foobar'));
    }

    public function test_execute_introspection_query() {
        $processor = processor::instance(execution_context::create(graphql::TYPE_DEV));

        $request_params = [
            'query' => self::get_introspection_query(),
            'variables' => [],
            'operationName' => null
        ];
        $request = new request(graphql::TYPE_DEV, $request_params);

        $result = $processor->process_request($request);
        $this->assertInstanceOf(ExecutionResult::class, $result);
        $this->assertEmpty($result->errors, 'Unexpected errors found in request');
    }

    private static function get_introspection_query(): string {
        // Not getting the types to keep performance impact of this as low as possible.
        // It should still be enough to test that introspection works.
        return '
            query IntrospectionQuery {
                __schema {
                    queryType { name }
                    mutationType { name }
                    subscriptionType { name }
                    directives {
                        name
                        description
                        locations
                        args {
                            ...InputValue
                        }
                    }
                }
            }
        
            fragment InputValue on __InputValue {
                name
                description
                type { ...TypeRef }
                defaultValue
            }
        
            fragment TypeRef on __Type {
                kind
                name
            }
        ';
    }
}
