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

namespace totara_webapi;

class schema_file_loader {

    /**
     * Loads all schema files returning the content
     *
     * @return array
     */
    public function load(): array {
        global $CFG;

        $schemas = [];

        // Add any additional files from core
        $filenames = $this->get_graphqls_files($CFG->dirroot . '/lib/webapi');
        foreach ($filenames as $filename) {
            // Core file is skipped as it is read separately
            if (preg_match("/\\/schema\\.graphqls$/", $filename)) {
                continue;
            }
            $schemas[$filename] = $this->get_schema_file_content($filename);
        }

        // Then read all plugin schema files, here the order or names do not matter
        // as they will all be merged together and then extend the main schema
        $types = \core_component::get_plugin_types();
        foreach ($types as $type => $typedir) {
            $plugins = \core_component::get_plugin_list($type);
            foreach ($plugins as $plugin => $plugindir) {
                $filenames = $this->get_graphqls_files("$plugindir/webapi");
                foreach ($filenames as $filename) {
                    $schemas[$filename] = $this->get_schema_file_content($filename);
                }
            }
        }

        if ($CFG->debugdeveloper) {
            foreach (\core_component::get_core_subsystems() as $subsystem => $dir) {
                if (!$dir) {
                    continue;
                }
                $filenames = $this->get_graphqls_files("$dir/webapi");
                if (!empty($filenames)) {
                    debugging('.graphqls files are not allowed in core subsystems, use lib/webapi/schema.graphqls instead');
                }
            }
        }

        return $schemas;
    }

    /**
     * Read contents of given schema file
     *
     * @param string $filename
     * @return string
     * @throws \Exception
     */
    private function get_schema_file_content(string $filename): string {
        $content = file_get_contents($filename);
        if ($content === false) {
            throw new \Exception('Could not read schema file '.$filename);
        }
        return $content;
    }

    /**
     * Get all .graphqls files in given folder
     *
     * @param string $dir
     * @return array
     */
    protected function get_graphqls_files(string $dir): array {
        return local\util::get_files_from_dir($dir, 'graphqls');
    }


}