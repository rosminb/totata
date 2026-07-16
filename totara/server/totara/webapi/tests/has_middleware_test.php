<?php
/*
 * This file is part of Totara Learn
 *
 * Copyright (C) 2022 onwards Totara Learning Solutions LTD
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
 * @author Michael Ivanov <michael.ivanov@totaralearning.com>
 * @package totara_webapi
 */

use core\webapi\resolver\has_middleware;
use totara_webapi\local\util;

defined('MOODLE_INTERNAL') || die();

class totara_webapi_has_middleware_test extends advanced_testcase {

    /**
     * @return void
     * @throws ReflectionException
     * @throws coding_exception
     */
    public function test_query_resolver(): void {
        $resolvers = $this->build_resolvers_array();
        foreach ($resolvers as $resolver) {
            include_once($resolver);
        }
        foreach (get_declared_classes() as $class) {
            $this->assertTrue($this->has_middleware_implemented($class));
        }
    }

    /**
     * Checks if the provided class implements has_middleware interface if it has get_middleware function defined
     * @param string $resolver_class
     * @return bool
     * @throws ReflectionException
     * @throws coding_exception
     */
    protected function has_middleware_implemented(string $resolver_class): bool {
        $reflection = new ReflectionClass($resolver_class);
        $path = $reflection->getFileName();
        $result = empty($path)
            || strpos($path, 'classes/webapi/resolver') === false
            || !method_exists($resolver_class, 'get_middleware')
            || in_array(has_middleware::class, class_implements($resolver_class));
        if (!$result) {
            throw new coding_exception(
                "$resolver_class has get_middleware method defined but doesn't implement has_middleware interface!"
            );
        }
        return $result;
    }

    /**
     * Build an array containing all GraphQL resolvers
     * @return array
     */
    protected static function build_resolvers_array(): array {
        global $CFG;

        $subdir = 'classes/webapi/resolver';
        $resolvers = [];

        $files = util::get_files_from_dir($CFG->libdir . '/' . $subdir . '/query', 'php');
        $files += util::get_files_from_dir($CFG->libdir . '/' . $subdir . '/mutation', 'php');
        foreach ($files as $file) {
            $resolvers[] = $file;
        }

        foreach (\core_component::get_core_subsystems() as $full_dir) {
            $files = util::get_files_from_dir($full_dir . '/' . $subdir . '/query', 'php');
            $files += util::get_files_from_dir($full_dir . '/' . $subdir . '/mutation', 'php');
            foreach ($files as $file) {
                $resolvers[] = $file;
            }
        }

        $plugin_types = \core_component::get_plugin_types();
        foreach ($plugin_types as $plugin_type => $unused) {
            $plugins = \core_component::get_plugin_list($plugin_type);
            foreach ($plugins as $full_dir) {
                $files = util::get_files_from_dir($full_dir . '/' . $subdir . '/query', 'php');
                $files += util::get_files_from_dir($full_dir . '/' . $subdir . '/mutation', 'php');
                foreach ($files as $file) {
                    $resolvers[] = $file;
                }
            }
        }

        return $resolvers;
    }
}
