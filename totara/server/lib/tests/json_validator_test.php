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
 * @author  Kian Nguyen <kian.nguyen@totaralearning.com>
 * @package core
 */

use core\json\structure\structure;
use core\json\validation_adapter;
use core\json\type;
use core_phpunit\testcase;

class core_json_validator_testcase extends testcase {
    /**
     * @return void
     */
    public function test_validate_json_with_valid_data(): void {
        $validator = validation_adapter::create_default();
        $data = json_encode([
            'id' => 15,
            'another_obj' => [
                'name' => 'ccccccc',
                'field_2' => 'ddddd'
            ]
        ]);

        $structure = json_encode([
            'type' => type::OBJECT,
            'properties' => [
                'id' => [
                    'type' => type::INT,
                ],
                'email' => [
                    'type' => type::STRING,
                    'maxLength' => 10,
                ],
                'another_obj' => [
                    '$ref' => '#/definitions/obj'
                ]
            ],
            'required' => ['id', 'another_obj'],
            'definitions' => [
                'obj' => [
                    'type' => type::OBJECT,
                    'properties' => [
                        'name' => [
                            'type' => type::STRING,
                            'maxLength' => 10,
                        ],
                        'field_2' => [
                            'type' => type::STRING,
                        ],
                    ],
                    'required' => ['field_2', 'name']
                ]
            ]
        ]);

        $result = $validator->validate($data, json_decode($structure));

        self::assertTrue($result->is_valid());
        self::assertEmpty($result->get_error_message());
    }

    /**
     * @return void
     */
    public function test_validate_json_with_invalid_data_1(): void {
        $structure = json_encode([
            'type' => type::OBJECT,
            'properties' => [
                'id' => [
                    'type' => type::INT,
                ],
                'details' => [
                    'type' => type::OBJECT,
                    'properties' => [
                        'first_name' => [
                            'type' => type::STRING,
                        ],
                        'email' => [
                            'type' => type::STRING,
                        ],
                    ],
                    'required' => ['first_name']
                ],
            ],
            'required' => ['id', 'details']
        ]);

        $validator = validation_adapter::create_default();
        $data = json_encode(
            [
                'id' => 15,
                'details' => []
            ]
            , JSON_FORCE_OBJECT
        );

        $first_result = $validator->validate_by_json_structure($data, $structure);
        self::assertFalse($first_result->is_valid());
        self::assertEquals(
            "Missing field 'first_name', within object at field 'details'.",
            $first_result->get_error_message()
        );
    }

    /**
     * @return void
     */
    public function test_validate_json_with_invalid_data_2(): void {
        $structure = json_encode([
            'type' => type::OBJECT,
            'properties' => [
                'id' => [
                    'type' => type::INT,
                ],
                'details' => [
                    'type' => type::OBJECT,
                    'properties' => [
                        'first_name' => [
                            'type' => type::STRING,
                            'maxLength' => 10
                        ],
                        'email' => [
                            'type' => type::STRING,
                        ],
                    ],
                    'required' => ['first_name']
                ],
            ],
            'required' => ['id', 'details']
        ]);

        $validator = validation_adapter::create_default();
        $data = json_encode(
            [
                'id' => 15,
                'details' => [
                    'first_name' => str_repeat('a', 25),
                ]
            ]
            , JSON_FORCE_OBJECT
        );

        $first_result = $validator->validate_by_json_structure($data, $structure);

        self::assertFalse($first_result->is_valid());
        self::assertEquals(
            "Expect the length of field 'first_name' to not exceed 10, actual length is 25.",
            $first_result->get_error_message()
        );
    }

    /**
     * @return void
     */
    public function test_validate_json_with_invalid_data_3(): void {
        $structure = json_encode([
            'type' => type::OBJECT,
            'properties' => [
                'id' => [
                    'type' => type::INT,
                ],
                'details' => [
                    'type' => type::OBJECT,
                    'properties' => [
                        'first_name' => [
                            'type' => type::STRING,
                            'format' => 'param_alpha'
                        ],
                        'email' => [
                            'type' => type::STRING,
                        ],
                    ],
                    'required' => ['first_name']
                ],
            ],
            'required' => ['id', 'details']
        ]);

        $validator = validation_adapter::create_default();
        $data = json_encode(
            [
                'id' => 15,
                'details' => [
                    'first_name' => 'Hello world',
                ]
            ]
            , JSON_FORCE_OBJECT
        );

        $first_result = $validator->validate_by_json_structure($data, $structure);

        self::assertFalse($first_result->is_valid());
        self::assertEquals(
            "The field 'first_name' value 'Hello world' failed the format 'param_alpha' of type 'string'.",
            $first_result->get_error_message()
        );
    }

    /**
     * @return void
     */
    public function test_validate_json_with_constant(): void {
        $structure = json_encode([
            'type' => type::OBJECT,
            'properties' => [
                'id' => [
                    'type' => type::INT,
                    'const' => 42
                ]
            ],
            'required' => ['id']
        ]);

        $validator = validation_adapter::create_default();
        $result_one = $validator->validate_by_json_structure(json_encode(['id' => 42]), $structure);

        self::assertTrue($result_one->is_valid());
        self::assertEmpty($result_one->get_error_message());

        $result_two = $validator->validate_by_json_structure(json_encode(['id' => 52]), $structure);
        self::assertFalse($result_two->is_valid());
        self::assertEquals(
            "Field 'id' does not match value '42', but receive '52'.",
            $result_two->get_error_message()
        );
    }

    /**
     * Test validate json with oneOf operator.
     *
     * @return void
     */
    public function test_validate_json_object_with_one_of(): void {
        $validator = validation_adapter::create_default();
        $structure = json_encode([
            'type' => type::OBJECT,
            'properties' => [
                'id' => [
                    'type' => type::INT,
                    'oneOf' => [
                        ['const' => 15],
                        ['const' => 42]
                    ]
                ],
            ]
        ]);

        $valid_data = [
            json_encode(['id' => 42]),
            json_encode(['id' => 15]),
        ];

        foreach ($valid_data as $valid_datum) {
            $result = $validator->validate_by_json_structure($valid_datum, $structure);

            self::assertTrue($result->is_valid(), "Expect the result to be valid for datum {$valid_datum}");
            self::assertEmpty($result->get_error_message());

            unset($result);
        }

        $invalid_data = [
            json_encode(['id' => 11]),
            json_encode(['id' => 0]),
            json_encode(['id' => -1])
        ];

        foreach ($invalid_data as $invalid_datum) {
            $result = $validator->validate_by_json_structure($invalid_datum, $structure);

            self::assertFalse($result->is_valid(), "Expect the result to be invalid for datum {$invalid_datum}");
            self::assertEquals(
                "Expect exactly 1 matched of data model, but there are 0 matches.",
                $result->get_error_message()
            );
        }
    }

    /**
     * @return void
     */
    public function test_validate_json_object_with_enum_keyword(): void {
        $validator = validation_adapter::create_default();
        $structure = json_encode([
            'type' => type::OBJECT,
            'properties' => [
                'id' => [
                    'type' => type::INT,
                    'enum' => [1, 2, 3, 4],
                ]
            ]
        ]);

        $valid_data = [
            json_encode(['id' => 1]),
            json_encode(['id' => 2]),
            json_encode(['id' => 3]),
            json_encode(['id' => 4]),
        ];

        foreach ($valid_data as $valid_datum) {
            $result = $validator->validate_by_json_structure($valid_datum, $structure);

            self::assertTrue($result->is_valid(), "Expect the result to be valid for datum {$valid_datum}");
            self::assertEmpty($result->get_error_message());

            unset($result);
        }

        $invalid_data = [
            json_encode(['id' => 5]),
            json_encode(['id' => 6]),
            json_encode(['id' => 7]),
            json_encode(['id' => 8]),
            json_encode(['id' => 9]),
        ];

        foreach ($invalid_data as $invalid_datum) {
            $json = json_decode($invalid_datum);
            $result = $validator->validate_by_json_structure($json, $structure);

            self::assertFalse($result->is_valid(), "Expect the result to be invalid for datum {$invalid_datum}");
            self::assertEquals(
                "Expect the value of field 'id' to be either of 1, 2, 3, 4, but receive '{$json->id}'.",
                $result->get_error_message(),
            );
        }
    }

    /**
     * @return void
     */
    public function test_validate_json_object_with_invalid_type(): void {
        $validator = validation_adapter::create_default();
        $structure = json_encode([
            'type' => type::OBJECT,
            'properties' => [
                'id' => [
                    'type' => type::INT
                ]
            ]
        ]);

        $obj = new stdClass();
        $obj->id = 'hello_world';

        $result = $validator->validate_by_json_structure($obj, $structure);
        self::assertFalse($result->is_valid());
        self::assertEquals(
            "Expect type of field 'id' to be integer, but receive type string.",
            $result->get_error_message()
        );
    }

    /**
     * @return void
     */
    public function test_validate_json_object_with_no_additional_fields(): void {
        $validator = validation_adapter::create_default();
        $structure = json_encode([
            'type' => type::OBJECT,
            'properties' => [
                'id' => [
                    'type' => type::INT
                ],
                'name' => [
                    'type' => type::STRING
                ]
            ],
            'required' => ['id', 'name'],
            structure::ADDITIONAL_PROPERTIES => false
        ]);

        $result_one = $validator->validate_by_json_structure(
            json_encode([
                'id' => 42,
                'name' => 'structure'
            ]),
            $structure
        );

        self::assertTrue($result_one->is_valid());
        self::assertEmpty($result_one->get_error_message());

        $result_two = $validator->validate_by_json_structure(
            json_encode([
                'id' => 54,
                'name' => 'structure',
                'additional' => 'x'
            ]),
            $structure
        );

        self::assertFalse($result_two->is_valid());
        self::assertEquals(
            "There are unexpected additional properties",
            $result_two->get_error_message()
        );
    }

    /**
     * @return void
     */
    public function test_validate_json_object_with_additional_fields(): void {
        $validator = validation_adapter::create_default();
        $structure = json_encode([
            'type' => type::OBJECT,
            'properties' => [
                'id' => [
                    'type' => type::INT
                ]
            ]
        ]);

        $obj_one = new stdClass();
        $obj_one->id = 42;

        $result_one = $validator->validate_by_json_structure($obj_one, $structure);
        self::assertTrue($result_one->is_valid());
        self::assertEmpty($result_one->get_error_message());

        $obj_two = clone $obj_one;
        $obj_two->additional = 'xxx';

        $result_two = $validator->validate_by_json_structure($obj_two, $structure);
        self::assertTrue($result_two->is_valid());
        self::assertEmpty($result_two->get_error_message());
    }
}