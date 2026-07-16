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
defined('MOODLE_INTERNAL') || die();

use editor_weka\factory\extension_loader;
use editor_weka\extension\link;
use editor_weka\extension\text;
use editor_weka\extension\ruler;
use editor_weka\extension\attachment;
use editor_weka\extension\media;
use editor_weka\extension\list_extension;
use editor_weka\extension\emoji;
use editor_weka\extension\hashtag;
use editor_weka\extension\mention;

class editor_weka_extension_loader_testcase extends advanced_testcase {
    /**
     * This test is to annoy people and force them to change the test when they change anything from the function.
     * @return void
     */
    public function test_get_minimal_extensions(): void {
        $extensions = extension_loader::get_minimal_required_extension_classes();
        self::assertCount(3, $extensions);

        self::assertContainsEquals(link::class, $extensions);
        self::assertContainsEquals(text::class, $extensions);
        self::assertContainsEquals(ruler::class, $extensions);
    }

    /**
     * This test is to annoy people and force them to change the test when
     * they change anything from the metadata function.
     *
     * @return void
     */
    public function test_get_standard_extensions(): void {
        $extensions = extension_loader::get_standard_extension_classes();
        self::assertCount(9, $extensions);

        self::assertContainsEquals(link::class, $extensions);
        self::assertContainsEquals(text::class, $extensions);
        self::assertContainsEquals(ruler::class, $extensions);
        self::assertContainsEquals(attachment::class, $extensions);
        self::assertContainsEquals(media::class, $extensions);
        self::assertContainsEquals(list_extension::class, $extensions);
        self::assertContainsEquals(emoji::class, $extensions);
        self::assertContainsEquals(hashtag::class, $extensions);
        self::assertContainsEquals(mention::class, $extensions);
    }

    /**
     * @return void
     */
    public function test_get_all_extensions_classes(): void {
        $extensions = extension_loader::get_all_extension_classes();
        self::assertGreaterThanOrEqual(9, $extensions);
    }
}