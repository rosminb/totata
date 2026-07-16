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

use editor_weka\factory\extension_loader;

/**
 * A class for holding configuration data.
 * @deprecated since Totara 13.3
 */
final class config_item {
    /**
     * Area default.
     * @var string
     *
     * @deprecated since Totara 13.3
     */
    const AREA_DEFAULT = 'default';

    /**
     * Whether to use all the extensions or not.
     * @var string
     *
     * @deprecated since Totara 13.3
     */
    const EXTENSION_ALL = 'all';

    /**
     * Array of extension class names
     * @var string[]
     */
    private $extensions;

    /**
     * item constructor.
     *
     * @param string|null $component
     * @param string|null $area
     */
    private function __construct(?string $component = null, ?string $area = null) {
        if (!empty($component)) {
            debugging(
                "The parameter '\$component' had been deprecated and no longer used, please update the caller",
                DEBUG_DEVELOPER
            );
        }

        if (!empty($area)) {
            debugging(
                "The parameter '\$area' had been deprecated and no longer used, please update the caller",
                DEBUG_DEVELOPER
            );
        }

        debugging(
            'The class \\editor_weka\\config\\config_item had been deprecated, please \\editor_weka\\variant instead',
            DEBUG_DEVELOPER
        );

        $this->extensions = [];
    }

    /**
     * This function has been deprecated,
     *
     * @return bool
     * @deprecated since Totara 13.3
     */
    public function show_toolbar(): bool {
        return true;
    }

    /**
     * Returning a metadata array that is similar with the one from metadata editor_weka.php file.
     * However this function will EXCLUDE the component and area, just purely metadata.
     *
     * @return array
     */
    public function get_metadata(): array {
        return [
            'includeextensions' => $this->extensions,
        ];
    }

    /**
     * @param array $config
     * @return config_item
     */
    public static function from_array(array $config): config_item {
        $instance = new static();

        if (array_key_exists('includeextensions', $config)) {
            if (is_array($config['includeextensions'])) {
                $extensions = array_merge(
                    extension_loader::get_minimal_required_extension_classes(),
                    $config['includeextensions']
                );

                $instance->extensions = array_unique(
                    array_map(
                        function (string $extension_class): string {
                            return ltrim($extension_class, '\\');
                        },
                        $extensions
                    )
                );
            } else {
                if (is_string($config['includeextensions']) && static::EXTENSION_ALL === $config['includeextensions']) {
                    // So this component-area want to use all the extensions. Therefore, we will load
                    // all the extensions from the system.

                    // Default extension classes from the editor only.
                    $instance->extensions = extension_loader::get_all_extension_classes();
                } else {
                    debugging("Invalid value for key 'includeextensions'", DEBUG_DEVELOPER);
                }
            }
        }

        return $instance;
    }

    /**
     * Returning the list of extension class name(s).
     *
     * @return string[]
     */
    public function get_extensions(): array {
        return $this->extensions;
    }

    /**
     * Given that the $extensionname is the extension classname, then this function should be able to give the
     * extension options defined by component/area specific only.
     *
     * @param string $extensionname
     * @return array
     *
     * @deprecated since Totara 13.3
     */
    public function get_options_for_extension(string $extensionname): array {
        debugging(
            "The function \\editor_weka\\config\\config_item::get_options_for_extension had been deprecated and no longer used",
            DEBUG_DEVELOPER
        );

        return [];
    }
}