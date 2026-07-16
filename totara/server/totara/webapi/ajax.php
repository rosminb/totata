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
 * @author Fabian Derschatta <fabian.derschatta@totaralearning.com>
 * @package totara_webapi
 */

/*
 * This file is not intended to be used directly from Javascript code,
 * all requests must be done via public API in core/webapi AMD module
 * or via the Apollo client code in VueJS.
 *
 * This endpoint is not a public API, the parameters or data structure
 * may change even in stable branches.
 *
 * The batching support is intended only for fast, non-recursive, read-only
 * queries. Batching is not suitable for mutations because execution is not
 * interrupted by errors and order of execution may not be guaranteed in future.
 */

use core\webapi\execution_context;
use totara_webapi\graphql;
use totara_webapi\local\util;
use totara_webapi\server;

// Find out if we should start session, we do this to allow
// concurrent requests for resources like strings, templates and flex icons.

ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (defined('NO_MOODLE_COOKIES')) {
    // This should not happen, dev must be trying to include this page from elsewhere.
    die;
}

require(__DIR__ . '/classes/local/util.php');

if (!file_exists(__DIR__ . '/../../config.php')) {
    util::send_error('Webapi entrypoint cannot be used from Totara web installer', 500);
}

if (empty($_SERVER['REQUEST_METHOD']) or $_SERVER['REQUEST_METHOD'] !== 'POST') {
    util::send_error('Invalid webapi request, only POST method is allowed', 400);
}

define('NO_MOODLE_COOKIES', util::is_nosession_request());
define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

try {
    require(__DIR__ . '/../../config.php');
    set_exception_handler([util::class, 'exception_handler']);
    set_error_handler([util::class, 'error_handler'], E_ALL | E_STRICT);
} catch (Throwable $e) {
    error_log('API error: exception during set up stage - ' . $e->getMessage());
    util::send_error('Unknown internal error', 500);
}
global $PAGE;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/totara/webapi/ajax.php');

$server = new server(execution_context::create(graphql::TYPE_AJAX));
$result = $server->handle_request();
$server->send_response($result);

