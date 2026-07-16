<?php
/**
 * This file is part of Totara Core
 *
 * Copyright (C) 2021 onwards Totara Learning Solutions LTD
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
 * @author Qingyang Liu <qingyang.liu@totaralearning.com>
 * @package totara_oauth2
 */
namespace totara_oauth2\model;

use coding_exception;
use core\orm\entity\model;
use core\orm\query\builder;
use totara_oauth2\config;
use totara_oauth2\entity\client_provider as entity;
use totara_oauth2\grant_type;

/**
 *
 * @property-read int $id
 * @property-read string $client_id
 * @property-read string $client_secret
 * @property-read string $id_number
 * @property-read string $name
 * @property-read string|null $description
 * @property-read int|null $description_format
 * @property-read string|null $scope
 * @property-read string|null $grant_types
 * @property-read int $time_created
 *
 * @property-read string $detail_scope
 */
class client_provider extends model {
    /**
     * @var entity
     */
    protected $entity;

    /**
     * @var string[]
     */
    protected $entity_attribute_whitelist = [
        'id',
        'client_id',
        'client_secret',
        'id_number',
        'name',
        'description',
        'description_format',
        'scope',
        'grant_types',
        'time_created'
    ];

    /**
     * @var string[]
     */
    protected $model_accessor_whitelist = [
        'detail_scope',
    ];

    /**
     * @return string
     */
    protected static function get_entity_class(): string {
        return entity::class;
    }

    /**
     * @return string|null
     */
    public function get_detail_scope(): ?string {
        switch ($this->scope) {
            case config::XAPI_WRITE: {
                return get_string('xapi_write', 'totara_oauth2');
            }
            default: {
                return null;
            }
        }
    }

    /**
     * @param string $name
     * @param string $scope_type
     * @param int $format
     * @param string|null $description
     * @return static
     */
    public static function create(
        string $name,
        string $scope_type,
        int $format,
        string $description = null
    ): self {
        $entity = new entity();
        $entity->client_id = self::generate_unique_value('client_id');
        $entity->client_secret = self::generate_unique_value('client_secret');
        $entity->name = $name;
        $entity->description_format = $format;
        $entity->description = $description ?? '';
        $entity->grant_types = grant_type::get_client_credentials();
        $entity->scope = $scope_type;

        $entity->save();

        return self::load_by_entity($entity);
    }

    /**
     * @param string $field_name
     * @return string
     */
    private static function generate_unique_value(string $field_name): string {
        $db = builder::get_db();

        $i = 0;
        while (true) {
            if ($i === config::MAX_GENERATION_ATTEMPTS) {
                throw new coding_exception("${field_name} can not be auto generated");
            }

            if ($field_name === 'client_id') {
                $field_value = random_string(16);
            } else if ($field_name === 'client_secret') {
                $field_value = random_string(24);
            }

            if (!$db->record_exists(entity::TABLE, [$field_name => $field_value])) {
                break;
            }

            $i++;
        }

        return $field_value;
    }

    /**
     * @return void
     */
    public function delete(): void {
        $this->entity->delete();
    }
}
