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
use editor_weka\extension\attachment;
use editor_weka\extension\emoji;
use editor_weka\extension\hashtag;
use editor_weka\extension\link;
use editor_weka\extension\list_extension;
use editor_weka\extension\media;
use editor_weka\extension\mention;
use editor_weka\extension\ruler;

class variant_definition {
    /**
     * variant_definition constructor.
     */
    private function __construct() {
        // Preventing this class from construction
    }

    /**
     * Returning a set of definitions based on the variants.
     * Default metadata
     *
     * @return array[]
     */
    public static function get_definitions(): array {
        return [
            // Standard from the core API
            variant_name::STANDARD => [
                'exclude_extensions' => []
            ],
            variant_name::DESCRIPTION => [
                'exclude_extensions' => [
                    hashtag::class,
                    mention::class
                ]
            ],

            variant_name::SIMPLE => [
                'exclude_extensions' => [
                    hashtag::class,
                    mention::class,
                    emoji::class,
                    ruler::class,
                    link::class,
                    list_extension::class,
                    media::class,
                    attachment::class,
                ]
            ],
            // The rest is the custom variants through out the system that we would want to
            // make it backward compatible.
            'editor_weka-phpunit' => [
                'exclude_extensions' => [
                    hashtag::class,
                    mention::class,
                    media::class,
                    list_extension::class,
                    emoji::class
                ],
            ],
            'editor_weka-behat' => [
                'exclude_extensions' => []
            ],
            'editor_weka-learn' => [
                'exclude_extensions' => []
            ],
            'editor_weka-default' => [
                'exclude_extensions' => []
            ],
            'totara_playlist-comment' => [
                'exclude_extensions' => [
                    emoji::class,
                    list_extension::class,
                    attachment::class,
                    media::class,
                ]
            ],
            'totara_playlist-summary' => [
                'exclude_extensions' => [
                    emoji::class,
                    list_extension::class,
                    attachment::class,
                    media::class,
                ]
            ],
            'container_workspace-description' => [
                'exclude_extensions' => [
                    emoji::class,
                    list_extension::class,
                    attachment::class,
                    media::class
                ]
            ],
            'container_workspace-discussion' => [
                'exclude_extensions' => []
            ],
            'engage_article-content' => [
                'exclude_extensions' => []
            ],
            'engage_article-comment' => [
                'exclude_extensions' => [
                    list_extension::class,
                    emoji::class,
                    attachment::class,
                    media::class
                ]
            ],
            'performelement_static_content-content' => [
                'exclude_extensions' => [
                    hashtag::class
                ]
            ]
        ];
    }

    /**
     * @param string $variant_name
     * @return bool
     */
    public static function in_supported(string $variant_name): bool {
        return in_array(
            $variant_name,
            [
                variant_name::STANDARD,
                variant_name::DESCRIPTION,
                variant_name::SIMPLE,
                'editor_weka-phpunit',
                'editor_weka-behat',
                'editor_weka-learn',
                'editor_weka-default',
                'totara_playlist-comment',
                'totara_playlist-summary',
                'container_workspace-description',
                'container_workspace-discussion',
                'engage_article-content',
                'engage_article-comment',
                'performelement_static_content-content',
            ]
        );
    }
}