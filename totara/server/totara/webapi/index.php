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
 * @author Petr Skoda <petr.skoda@totaralearning.com>
 * @package totara_webapi
 */

require(__DIR__ . '/../../config.php');
require_once("$CFG->libdir/adminlib.php");

admin_externalpage_setup('webapi');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'totara_webapi'));

// NOTE: this page is for developers only, do not localise!

$info = file_get_contents(__DIR__ . '/README.md');

echo $OUTPUT->box_start('generalbox');
echo markdown_to_html($info);
echo $OUTPUT->box_end();

$devmode = (defined('GRAPHQL_DEVELOPMENT_MODE') and GRAPHQL_DEVELOPMENT_MODE);

$schemaurl = new moodle_url('/totara/webapi/dev_graphql_schema.php');
$executorurl = new moodle_url('/totara/webapi/dev_graphql_executor.php');

echo '<dl class="dl-horizontal">';
echo '<dt>GraphQL schema</dt>';
echo '<dd>';
if ($devmode) {
    echo html_writer::link($schemaurl, $schemaurl);
} else {
    echo '<s>' . $schemaurl->out() . '</s>';
}
echo '</dd>';
echo '<dt>GraphQL endpoint</dt>';
echo '<dd>';
if ($devmode) {
    echo $executorurl->out();
} else {
    echo '<s>' . $executorurl->out() . '</s>';
}
echo '</dd>';
echo '</dl>';

echo $OUTPUT->footer();

