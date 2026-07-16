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
 * @author Petr Skoda <petr.skoda@totaralearning.com>
 * @author Fabian Derschatta <fabian.derschatta@totaralearning.com>
 * @package totara_webapi
 */

namespace totara_webapi;

use Closure;
use core\webapi\execution_context;
use core\webapi\middleware;
use core\webapi\middleware_group;
use core\webapi\mutation_resolver;
use core\webapi\query_resolver;
use core\webapi\resolver\has_middleware;
use core\webapi\resolver\payload;
use core\webapi\resolver\result;
use core_component;
use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * This represents our default resolver. It takes the request information
 * and tries to resolve the operation to the proper query, type or mutation resolver.
 *
 * All resolvers are stored in classes/webapi/resolver/* folders.
 *
 * @package totara_webapi
 */
class default_resolver {

    /**
     * This class can be used as a callable. The GraphQL library will call it when it tries
     * to resolve the operation given to it.
     *
     * @param mixed $source
     * @param mixed $variables
     * @param execution_context $ec
     * @param ResolveInfo $info
     *
     * @return mixed|null
     */
    public function __invoke($source, $variables, execution_context $ec, ResolveInfo $info) {
        // phpcs:disable Totara.NamingConventions.ValidVariableName.LowerCaseUnderscores
        $ec->set_resolve_info($info);

        $variables = (array) $variables;
        if ($info->parentType->name === 'Query' or $info->parentType->name === 'Mutation') {
            $otype = ($info->parentType->name === 'Query') ? 'query' : 'mutation';

            [$component, $name] = $this->split_type_name($info->fieldName);
            if (empty($component)) {
                throw new \coding_exception('GraphQL ' . $otype . ' name is invalid', $info->fieldName);
            }
            /** @var query_resolver|mutation_resolver $classname */
            $classname = "{$component}\\webapi\\resolver\\{$otype}\\{$name}";
            if (!class_exists($classname)) {
                throw new \coding_exception('GraphQL ' . $otype . ' resolver class is missing', $info->fieldName);
            }

            return self::resolve_query_mutation($classname, $variables, $ec);
        }

        // Regular data type.
        $parts = explode('_', $info->parentType->name);
        if (!$this->is_introspection_type($info->parentType->name) && count($parts) > 1) {
            $this->check_for_deprecation_messages($ec, $info);

            [$component, $name] = $this->split_type_name($info->parentType->name);
            if (empty($name)) {
                throw new \coding_exception('Type resolvers must be named as component_name, e.g. totara_job_job');
            }
            $classname = "{$component}\\webapi\\resolver\\type\\{$name}";
            if (class_exists($classname)) {
                /** @var \core\webapi\type_resolver $classname */
                return $classname::resolve($info->fieldName, $source, $variables, $ec);
            }
        }

        return Executor::defaultFieldResolver($source, $variables, $ec, $info);
        // phpcs:enable
    }

    /**
     * Check if the type name is one used in introspections, which usually starts with two underscores
     * @param string $name
     * @return bool
     */
    private function is_introspection_type(string $name): bool {
        return strpos($name, '__') === 0;
    }

    private static $split_type_name_cache = [];

    /**
     * Split type name, i.e. totara_competency_my_query_name into component (totara_competency) and the rest (my_query_name)
     *
     * @param string $name
     * @return array
     */
    private function split_type_name(string $name) {
        if (isset(self::$split_type_name_cache[$name])) {
            return self::$split_type_name_cache[$name];
        }

        if (strpos($name, 'core_') === 0) {
            self::$split_type_name_cache[$name] = ['core', substr($name, 5)];
            return self::$split_type_name_cache[$name];
        }

        // Build flat list out of all plugins and subplugins
        $components = [];
        $types = core_component::get_plugin_types();
        foreach ($types as $type => $typedir) {
            $plugins = core_component::get_plugin_list($type);
            foreach ($plugins as $plugin => $plugindir) {
                $plugin_component = $type.'_'.$plugin;
                $components[$plugin_component] = $plugin_component;
                $subplugins = core_component::get_subplugins($plugin_component);
                foreach ($subplugins ?? [] as $prefix => $subplugin_names) {
                    foreach ($subplugin_names as $subplugin) {
                        $subplugin_component = $prefix . '_' . $subplugin;
                        $components[$subplugin_component] = $subplugin_component;
                    }
                }
            }
        }

        // Now try to find component name by reducing the name one by one and checking existence in component list
        $parts = explode('_', $name);
        while (count($parts) > 0) {
            array_pop($parts);
            $component_search_name = implode('_', $parts);
            if (isset($components[$component_search_name])) {
                self::$split_type_name_cache[$name] = [
                    $component_search_name,
                    substr($name, strlen($component_search_name) + 1)
                ];
                return self::$split_type_name_cache[$name];
            }
        }

        self::$split_type_name_cache[$name] = [null, null];
        return [null, null];
    }

    /**
     * Resolve a query or mutation by either generating and applying a middleware chain
     * or if no middleware is specified calling the resolver directly.
     *
     * @param string $classname
     * @param mixed $variables
     * @param execution_context $ec
     * @return mixed
     */
    protected function resolve_query_mutation(string $classname, $variables, execution_context $ec) {
        if (is_subclass_of($classname, has_middleware::class)) {
            /** @var middleware[] $middleware */
            $middleware = $classname::get_middleware();

            if (!empty($middleware)) {
                $middleware = array_values(array_reverse($middleware));

                // Wrapping the request to be more flexible in the future,
                // adding new things will be easier compared to having fixed arguments for args and the ec
                $payload = payload::create($variables, $ec);

                $middleware_chain = function (payload $payload) use ($classname) {
                    $result = $classname::resolve($payload->get_variables(), $payload->get_execution_context());
                    // Wrapping the result to make sure the middleware has a specific return type
                    return new result($result);
                };

                $middleware_chain = $this->create_chain_recursively($middleware, $middleware_chain);

                return $middleware_chain($payload)->get_data();
            }
        }

        return $classname::resolve($variables, $ec);
    }

    /**
     * Create a middleware chain recursively
     *
     * @param $middleware
     * @param Closure $middleware_chain
     * @return Closure
     */
    private function create_chain_recursively($middleware, Closure $middleware_chain): Closure {
        foreach ($middleware as $current_middleware) {
            // This middleware could be a middleware group, in this case get all middleware
            // from it and add them to the chain as well
            if (is_subclass_of($current_middleware, middleware_group::class)) {
                // This is just the class name, so let's instantiate it
                if (is_string($current_middleware)) {
                    $current_middleware = new $current_middleware();
                }

                /** @var middleware_group $current_middleware */
                $middleware_group_items = array_values(array_reverse($current_middleware->get_middleware()));
                $middleware_chain = $this->create_chain_recursively($middleware_group_items, $middleware_chain);
                continue;
            }

            // Middleware can be instances or class names, both would work
            if (!is_subclass_of($current_middleware, middleware::class)) {
                throw new \coding_exception('Expecting an array of middleware instances only');
            }

            $middleware_chain = function (payload $payload) use ($middleware_chain, $current_middleware) {
                // This is just the class name, so let's instantiate it
                if (is_string($current_middleware)) {
                    $current_middleware = new $current_middleware();
                }
                return $current_middleware->handle($payload, $middleware_chain);
            };
        }

        return $middleware_chain;
    }

    /**
     * Check for deprecation messages and if found add them to the execution context
     *
     * @param execution_context $execution_context
     * @param ResolveInfo $info
     */
    private function check_for_deprecation_messages(execution_context $execution_context, ResolveInfo $info): void {
        // phpcs:disable Totara.NamingConventions.ValidVariableName.LowerCaseUnderscores

        if ($info->schema) {
            $type = $info->schema->getType($info->parentType->name);
            if ($type) {
                $field = $type->getField($info->fieldName);
                if (!empty($field->deprecationReason)) {
                    $execution_context->add_deprecation_warning(
                        $info->parentType->name,
                        $info->fieldName,
                        $field->deprecationReason
                    );
                }
            }
        }
    }

}