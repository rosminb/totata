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
 * @author Kian Nguyen <kian.nguyen@totaralearning.com>
 * @package editor_weka
 */
namespace editor_weka\factory;

use core\editor\variant_name;
use core_component;
use editor_weka\extension\abstraction\specific_custom_extension;
use editor_weka\extension\attachment;
use editor_weka\extension\hashtag;
use editor_weka\extension\mention;
use editor_weka\extension\media;
use editor_weka\extension\list_extension;
use editor_weka\extension\emoji;
use editor_weka\extension\link;
use editor_weka\extension\text;
use editor_weka\extension\ruler;

class extension_loader {
    /**
     * extension_loader constructor.
     */
    private function __construct() {
        // Prevent the construction of this class.
    }

    /**
     * Return a merge array of standard extensions introduce by the core
     * system (editor_weka) itself and the array of plugin extension.
     *
     * @return string[]
     */
    public static function get_all_extension_classes(): array {
        return array_merge(
            static::get_standard_extension_classes(),
            static::get_all_plugin_extensions()
        );
    }

    /**
     * Returning a built-up metadata of extensions and potentially extension options
     * base on the variant's name.
     *
     * Return with the schema as example below:
     * [
     *  'extensions' => [
     *      'editor_weka\extension\attachment'
     *  ]
     * ]
     *
     * @param string $name
     * @return array
     */
    public static function get_extensions_for_variant(string $name): array {
        $definitions = variant_definition::get_definitions();
        $metadata = [
            'extensions' => []
        ];

        if (!array_key_exists($name, $definitions)) {
            debugging("The variant name '{$name}' is not supported", DEBUG_DEVELOPER);
            // Use the default standard variant.
            $name = variant_name::STANDARD;
        }

        $definition = $definitions[$name];
        $exclude_extensions = [];

        if (array_key_exists('exclude_extensions', $definition)) {
            $exclude_extensions = $definition['exclude_extensions'];
        }

        $extensions = static::get_all_extension_classes_exclude($exclude_extensions);

        // Excluding the extensions that are implement the interface specific_custom_extension
        // as it is only be included per use case and not to be use as a generic extension.
        $extensions = array_filter(
            $extensions,
            function (string $extension_class_name): bool {
                return !is_a($extension_class_name, specific_custom_extension::class, true);
            }
        );

        $metadata['extensions'] = $extensions;
        return $metadata;
    }

    /**
     * @param array $exclude_classes
     * @return string[]
     */
    private static function get_all_extension_classes_exclude(array $exclude_classes) {
        $all_extensions = static::get_all_extension_classes();
        $exclude_classes = array_map(
            function (string $extension_class): string {
                return ltrim($extension_class, '\\');
            },
            $exclude_classes
        );

        return array_values(
            array_filter(
                $all_extensions,
                function (string $extension_class) use ($exclude_classes): bool {
                    $extension_class = ltrim($extension_class, '\\');
                    return !in_array($extension_class, $exclude_classes);
                }
            )
        );
    }

    /**
     * This is to return all the extension classes that are introduced as a
     * part of the weka editor.
     *
     * Note: please use {@see extension_loader::get_all_extension_classes()} to fetch all the
     * extension classes from the system. As for now we do not support the extensions to be added
     * as a sub plugin of editor weka. However, with the method above, we will have space
     * to do that in the future without reworking on the current API.
     *
     * @return string[]
     */
    final public static function get_standard_extension_classes(): array {
        return array_merge(
            self::get_minimal_required_extension_classes(),
            [
                attachment::class,
                hashtag::class,
                mention::class,
                media::class,
                list_extension::class,
                emoji::class
            ]
        );
    }

    /**
     * @return string[]
     */
    final public static function get_minimal_required_extension_classes(): array {
        return [
            link::class,
            text::class,
            ruler::class
        ];
    }

    /**
     * @return array
     */
    final public static function get_all_plugin_extensions(): array {
        $sub_plugins = core_component::get_subplugins('editor_weka');
        $extensions = [];

        foreach ($sub_plugins as $plugin_type => $sub_plugin_names) {
            foreach ($sub_plugin_names as $plugin_name) {
                $extension_class = "{$plugin_type}_{$plugin_name}\\extension";

                if (!class_exists($extension_class)) {
                    debugging(
                        "The extension class '{$extension_class}' does not exist in the system",
                        DEBUG_DEVELOPER
                    );
                }

                $extensions[] = $extension_class;
            }
        }

        return $extensions;
    }
}