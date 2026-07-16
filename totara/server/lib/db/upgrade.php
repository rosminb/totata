<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file keeps track of upgrades to Moodle.
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package   core_install
 * @category  upgrade
 * @copyright 2006 onwards Martin Dougiamas  http://dougiamas.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Main upgrade tasks to be executed on Moodle version bump
 *
 * This function is automatically executed after one bump in the Moodle core
 * version is detected. It's in charge of performing the required tasks
 * to raise core from the previous version to the next one.
 *
 * It's a collection of ordered blocks of code, named "upgrade steps",
 * each one performing one isolated (from the rest of steps) task. Usually
 * tasks involve creating new DB objects or performing manipulation of the
 * information for cleanup/fixup purposes.
 *
 * Each upgrade step has a fixed structure, that can be summarised as follows:
 *
 * if ($oldversion < XXXXXXXXXX.XX) {
 *     // Explanation of the update step, linking to issue in the Tracker if necessary
 *     upgrade_set_timeout(XX); // Optional for big tasks
 *     // Code to execute goes here, usually the XMLDB Editor will
 *     // help you here. See {@link http://docs.moodle.org/dev/XMLDB_editor}.
 *     upgrade_main_savepoint(true, XXXXXXXXXX.XX);
 * }
 *
 * All plugins within Moodle (modules, blocks, reports...) support the existence of
 * their own upgrade.php file, using the "Frankenstyle" component name as
 * defined at {@link http://docs.moodle.org/dev/Frankenstyle}, for example:
 *     - {@link xmldb_page_upgrade($oldversion)}. (modules don't require the plugintype ("mod_") to be used.
 *     - {@link xmldb_auth_manual_upgrade($oldversion)}.
 *     - {@link xmldb_workshopform_accumulative_upgrade($oldversion)}.
 *     - ....
 *
 * In order to keep the contents of this file reduced, it's allowed to create some helper
 * functions to be used here in the {@link upgradelib.php} file at the same directory. Note
 * that such a file must be manually included from upgrade.php, and there are some restrictions
 * about what can be used within it.
 *
 * For more information, take a look to the documentation available:
 *     - Data definition API: {@link http://docs.moodle.org/dev/Data_definition_API}
 *     - Upgrade API: {@link http://docs.moodle.org/dev/Upgrade_API}
 *
 * @param int $oldversion
 * @return bool always true
 */
function xmldb_main_upgrade($oldversion) {
    global $CFG, $DB;
    require_once(__DIR__ .'/upgradelib.php');

    $dbman = $DB->get_manager();

    if ($oldversion < 2017111309.00) {
        // Somebody must have hacked upgrade checks, stop them here.
        throw new coding_exception('Upgrades are supported only from Totara 13.0 or later!');
    }

    // Totara 13.0 release line.

    if ($oldversion < 2020101500) {
        // Remove all MNET functionality and settings.

        $droptables = ['mnet_sso_access_control', 'mnet_session', 'mnet_remote_service2rpc', 'mnet_service2rpc',
            'mnet_service', 'mnet_remote_rpc', 'mnet_rpc', 'mnet_log', 'mnet_host2service', 'mnet_host', 'mnet_application'];
        foreach ($droptables as $tablename) {
            $table = new xmldb_table($tablename);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }

        $DB->set_field('user', 'auth', 'nologin', ['auth' => 'mnet']);

        $table = new xmldb_table('user');
        $index = new xmldb_index('username', XMLDB_INDEX_UNIQUE, array('mnethostid', 'username'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        $table = new xmldb_table('user');
        $index = new xmldb_index('username', XMLDB_INDEX_UNIQUE, array('username'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        unset_config('mnetkeylifetime');
        unset_config('mnet_dispatcher_mode');
        unset_config('mnet_localhost_id');

        upgrade_main_savepoint(true, 2020101500.00);
    }

    if ($oldversion < 2020110500.00) {
        // We will keep mnethostid for the sake of basic compatibility with Moodle auth plugins.
        $table = new xmldb_table('user');
        $field = new xmldb_field('mnethostid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'suspended', ['1']);
        if ($dbman->field_exists($table, $field)) {
            $DB->set_field('user', 'mnethostid', '1', []);
            $dbman->change_field_default($table, $field);
            $dbman->change_field_allowed_values($table, $field);
        } else {
            $dbman->add_field($table, $field);
        }

        upgrade_main_savepoint(true, 2020110500.00);
    }

    if ($oldversion < 2020120800.00) {
        // Make sure Learner role assignments in programs are not reported as unsupported.
        $role = $DB->get_record('role', ['shortname' => 'student']);
        if ($role) {
            if (!$DB->record_exists('role_context_levels', ['roleid' => $role->id, 'contextlevel' => CONTEXT_PROGRAM])) {
                $record = new stdClass();
                $record->roleid = $role->id;
                $record->contextlevel = CONTEXT_PROGRAM;
                $DB->insert_record('role_context_levels', $record);
            }
        }
        // Savepoint reached.
        upgrade_main_savepoint(true, 2020120800.00);
    }

    if ($oldversion < 2020122100.00) {
        // Define table virtualmeeting to be created, for storing virtual meetings.
        $table = new xmldb_table('virtualmeeting');

        // Adding fields to table
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('plugin', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys and indexes to table
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'), 'cascade');
        $table->add_index('plugin', XMLDB_INDEX_NOTUNIQUE, array('plugin'));

        // Conditionally launch create table
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table virtualmeeting_config to be created, for storing virtual meeting config data.
        $table = new xmldb_table('virtualmeeting_config');

        // Adding fields to table
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('value', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);
        $table->add_field('virtualmeetingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);

        // Adding keys and indexes to table
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('vmconfig_fk', XMLDB_KEY_FOREIGN, array('virtualmeetingid'), 'virtualmeeting', array('id'), 'cascade');
        $table->add_index('vmid_name_ix', XMLDB_INDEX_UNIQUE, array('virtualmeetingid', 'name'));

        // Conditionally launch create table
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table virtualmeeting_auth to be created, for storing virtual meeting auth tokens.
        $table = new xmldb_table('virtualmeeting_auth');

        // Adding fields to table
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('plugin', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('access_token', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('refresh_token', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('timeexpiry', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);

        // Adding keys and indexes to table
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('user_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'), 'cascade');
        $table->add_index('pluginuser_ix', XMLDB_INDEX_UNIQUE, array('plugin', 'userid'));

        // Conditionally launch create table
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020122100.00);
    }

    if ($oldversion < 2020122900.00) {
        // Define table 'messages' to be created.
        $table = new xmldb_table('messages');

        // Adding fields to table 'messages'.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('useridfrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('conversationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('subject', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('fullmessage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('fullmessageformat', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('fullmessagehtml', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('smallmessage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table 'messages'.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('useridfrom', XMLDB_KEY_FOREIGN, array('useridfrom'), 'user', array('id'));
        $table->add_key('conversationid', XMLDB_KEY_FOREIGN, array('conversationid'), 'message_conversations', array('id'));

        // Conditionally launch create table for 'messages'.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table 'message_conversations' to be created.
        $table = new xmldb_table('message_conversations');

        // Adding fields to table 'message_conversations'.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table 'message_conversations'.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for 'message_conversations'.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table 'message_conversation_members' to be created.
        $table = new xmldb_table('message_conversation_members');

        // Adding fields to table 'message_conversation_members'.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('conversationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table 'message_conversation_members'.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('conversationid', XMLDB_KEY_FOREIGN, array('conversationid'), 'message_conversations', array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

        // Conditionally launch create table for 'message_conversation_members'.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table 'message_user_actions' to be created.
        $table = new xmldb_table('message_user_actions');

        // Adding fields to table 'message_user_actions'.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('messageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('action', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table 'message_user_actions'.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $table->add_key('messageid', XMLDB_KEY_FOREIGN, array('messageid'), 'messages', array('id'));

        // Conditionally launch create table for 'message_user_actions'.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table 'notifications' to be created.
        $table = new xmldb_table('notifications');

        // Adding fields to table 'notifications'.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('useridfrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('useridto', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('subject', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('fullmessage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('fullmessageformat', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('fullmessagehtml', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('smallmessage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('eventtype', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('contexturl', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('contexturlname', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timeread', XMLDB_TYPE_INTEGER, '10', null, false, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table 'notifications'.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('useridto', XMLDB_KEY_FOREIGN, array('useridto'), 'user', array('id'));

        // Conditionally launch create table for 'notifications'.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table 'message_conversations' to be updated.
        $table = new xmldb_table('message_conversations');
        $field = new xmldb_field('convhash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'id');

        // Conditionally launch add field 'convhash'.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Conditionally launch add index.
        $index = new xmldb_index('convhash', XMLDB_INDEX_UNIQUE, array('convhash'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define table 'message_user_actions' to add an index to.
        $table = new xmldb_table('message_user_actions');

        // Conditionally launch add index.
        $index = new xmldb_index('userid_messageid_action', XMLDB_INDEX_UNIQUE, array('userid, messageid, action'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define table 'messages' to add an index to.
        $table = new xmldb_table('messages');

        // Conditionally launch add index.
        $index = new xmldb_index('conversationid_timecreated', XMLDB_INDEX_NOTUNIQUE, array('conversationid, timecreated'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020122900.00);
    }

    if ($oldversion < 2021040700.00) {
        // Define table notifiable_event_queue to be created.
        $table = new xmldb_table('notifiable_event_queue');

        // Adding fields to table notifiable_event_queue.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('resolver_class_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('event_data', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('context_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('area', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('item_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('time_created', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table notifiable_event_queue.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('context_id_key', XMLDB_KEY_FOREIGN, array('context_id'), 'context', array('id'));

        // Adding indexes to table notifiable_event_queue.
        $table->add_index('resolver_class_name_index', XMLDB_INDEX_NOTUNIQUE, array('resolver_class_name'));

        // Conditionally launch create table for notifiable_event_queue.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table notification_queue to be created.
        $table = new xmldb_table('notification_queue');

        // Adding fields to table notification_queue.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('notification_preference_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('event_data', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('context_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('area', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('item_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('time_created', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('scheduled_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table notification_queue.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('context_id_key', XMLDB_KEY_FOREIGN, array('context_id'), 'context', array('id'));
        $table->add_key('notification_preference_id_key', XMLDB_KEY_FOREIGN, array('notification_preference_id'), 'notification_preference', array('id'));

        // Conditionally launch create table for notification_queue.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table notification_preference to be created.
        $table = new xmldb_table('notification_preference');

        // Adding fields to table notification_preference.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ancestor_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('resolver_class_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('notification_class_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('context_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('area', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('item_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('title', XMLDB_TYPE_CHAR, '1024', null, null, null, null);
        $table->add_field('recipient', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('subject', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('subject_format', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('body_format', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('time_created', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('schedule_offset', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('forced_delivery_channels', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Adding keys to table notification_preference.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('context_id_key', XMLDB_KEY_FOREIGN, array('context_id'), 'context', array('id'));

        // Adding indexes to table notification_preference.
        $table->add_index('resolver_class_name_index', XMLDB_INDEX_NOTUNIQUE, array('resolver_class_name'));
        $table->add_index('notification_class_name_index', XMLDB_INDEX_NOTUNIQUE, array('notification_class_name'));

        // Conditionally launch create table for notification_preference.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table notifiable_event_preference to be created.
        $table = new xmldb_table('notifiable_event_preference');

        // Adding fields to table notifiable_event_preference.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('resolver_class_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('context_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('area', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('item_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('default_delivery_channels', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Adding keys to table notifiable_event_preference.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('context_id_key', XMLDB_KEY_FOREIGN, array('context_id'), 'context', array('id'));

        // Adding indexes to table notifiable_event_preference.
        $table->add_index('resolver_class_name_index', XMLDB_INDEX_NOTUNIQUE, array('resolver_class_name'));

        // Conditionally launch create table for notifiable_event_preference.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table notifiable_event_user_preference to be created.
        $table = new xmldb_table('notifiable_event_user_preference');

        // Adding fields to table notifiable_event_user_preference.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('resolver_class_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('context_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('area', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('item_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('delivery_channels', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Adding keys to table notifiable_event_user_preference.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('user_id_key', XMLDB_KEY_FOREIGN, array('user_id'), 'user', array('id'));
        $table->add_key('context_id_key', XMLDB_KEY_FOREIGN, array('context_id'), 'context', array('id'));

        // Adding indexes to table notifiable_event_user_preference.
        $table->add_index('user_resolver_class_name_index', XMLDB_INDEX_NOTUNIQUE, array('resolver_class_name'));
        $table->add_index('user_context_resolver_class_uindex', XMLDB_INDEX_UNIQUE, array('user_id', 'context_id', 'resolver_class_name'));

        // Conditionally launch create table for notifiable_event_user_preference.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021040700.00);
    }

    if ($oldversion < 2021061700.00) {
        // Define field additional_criteria to be added to notification_preference.
        $table = new xmldb_table('notification_preference');
        $field = new xmldb_field('additional_criteria', XMLDB_TYPE_TEXT, null, null, null, null, null, 'title');

        // Conditionally launch add field additional_criteria.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021061700.00);
    }

    if ($oldversion < 2021092200.00) {
        // Define index title_index (not unique) to be dropped from notification_preference.
        $table = new xmldb_table('notification_preference');
        $index = new xmldb_index('title_index', XMLDB_INDEX_NOTUNIQUE, array('title'));

        // Conditionally launch drop index title_index.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Changing precision of field title on table notification_preference to (1024).
        $field = new xmldb_field('title', XMLDB_TYPE_CHAR, '1024', null, null, null, null, 'item_id');

        // Launch change of precision for field title.
        $dbman->change_field_precision($table, $field);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021092200.00);
    }

    if ($oldversion < 2021120100.00) {
        $table = new xmldb_table('course');

        // Define field duedate to be added to course.
        $field = new xmldb_field('duedate', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'containertype');

        // Conditionally launch add field duedate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field duedateoffsetamount to be added to course.
        $field = new xmldb_field('duedateoffsetamount', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'duedate');

        // Conditionally launch add field duedateoffsetamount.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field duedateoffsetunit to be added to course.
        $field = new xmldb_field('duedateoffsetunit', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'duedate');

        // Conditionally launch add field duedateoffsetunit.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021120100.00);
    }

    if ($oldversion < 2021121700.00) {
        $table = new xmldb_table('course_completions');

        // Define field duedate to be added to course.
        $field = new xmldb_field('duedate', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'renewalstatus');

        // Conditionally launch add field duedate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021121700.00);
    }

    if ($oldversion < 2022042601.00) {
        $table = new xmldb_table('course_sections');

        // Changing precision of field name on table course_sections to (1024).
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '1024', null, null, null, null, 'section');

        // Launch change of precision for field title.
        $dbman->change_field_precision($table, $field);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022042601.00);
    }

    if ($oldversion < 2022042601.01) {
        $table = new xmldb_table('totara_navigation');

        // Changing precision of field url on table totara_navigation to (1333).
        $field = new xmldb_field('url', XMLDB_TYPE_CHAR, '1333', null, null, null, null, 'title');
        // Launch change of precision for field title.
        $dbman->change_field_precision($table, $field);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022042601.01);
    }

    return true;
}
