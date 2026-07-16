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
 * @author Fabian Derschatta <fabian.derschatta@totaralearning.com>
 * @package core
 */

namespace core;

defined('MOODLE_INTERNAL') || die();

/**
 * This class defines all basic formats related to strings/text
 */
class format implements format_interface {

    // Ths following constants also map to the graphql core_format enums
    public const FORMAT_RAW = 'RAW';
    public const FORMAT_HTML = 'HTML';
    public const FORMAT_PLAIN = 'PLAIN';
    public const FORMAT_MARKDOWN = 'MARKDOWN';
    public const FORMAT_JSON_EDITOR = 'JSON_EDITOR';
    public const FORMAT_MOBILE = 'MOBILE';

    public static function is_defined(string $format): bool {
        return defined('self::FORMAT_'.strtoupper($format));
    }

    public static function get_available(): array {
        return [
            self::FORMAT_RAW,
            self::FORMAT_HTML,
            self::FORMAT_PLAIN,
            self::FORMAT_MARKDOWN,
            self::FORMAT_JSON_EDITOR,
            self::FORMAT_MOBILE
        ];
    }

}
