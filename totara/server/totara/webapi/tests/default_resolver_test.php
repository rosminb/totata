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
 * @author Fabian Derschatta <fabian.derschatta@totaralearning.com>
 * @package totara_webapi
 */

use core\date_format;
use core\webapi\execution_context;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use totara_webapi\default_resolver;
use totara_webapi\graphql;

defined('MOODLE_INTERNAL') || die();

class totara_webapi_default_resolver_test extends advanced_testcase {

    public function test_query_resolver() {
        $result = $this->resolve_graphql_query('totara_webapi_status');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('timestamp', $result);
    }

    public function test_query_resolver_unknown_query() {
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('GraphQL query name is invalid');

        $this->resolve_graphql_query('idonot_exist');
    }

    public function test_query_resolver_resolver_class_missing() {
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('GraphQL query resolver class is missing');

        $this->resolve_graphql_query('totara_webapi_idonotexist');
    }

    public function test_query_resolver_with_middleware() {
        global $CFG;
        require_once $CFG->dirroot.'/totara/webapi/tests/fixtures/resolver/query/test_middleware_query_resolver.php';

        $result = $this->resolve_graphql_query('totara_webapi_test_middleware_query_resolver', ['arg1' => 'value1']);

        $this->assertArrayHasKey('success', $result);
        $this->assertEquals(true, $result['success']);
        $this->assertArrayHasKey('args', $result);
        $this->assertEqualsCanonicalizing([
            'arg1' => 'newvalue1',
            'arg2' => 'value2'
        ], $result['args']);

        $this->assertArrayHasKey('result1', $result);
        $this->assertArrayHasKey('result2', $result);
    }

    public function test_query_resolver_with_invalid_middleware() {
        global $CFG;
        require_once $CFG->dirroot.'/totara/webapi/tests/fixtures/resolver/query/test_middleware_query_resolver_with_invalid_middleware.php';

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Expecting an array of middleware instances only');

        $this->resolve_graphql_query('totara_webapi_test_middleware_query_resolver_with_invalid_middleware');
    }

    public function test_query_resolver_with_middleware_group() {
        global $CFG;
        require_once $CFG->dirroot.'/totara/webapi/tests/fixtures/resolver/query/test_middleware_query_resolver_with_group.php';

        $result = $this->resolve_graphql_query('totara_webapi_test_middleware_query_resolver_with_group', ['arg1' => 'value1']);

        $this->assertArrayHasKey('success', $result);
        $this->assertEquals(true, $result['success']);
        $this->assertArrayHasKey('args', $result);
        $this->assertEqualsCanonicalizing([
            'arg1' => 'newvalue1',
            'arg2' => 'value2'
        ], $result['args']);

        $this->assertArrayHasKey('result1', $result);
        $this->assertArrayHasKey('result2', $result);
    }

    public function test_query_resolver_with_nested_middleware_group() {
        global $CFG;
        require_once $CFG->dirroot.'/totara/webapi/tests/fixtures/resolver/query/test_middleware_query_resolver_with_nested_group.php';

        $result = $this->resolve_graphql_query('totara_webapi_test_middleware_query_resolver_with_nested_group', ['arg1' => 'value1']);

        $this->assertArrayHasKey('success', $result);
        $this->assertEquals(true, $result['success']);
        $this->assertArrayHasKey('args', $result);
        $this->assertEqualsCanonicalizing([
            'arg1' => 'newvalue1',
            'arg2' => 'value2'
        ], $result['args']);

        $this->assertArrayHasKey('result1', $result);
        $this->assertArrayHasKey('result2', $result);
    }

    public function test_mutation_resolver_with_middleware() {
        global $CFG;
        require_once $CFG->dirroot.'/totara/webapi/tests/fixtures/resolver/mutation/test_middleware_mutation_resolver.php';

        $result = $this->resolve_graphql_mutation('totara_webapi_test_middleware_mutation_resolver');

        $this->assertArrayHasKey('mutation_success', $result);
        $this->assertEquals(true, $result['mutation_success']);
        $this->assertArrayHasKey('args', $result);
        $this->assertEqualsCanonicalizing([
            'arg1' => 'newvalue1',
            'arg2' => 'value2'
        ], $result['args']);

        $this->assertArrayHasKey('result1', $result);
        $this->assertArrayHasKey('result2', $result);
    }

    public function test_type_resolver() {
        $timestamp = time();

        $source = [
            'status' => 'green',
            'timestamp' => $timestamp
        ];

        $args = ['format' => date_format::FORMAT_ISO8601];

        // First let's query the status column
        $result = $this->resolve_graphql_type('totara_webapi_status', 'status', $source, $args);
        $this->assertEquals('green', $result);

        // Then the timestamp
        $date = new DateTime('@' . $timestamp);
        $date->setTimezone(core_date::get_user_timezone_object());
        $expected_timestamp_result = $date->format(DateTime::ISO8601);

        $result = $this->resolve_graphql_type('totara_webapi_status', 'timestamp', $source, $args);
        $this->assertEquals($expected_timestamp_result, $result);
    }

    public function test_type_resolver_with_invalid_name() {
        $source = [
            'status' => 'green',
            'timestamp' => 123
        ];

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Type resolvers must be named as component_name, e.g. totara_job_job');

        $this->resolve_graphql_type('totara_idontexist', 'status', $source);
    }

    public function test_no_existing_type_resolver_class_hits_graphql_default_resolver() {
        $source = [
            'status' => 'red',
            'timestamp' => 123
        ];

        $args = ['format' => date_format::FORMAT_ISO8601];

        // First let's query the status column
        $result = $this->resolve_graphql_type('totara_webapi_mystatus', 'status', $source, $args);
        $this->assertEquals('red', $result);

        // Then the timestamp. This time the format is ignored as its not going through the normal type resolver
        $result = $this->resolve_graphql_type('totara_webapi_mystatus', 'timestamp', $source, $args);
        $this->assertEquals(123, $result);
    }

    public function test_introspection_type() {
        // We make sure the default resolver does not brake on introspection types
        $result = $this->resolve_graphql_type('__schema', 'types', null, []);

        // This is empty as it's usually handled internally
        $this->assertNull($result);
    }

    /**
     * Explicitly not depending on the webapi_phpunit_helper to make sure even if it
     * changes these tests here still test what they should test.
     *
     * @param string  $query_name
     * @param array   $variables
     * @param context $relevantcontext - set execution relevant context to given context
     * @return mixed|null
     */
    protected function resolve_graphql_query(string $query_name, array $variables = [], \context $relevantcontext = null) {
        $object_type_mock = $this->getMockBuilder(ObjectType::class)
            ->disableOriginalConstructor()
            ->getMock();

        $object_type_mock->name = 'Query';

        $resolve_info_mock = $this->getMockBuilder(ResolveInfo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $resolve_info_mock->parentType = $object_type_mock;
        $resolve_info_mock->fieldName = $query_name;

        $execution_context = execution_context::create(graphql::TYPE_AJAX, $query_name);
        if (!empty($relevantcontext)) {
            $execution_context->set_relevant_context($relevantcontext);
        }

        $resolver = new default_resolver();
        return $resolver(null, $variables, $execution_context, $resolve_info_mock);
    }

    /**
     * Explicitly not depending on the webapi_phpunit_helper to make sure even if it
     * changes these tests here still test what they should test.
     *
     * @param string $mutation_name
     * @param array $variables
     * @param context $relevantcontext - set execution relevant context to given context
     * @return mixed|null
     */
    protected function resolve_graphql_mutation(string $mutation_name, array $variables = [], \context $relevantcontext = null) {
        $object_type_mock = $this->getMockBuilder(ObjectType::class)
            ->disableOriginalConstructor()
            ->getMock();

        $object_type_mock->name = 'Mutation';

        $resolve_info_mock = $this->getMockBuilder(ResolveInfo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $resolve_info_mock->parentType = $object_type_mock;
        $resolve_info_mock->fieldName = $mutation_name;

        $execution_context = execution_context::create(graphql::TYPE_AJAX, $mutation_name);
        if (!empty($relevantcontext)) {
            $execution_context->set_relevant_context($relevantcontext);
        }

        $resolver = new default_resolver();
        return $resolver(null, $variables, $execution_context, $resolve_info_mock);
    }

    /**
     * Explicitly not depending on the webapi_phpunit_helper to make sure even if it
     * changes these tests here still test what they should test.
     *
     * @param string $type_name
     * @param string $field_name
     * @param $source
     * @param array $variables
     * @param context $relevantcontext - set execution relevant context to given context
     * @return mixed|null
     */
    protected function resolve_graphql_type(string $type_name, string $field_name, $source, array $variables = [], \context $relevantcontext = null) {
        $object_type_mock = $this->getMockBuilder(ObjectType::class)
            ->disableOriginalConstructor()
            ->getMock();

        $object_type_mock->name = $type_name;

        $resolve_info_mock = $this->getMockBuilder(ResolveInfo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $resolve_info_mock->parentType = $object_type_mock;
        $resolve_info_mock->fieldName = $field_name;

        $execution_context = execution_context::create(graphql::TYPE_AJAX, null);
        if (!empty($relevantcontext)) {
            $execution_context->set_relevant_context($relevantcontext);
        }

        $resolver = new default_resolver();
        return $resolver($source, $variables, $execution_context, $resolve_info_mock);
    }

}
