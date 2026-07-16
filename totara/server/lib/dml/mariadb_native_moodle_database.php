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

use core\dml\sql;

/**
 * Native MariaDB class representing moodle database interface.
 *
 * @package    core_dml
 * @copyright  2013 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/moodle_database.php');
require_once(__DIR__.'/mysqli_native_moodle_database.php');
require_once(__DIR__.'/mysqli_native_moodle_recordset.php');
require_once(__DIR__.'/mysqli_native_moodle_temptables.php');

/**
 * Native MariaDB class representing moodle database interface.
 *
 * @package    core_dml
 * @copyright  2013 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mariadb_native_moodle_database extends mysqli_native_moodle_database {

    /**
     * Marks that we expect the optimizer to turn off materialization.
     *
     * Note that we do not use the MySQL hint system here as at the time of writing this
     * MariaDB (10.4.7) does not support these hints.
     */
    private const MATERIALIZATION_FORCE_OFF_MARKER = '/*optimizer_disable_materialization*/';

    /**
     * Set to true if there is a query coming up, or that has executed that requires
     * materialization to be turned off.
     * When turned on we check the query for a magic token and if found turn materialization off for while the query executes.
     * This property does not get reset.
     * @var bool
     */
    private $materialization_force_off = false;

    /**
     * Returns localised database type name
     * Note: can be used before connect()
     * @return string
     */
    public function get_name() {
        return get_string('nativemariadb', 'install');
    }

    /**
     * Returns localised database configuration help.
     * Note: can be used before connect()
     * @return string
     */
    public function get_configuration_help() {
        return get_string('nativemariadbhelp', 'install');
    }

    /**
     * Returns the database vendor.
     * Note: can be used before connect()
     * @return string The db vendor name, usually the same as db family name.
     */
    public function get_dbvendor() {
        return 'mariadb';
    }

    /**
     * Returns more specific database driver type
     * Note: can be used before connect()
     * @return string db type mysqli, pgsql, oci, mssql, sqlsrv
     */
    protected function get_dbtype() {
        return 'mariadb';
    }

    /**
     * Add configuration for specific versions of MariaDB
     */
    protected function version_specific_support() {
        // MariaDB doesn't have issues specific to Mysql (at least yet), so override it.
    }

    /**
     * Returns the driver specific syntax for the beginning of a word boundary.
     *
     * @since Totara 13.0
     * @return string or empty if not supported
     */
    public function sql_regex_word_boundary_start(): string {
        // MariaDB doesn't have regexp issue specific to MySQL, so override it.
        return '[[:<:]]';
    }

    /**
     * Returns the driver specific syntax for the end of a word boundary.
     *
     * @since Totara 13.0
     * @return string or empty if not supported
     */
    public function sql_regex_word_boundary_end(): string {
        // MariaDB doesn't have regexp issue specific to MySQL, so override it.
        return '[[:>:]]';
    }

    /**
     * It is time to require transactions everywhere.
     *
     * MyISAM is NOT supported!
     *
     * @return bool
     */
    protected function transactions_supported() {
        // Totara: developers should not use transactions in external databases, but we do not prevent it any more.
        return true;
    }

    /**
     * Gets a database optimizer hint given a Totara identifier for it.
     *
     * @since Totara 12.10 + 13.0
     * @param string $hint The hint identifier you want to us
     * @param mixed $parameter A parameter to provide to the DML engine to assist it producing the hint if required.
     * @return string
     */
    public function get_optimizer_hint(string $hint, $parameter = null): string {
        if ($hint === 'mariadb_materialization_force_off') {
            if ($parameter !== null) {
                debugging('The mariadb_materialization_force_off hint does not support parameter use.', DEBUG_DEVELOPER);
            }
            // Disables materialization and returns a marker that must be included in SQL.
            // Note that we do not use the existing hint system here as at the time of writing this
            // MariaDB (10.4.7) does not support the subquery hints.
            // Importantly this works differently to the existing hints.
            // Hints are designed to apply to a block in the query, this will forcibly disable
            // materialization for the whole query, not just a block.
            $this->materialization_force_off = true;
            return self::MATERIALIZATION_FORCE_OFF_MARKER;
        }
        return '';
    }

    /**
     * Execute a query having applied any required environment modifications.
     *
     * @param callable $execution A callable to execute the actual query.
     * @param string|core\dml\sql $sql
     * @param array|null $params
     * @return mixed
     */
    private function query_with_modified_environment(callable $execution, $sql, array $params = null) {
        $disable_materialization = (
            $this->materialization_force_off &&
            strpos($sql, self::MATERIALIZATION_FORCE_OFF_MARKER) !== false
        );

        if ($disable_materialization) {
            if ($sql instanceof \core\dml\sql) {
                $params = $sql->get_params();
            }
            $sql = str_replace(self::MATERIALIZATION_FORCE_OFF_MARKER, '', $sql);

            // Force materialization off for just this session.
            $this->execute('SET SESSION optimizer_switch=\'materialization=off\'');
        }

        $result = $execution($sql, $params);

        if ($disable_materialization) {
            // And return it to the default value.
            $this->execute('SET SESSION optimizer_switch=\'materialization=default\'');
        }

        return $result;
    }

    /**
     * Returns true if the query executions may require environment modification.
     *
     * @return bool
     */
    private function query_requires_environment_modification(): bool {
        return ($this->materialization_force_off);
    }

    /**
     * Get a number of records as an array of objects using a SQL statement.
     *
     * Overridden to apply hints if required.
     *
     * @param string|core\dml\sql $sql
     * @param array|null $params
     * @param int $limitfrom
     * @param int $limitnum
     * @param bool $unique_id
     * @return array
     */
    protected function get_records_sql_raw($sql, array $params = null, $limitfrom = 0, $limitnum = 0, bool $unique_id = true): array {
        if (!$this->query_requires_environment_modification()) {
            // Get out as quick as we can if hints are not required.
            return parent::get_records_sql_raw($sql, $params, $limitfrom, $limitnum, $unique_id);
        }
        // Environment modification is required, send it through the query function so that any required
        // modifications are applied.
        $result = $this->query_with_modified_environment(
            function ($sql, $params) use ($limitfrom, $limitnum, $unique_id) {
                return parent::get_records_sql_raw($sql, $params, $limitfrom, $limitnum, $unique_id);
            },
            $sql,
            $params
        );
        return $result;
    }

    /**
     * Get a number of records as a moodle_recordset using a SQL statement.
     *
     * This method is intended for queries with reasonable result size only,
     * @see moodle_database::get_huge_recordset_sql() if the results might not fit into memory.
     *
     * The result may be used as iterator in foreach(), if you want to obtain
     * an array with incremental numeric keys @see moodle_recordset::to_array()
     *
     * @param string|sql $sql the SQL select query to execute.
     * @param array|null $params array of sql parameters
     * @param int $limitfrom return a subset of records, starting at this point (optional).
     * @param int $limitnum return a subset comprising this many records (optional, required if $limitfrom is set).
     * @return moodle_recordset A moodle_recordset instance.
     * @throws dml_exception A DML specific exception is thrown for any errors.
     */
    public function get_recordset_sql($sql, array $params = null, $limitfrom = 0, $limitnum = 0) {
        if (!$this->query_requires_environment_modification()) {
            // Get out as quick as we can if environment modifications are not required.
            return parent::get_recordset_sql($sql, $params, $limitfrom, $limitnum);
        }
        // Environment modification is required, send it through the query function so that any required
        // modifications are applied.
        $result = $this->query_with_modified_environment(
            function ($sql, $params) use ($limitfrom, $limitnum) {
                return parent::get_recordset_sql($sql, $params, $limitfrom, $limitnum);
            },
            $sql,
            $params
        );
        return $result;
    }

    /**
     * Do not use.
     *
     * @deprecated
     *
     * @param string|core\dml\sql $sql the SQL select query to execute.
     * @param array $params array of sql parameters (optional)
     * @param int $limitfrom return a subset of records, starting at this point (optional).
     * @param int $limitnum return a subset comprising this many records (optional, required if $limitfrom is set).
     * @param int &$count this variable will be filled with count of rows returned by select without limit statement
     * @return counted_recordset A moodle_recordset instance.
     */
    public function get_counted_recordset_sql($sql, array $params = null, $limitfrom = 0, $limitnum = 0, &$count = 0) {
        if (!$this->query_requires_environment_modification()) {
            // Get out as quick as we can if environment modifications are not required.
            return parent::get_counted_recordset_sql($sql, $params, $limitfrom, $limitnum, $count);
        }
        // Environment modification is required, send it through the query function so that any required
        // modifications are applied.
        $result = $this->query_with_modified_environment(
            function ($sql, $params) use ($limitfrom, $limitnum, &$count) {
                return parent::get_counted_recordset_sql($sql, $params, $limitfrom, $limitnum, $count);
            },
            $sql,
            $params
        );
        return $result;
    }
}
