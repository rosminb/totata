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
 * @author Petr Skoda <petr.skoda@totaralearning.com>
 * @author Fabian Derschatta <fabian.derschatta@totaralearning.com>
 * @package totara_webapi
 */

namespace totara_webapi;

use core\webapi\execution_context;

/**
 * Main GraphQL API intended for plugins such as External API or mobile support.
 */
final class graphql {

    public const TYPE_AJAX = 'ajax';
    public const TYPE_DEV = 'dev';
    public const TYPE_MOBILE = 'mobile';

    /**
     * Builds, validate the schema (on developer mode) and returns the schema instance
     *
     * @param string|null $type
     * @return \GraphQL\Type\Schema
     */
    public static function get_schema(?string $type = null): \GraphQL\Type\Schema {
        $schema_file_loader = new schema_file_loader();
        $schema_builder = new schema_builder($schema_file_loader);
        $schema = $schema_builder->build();

        if ($type === graphql::TYPE_DEV) {
            $schema->assertValid();
        }

        return $schema;
    }

    /**
     * Returns list of required capabilities in system context for each operation.
     *
     * NOTE: this is not enforced automatically,
     *       it is used for documentation and to create default roles for External API.
     *
     * @param string $type API type such as 'ajax', 'external' or 'mobile'
     * @return array where keys are operation names and values lists of capability names
     */
    public static function get_role_capabilities(string $type) {
        $result = [];

        $alloperations = persistent_operations_loader::get_operations($type);
        foreach ($alloperations as $operationname => $file) {
            $result[$operationname] = [];
            $content = file_get_contents($alloperations[$operationname]);
            if (!$content) {
                continue;
            }
            if (!preg_match('/# role capabilities:(.*)/', $content, $matches)) {
                if ($type === 'external') {
                    debugging("External persisted operation {$operationname} does not include '# role capabilities:' comment", DEBUG_DEVELOPER);
                }
                continue;
            }

            $capabilities = $matches[1];
            $capabilities = explode(',', $capabilities);
            $capabilities = array_map('trim', $capabilities);
            foreach ($capabilities as $capability) {
                if (!get_capability_info($capability)) {
                    debugging("Persisted operation {$operationname} includes invalid '# role capabilities:' comment", DEBUG_DEVELOPER);
                    continue;
                }
                $result[$operationname][] = $capability;
            }
        }

        return $result;
    }

    /**
     * Get root for operation execution.
     *
     * @return array
     */
    public static function get_server_root(\GraphQL\Type\Schema $schema) {
        return [];
    }

    /**
     * Execute persisted GraphQL query or mutation. This can now be handled
     * by the server instance. This function here is kept for backwards
     * compatibility reason.
     *
     * @param execution_context $ec
     * @param array $variables
     * @return \GraphQL\Executor\ExecutionResult
     */
    public static function execute_operation(execution_context $ec, array $variables) {
        $request = self::create_request_from_execution_context($ec, $variables);
        $server = new server($ec);
        return $server->handle_request($request);
    }

    /**
     * @param execution_context $ec
     * @param array $variables
     * @return request
     */
    private static function create_request_from_execution_context(execution_context $ec, array $variables) {
        if (!$ec->get_operationname()) {
            throw new \coding_exception('Execution context has to have an operation name set.');
        }

        $params = [
            'operationName' => $ec->get_operationname(),
            'variables' => $variables
        ];
        return new request($ec->get_type(), $params);
    }

    /**
     * Returns all available types
     *
     * @return array|string[]
     */
    public static function get_available_types(): array {
        return [
            self::TYPE_DEV,
            self::TYPE_MOBILE,
            self::TYPE_AJAX
        ];
    }

}