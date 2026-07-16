<?php
/**
 * This file is part of Totara LMS
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
 * @package core
 */
namespace core\json_editor\node;

use core\json_editor\helper\node_helper;
use core\json_editor\node\abstraction\block_node;
use html_writer;
use core\json_editor\formatter\formatter;
use core\json_editor\node\file\base_file;

/**
 * Class image
 * @package core\json_editor\node
 */
final class image extends base_file implements block_node {
    /**
     * @var string
     */
    private $alttext;

    /**
     * @param array $node
     * @return image
     */
    public static function from_node(array $node): node {
        /** @var image $image */
        $image = parent::from_node($node);
        $attrs = $node['attrs'];

        if (isset($attrs['alttext'])) {
            $image->alttext = (string) $attrs['alttext'];
        } else {
            $image->alttext = '';
        }

        return $image;
    }

    /**
     * @param formatter $formatter
     * @return string
     */
    public function to_html(formatter $formatter): string {
        return html_writer::empty_tag(
            'img',
            [
                'src' => $this->get_file_url()->out(false),
                'alt' => $this->alttext,
                'class' => 'img-responsive',
            ]
        );
    }

    /**
     * @param array $raw_node
     * @return bool
     */
    public static function validate_schema(array $raw_node): bool {
        $result = parent::validate_schema($raw_node);
        if (!$result) {
            return false;
        }

        $attrs = $raw_node['attrs'];
        if (!array_key_exists('alttext', $attrs)) {
            return false;
        }

        // Validate on attribute keys.
        $input_keys = array_keys($attrs);
        return node_helper::check_keys_match($input_keys, ['filename', 'url', 'alttext']);
    }

    /**
     * @param formatter $formatter
     * @return string
     */
    public function to_text(formatter $formatter): string {
        $url = $this->get_file_url();
        return "({$this->filename})[{$url->out(false)}]\n\n";
    }

    /**
     * @return string
     */
    protected static function do_get_type(): string {
        return 'image';
    }

    /**
     * @param \stored_file $file
     * @return array
     */
    public static function create_raw_node_from_image(\stored_file $file): array {
        global $CFG;
        require_once("{$CFG->dirroot}/lib/filelib.php");

        $filetype = $file->get_mimetype();
        $result = file_mimetype_in_typegroup($filetype, 'web_image');

        if (!$result) {
            throw new \coding_exception("Invalid image file");
        }

        $url = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );

        if ('user' === $file->get_component() && 'draft' === $file->get_filearea()) {
            $url = \moodle_url::make_draftfile_url(
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );
        }

        return [
            'type' => static::get_type(),
            'attrs' => [
                'filename' => $file->get_filename(),
                'url' => $url->out(),
                'alttext' => ''
            ],
        ];
    }

    /**
     * @return string
     */
    public function get_alt_text(): string {
        return $this->alttext;
    }
}