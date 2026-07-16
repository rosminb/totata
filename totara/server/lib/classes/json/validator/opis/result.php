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
namespace core\json\validator\opis;

use coding_exception;
use core\json\abstraction\validation_result;
use core\json\structure\structure;
use Opis\JsonSchema\ValidationError;
use Opis\JsonSchema\ValidationResult;

/**
 * OPIS validation result wrapper.
 */
class result implements validation_result {
    /**
     * @var ValidationResult
     */
    protected $validation_result;

    /**
     * result constructor.
     * @param ValidationResult $rs
     */
    public function __construct(ValidationResult $rs) {
        $this->validation_result = $rs;
    }

    /**
     * @return bool
     */
    public function is_valid(): bool {
        return $this->validation_result->isValid();
    }

    /**
     * Returns null, if the data pointer of validation error is empty. Which
     * is unable to resolve the field name.
     *
     * @param ValidationError $error
     * @return string|null
     */
    private static function get_field_name(ValidationError $error): ?string {
        $data_pointer = $error->dataPointer();
        if (empty($data_pointer)) {
            return null;
        }

        return end($data_pointer);
    }

    /**
     * This function is being set as static, because we would not want the function
     * itself to be able to access into the properties of this class at all.
     *
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_from_validation_error(
        ValidationError $error,
        ?ValidationError $parent_error = null
    ): string {
        $keyword = $error->keyword();

        switch ($keyword) {
            case 'required':
                return self::get_error_message_for_keyword_required($error);

            case 'maxLength':
                return self::get_error_message_for_keyword_max_length($error);

            case 'minLength':
                return self::get_error_message_for_keyword_min_length($error);

            case 'format':
                return self::get_error_message_for_keyword_format($error);

            case structure::ADDITIONAL_PROPERTIES:
                return self::get_error_message_for_keyword_additional_properties($error);

            case structure::ANY_OF:
                return self::get_error_message_for_keyword_any_of($error);

            case 'const':
                return self::get_error_message_for_keyword_const($error);

            case structure::MIN_ITEMS:
                return self::get_error_message_for_keyword_min_items($error);

            case structure::MAX_ITEMS:
                return self::get_error_message_for_keyword_max_items($error);

            case 'contains':
                return self::get_error_message_for_keyword_contains($error, $parent_error);

            case 'minimum':
                return self::get_error_message_for_keyword_minimum($error);

            case 'maximum':
                return self::get_error_message_for_keyword_maximum($error);

            case structure::ONE_OF:
                $keyword_args = $error->keywordArgs();
                return "Expect exactly 1 matched of data model, but there are {$keyword_args['matched']} matches.";

            case structure::ALL_OF:
                if (1 === $error->subErrorsCount()) {
                    [$sub_error] = $error->subErrors();
                    return self::get_error_message_from_validation_error($sub_error, $error);
                }

                break;

            case 'enum':
                return self::get_error_message_for_keyword_enum($error);

            case 'type':
                return self::get_error_mesage_for_keyword_type($error);

            default:
                break;
        }

        return "Unknown error";
    }

    /**
     * Returns message error for keyword "type".
     *
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_mesage_for_keyword_type(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), 'type');
        $keyword_args = $error->keywordArgs();

        $field_name = self::get_field_name($error);
        $field_type = is_numeric($field_name) ? 'index' : 'field';

        $expected = $keyword_args['expected'];
        $used = $keyword_args['used'];

        return "Expect type of {$field_type} '{$field_name}' to be {$expected}, but receive type {$used}.";
    }

    /**
     * Returns message error for keyword "enum".
     *
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_for_keyword_enum(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), 'enum');

        $keyword_args = $error->keywordArgs();
        $expected = $keyword_args['expected'];

        $field = self::get_field_name($error);

        $expected_str = implode(', ', $expected);
        $data_value = $error->data();

        return "Expect the value of field '{$field}' to be either of {$expected_str}, but receive '{$data_value}'.";
    }

    /**
     * Returns message error for keyword "required".
     *
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_for_keyword_required(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), 'required');

        $keyword_args = $error->keywordArgs();
        $field_name = self::get_field_name($error);

        if (null === $field_name) {
            return "Missing field '{$keyword_args['missing']}'.";
        }

        $schema = $error->schema();
        $type = $schema->type ?? 'unknown';

        $field_type = is_numeric($field_name) ? 'index' : 'field';
        return "Missing field '{$keyword_args['missing']}', within {$type} at {$field_type} '{$field_name}'.";
    }

    /**
     * Returns message error for keyword "maxLength".
     *
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_for_keyword_max_length(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), 'maxLength');

        $keyword_args = $error->keywordArgs();
        $field_name = self::get_field_name($error);

        $max = $keyword_args['max'];
        $length = $keyword_args['length'];

        if (null === $field_name) {
            return "Expect the length to not exceed {$max}, actual length is {$length}.";
        }

        return "Expect the length of field '{$field_name}' to not exceed {$max}, actual length is {$length}.";
    }

    /**
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_for_keyword_min_length(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), 'minLength');
        $keyword_args = $error->keywordArgs();

        $field_name = self::get_field_name($error);
        $min = $keyword_args['min'];
        $length = $keyword_args['length'];

        if (null === $field_name) {
            return "Expect the length to exceed {$min}, actual length is {$length}.";
        }

        return "Expect the length of field '{$field_name}' to exceed {$min}, actual length is {$length}.";
    }

    /**
     * Returns message error for keyword "format".
     *
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_for_keyword_format(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), 'format');

        $data_value = $error->data();
        $keyword_args = $error->keywordArgs();

        $field_name = self::get_field_name($error);
        $format = $keyword_args['format'];
        $type = $keyword_args['type'];

        return "The field '{$field_name}' value '{$data_value}' failed the format '{$format}' of type '{$type}'.";
    }

    /**
     * Returns message error for keyword "const".
     *
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_for_keyword_const(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), 'const');

        $keyword_args = $error->keywordArgs();
        $data_value = $error->data();

        $field = self::get_field_name($error);
        return "Field '{$field}' does not match value '{$keyword_args['expected']}', but receive '{$data_value}'.";
    }

    /**
     * Returns message error for keyword "minItems".
     *
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_for_keyword_min_items(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), structure::MIN_ITEMS);

        $keyword_args = $error->keywordArgs();
        $field = static::get_field_name($error);

        $min = $keyword_args['min'];
        $count = $keyword_args['count'];

        if (null === $field) {
            // No field was found, which means that we are
            // validating the list of items.
            return "Expect the min items to be {$min}, but actual count is {$count}.";
        }

        return "Expect the min items of field '{$field}' to be {$min}, but actual count is {$count}.";
    }

    /**
     * Returns message error for keyword "maxItems".
     *
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_for_keyword_max_items(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), structure::MAX_ITEMS);

        $keyword_args = $error->keywordArgs();
        $field = static::get_field_name($error);

        $max = $keyword_args['max'];
        $count = $keyword_args['count'];

        if (null === $field) {
            return "Expect the max items to be {$max}, but actual count is {$count}.";
        }

        return "Expect the max items of field '{$field}' to be {$max}, but actual count is {$count}.";
    }

    /**
     * Returns message error for keyword "contains".
     *
     * @param ValidationError      $error
     * @param ValidationError|null $parent_error
     * @return string
     */
    private static function get_error_message_for_keyword_contains(
        ValidationError $error,
        ?ValidationError $parent_error = null
    ): string {
        self::ensure_same_keyword($error->keyword(), 'contains');

        if (0 === $error->subErrorsCount()) {
            // This is probably because of array validation.
            return "The json instance does not contain any items.";
        }

        if (1 === $error->subErrorsCount()) {
            [$sub_error] = $error->subErrors();
            return self::get_error_message_from_validation_error($sub_error, $error);
        }

        $messages = array_map(
            function (ValidationError $sub_error) use ($error): string {
                $message = result::get_error_message_from_validation_error($sub_error, $error);
                $message = rtrim($message, '.');

                return lcfirst($message);
            },
            $error->subErrors()
        );

        $delimiter = '. ';
        if (null !== $parent_error) {
            switch ($parent_error->keyword()) {
                case structure::ANY_OF:
                    $delimiter = '. Or ';
                    break;

                case structure::ALL_OF:
                    $delimiter = '. And ';
                    break;

                default:
                    break;
            }
        }

        $error_message = implode($delimiter, $messages);
        return ucfirst($error_message) . '.';
    }

    /**
     * Returns message error for keyword "anyOf".
     *
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_for_keyword_any_of(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), structure::ANY_OF);
        $sub_errors = $error->subErrors();

        if (empty($sub_errors)) {
            return "";
        }

        $messages = array_map(
            function (ValidationError $sub_error) use ($error): string {
                $message = result::get_error_message_from_validation_error($sub_error, $error);
                $message = rtrim($message, '.');

                // Lower case the text.
                return lcfirst($message);
            },
            $sub_errors
        );

        $error_message = implode('. Or ', $messages);
        return ucfirst($error_message) . '.';
    }

    /**
     * Returns message error for keyword "additionalProperties"
     *
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_for_keyword_additional_properties(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), structure::ADDITIONAL_PROPERTIES);
        $field = self::get_field_name($error);

        if (null === $field) {
            return "There are unexpected additional properties";
        }

        $field_type = is_numeric($field) ? 'index' : 'field';
        return "There are unexpected additional properties at {$field_type} '{$field}'";
    }

    /**
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_for_keyword_minimum(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), 'minimum');
        $keyword_args = $error->keywordArgs();

        $field = self::get_field_name($error);
        $minimum = $keyword_args['min'];
        $value = $error->data();

        if (null === $field) {
            return "Expect the value to exceed {$minimum}, actual value is {$value}.";
        }

        return "Expect the value of field '{$field}' to exceed {$minimum}, actual value is {$value}.";
    }

    /**
     * @param ValidationError $error
     * @return string
     */
    private static function get_error_message_for_keyword_maximum(ValidationError $error): string {
        self::ensure_same_keyword($error->keyword(), 'maximum');
        $keyword_args = $error->keywordArgs();

        $field = self::get_field_name($error);
        $maximum = $keyword_args['max'];
        $value = $error->data();

        if (null === $field) {
            return "Expect the value to not exceed {$maximum}, actual value is {$value}.";
        }

        return "Expect the value of field '{$field}' to not exceed {$maximum}, actual value is {$value}.";
    }

    /**
     * @param string $keyword
     * @param string $expected_keyword
     *
     * @return void
     */
    private static function ensure_same_keyword(string $keyword, string $expected_keyword): void {
        if ($keyword === $expected_keyword) {
            return;
        }

        throw new coding_exception(
            "Expect a keyword of '{$expected_keyword}', but receive keyword '{$keyword}'"
        );
    }

    /**
     * @return string
     */
    public function get_error_message(): string {
        if ($this->validation_result->isValid()) {
            return "";
        }

        $first_error = $this->validation_result->getFirstError();
        return self::get_error_message_from_validation_error($first_error);
    }
}