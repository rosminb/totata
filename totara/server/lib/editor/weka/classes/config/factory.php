<?php
/**
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
 * @author  Kian Nguyen <kian.nguyen@totaralearning.com>
 * @package editor_weka
 */
namespace editor_weka\config;

use cache;
use coding_exception;
use core_component;

/**
 * Config class for editor.
 * @deprecated since Totara 13.3
 */
final class factory {
    /**
     * The array of configuration which is categorized by the item's component name and area.
     * @var array
     */
    private $configuration;

    /**
     * editor_config constructor.
     */
    public function __construct() {
        debugging(
            "The class \\editor_weka\\config\\factory had been deprecated, please use \\editor_weka\\variant instead",
            DEBUG_DEVELOPER
        );

        $this->configuration = [];
    }

    /**
     * @return void
     */
    public function load(): void {
        if (!empty($this->configuration)) {
            return;
        }

        $istest = (defined('PHPUNIT_TEST') && PHPUNIT_TEST);
        $cache = cache::make('editor_weka', 'editorconfig');

        if (!$istest) {
            // Cache should only be done for the non unit-testing environment.
            $configuration = $cache->get('configuration');

            if (is_array($configuration)) {
                $this->configuration = $configuration;
                return;
            }
        }

        $types = core_component::get_plugin_types();

        foreach ($types as $type => $directory) {
            $pluginfiles = core_component::get_plugin_list_with_file($type, 'db/editor_weka.php');
            if (empty($pluginfiles)) {
                continue;
            }

            foreach ($pluginfiles as $pluginname => $file) {
                $component = "{$type}_{$pluginname}";

                if (isset($this->configuration[$component])) {
                    debugging(
                        "The component '{$component}' had already been loaded with the configuration",
                        DEBUG_DEVELOPER
                    );

                    continue;
                }

                $editor = [];
                require($file);

                if (!empty($editor)) {
                    $this->configuration[$component] = $editor;
                }
            }
        }

        if (!$istest && !empty($this->configuration)) {
            // Cache the configs if not test.
            $cache->set('configuration', $this->configuration);
        }
    }

    /**
     * @param string $component
     * @param string $area
     *
     * @return config_item
     */
    public function get_configuration(string $component, string $area): config_item {
        $this->load();

        if (!isset($this->configuration[$component])) {
            // No configuration found for component
            throw new coding_exception("Cannot find the configuration for component '{$component}'");
        }

        $config = $this->configuration[$component];
        if (!isset($config[$area])) {
            throw new coding_exception("Cannot find the configuration of area '{$area}'");
        }

        $data = $config[$area];
        return config_item::from_array($data);
    }

    /**
     * This function will be mainly used to mock the configuration of the editor weka based for the place of
     * '{$area} - {$component}'. Note that this API will only cache the data to memory but not to physical storage.
     *
     * @param string      $component
     * @param string      $area
     * @param config_item $config_item
     *
     * @return void
     */
    public function add_configuration(string $component, string $area, config_item $config_item): void {
        if (!isset($this->configuration[$component])) {
            $this->configuration[$component] = [];
        }

        $config = $this->configuration[$component];

        if (isset($config[$area])) {
            throw new coding_exception(
                "The area '{$area}' for component '{$component}' is already existing in the configuration data"
            );
        }

        $config[$area] = $config_item->get_metadata();
        $this->configuration[$component] = $config;
    }
}