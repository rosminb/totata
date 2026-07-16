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
 * @package core
 */

namespace core\webapi;

use core\date_format;
use core\format;
use core\webapi\formatter\field\date_field_formatter;
use core\webapi\formatter\field\text_field_formatter;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * GraphQL execution context.
 */
class execution_context {

    /** @var string */
    private $type;

    /** @var string|null */
    private $operationname;

    /** @var ResolveInfo|null */
    private $resolveinfo;

    /** @var \context */
    private $relevantcontext;

    /**
     * @var array
     */
    private $deprecation_warnings = [];

    /**
     * Constructor.
     *
     * @param string $type type of end point, see TYPE_* constants in \totara_webapi\graphql class
     * @param string|null $operationname the name of query or mutation, can be null, i.e. in batched queries
     */
    protected function __construct(string $type, ?string $operationname) {
        $this->type = $type;
        $this->operationname = $operationname;
    }

    /**
     * Factory method for creation of execution context.
     *
     * @param string $type  type of end point 'ajax', 'external', 'mobile', 'dev', etc.
     * @param string|null $operationname the name of query or mutation
     * @return execution_context
     */
    final public static function create(string $type, ?string $operationname = null): self {
        // NOTE: the main purpose of this method is to allow us to introduce subclasses for different types
        //       without breaking BC.
        return new self($type, $operationname);
    }

    /**
     * @internal
     * @param ResolveInfo|null $info
     */
    final public function set_resolve_info(?ResolveInfo $info): void {
        $this->resolveinfo = $info;
    }

    /**
     * Returns advanced information for the current resolve step.
     *
     * @return ResolveInfo|null
     */
    final public function get_resolve_info(): ?ResolveInfo {
        return $this->resolveinfo;
    }

    /**
     * Returns the type of Web API entry point.
     *
     * @return string|null
     */
    final public function get_type(): ?string {
        return $this->type;
    }

    /**
     * @param string|null $operationname
     */
    public function set_operationname(?string $operationname): void {
        $this->operationname = $operationname;
    }

    /**
     * Returns persisted query/mutation name.
     *
     * @return string|null
     */
    final public function get_operationname(): ?string {
        return $this->operationname;
    }

    /**
     * Sets the context most relevant to this execution.
     * @param \context $context
     */
    final public function set_relevant_context(\context $context): void {
        if (isset($this->relevantcontext)) {
            throw new \coding_exception('Context can only be set once per execution');
        }
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            // We don't want developers just setting the system context here, that would be bad form.
            // It doesn't provide us with anything.
            // In a multitenant world if a query or mutation has a relevant context then it will always be child of system.
            throw new \coding_exception('Do not use the system context, provide an specific context or do not set a context.');
        }
        $this->relevantcontext = $context;
    }

    /**
     * Returns the context.
     */
    final public function get_relevant_context(): \context {
        if (!isset($this->relevantcontext)) {
            throw new \coding_exception('Context has not been provided for this execution');
        }
        return $this->relevantcontext;
    }

    /**
     * Returns true if the execution has provided a relevant context.
     */
    final public function has_relevant_context(): bool {
        return isset($this->relevantcontext);
    }

    /**
     * Adds deprecation warning for later processing
     *
     * @param string $type_name the graphql type name
     * @param string $field_name the graphql field name
     * @param string $message the deprecation message
     */
    public function add_deprecation_warning(string $type_name, string $field_name, string $message): void {
        $this->deprecation_warnings[$type_name][$field_name] = $message;
    }

    /**
     * Get all deprecation warning triggered in request
     *
     * @return array
     */
    public function get_deprecation_warnings(): array {
        return $this->deprecation_warnings;
    }

    // === Utility functions for resolvers ===

    /**
     * Format timestamp for core_date scalar fields using current user timezone.
     *
     * @deprecated
     * @param int|null $timestamp unix timestamp
     * @param array $args field arguments
     * @return string|null
     */
    public function format_core_date(?int $timestamp, array $args) {
        debugging('format_core_date() in execution_context is deprecated, please use the new \core\webapi\formatter\field\date_field_formatter class', DEBUG_DEVELOPER);

        $format = $args['format'] ?? date_format::FORMAT_TIMESTAMP;

        $formatter = new date_field_formatter($format, \context_system::instance());
        return $formatter->format($timestamp);
    }

    /**
     * Format text to HTML and link files to pluginfile.php script if all options specified.
     *
     * @deprecated
     * @param string $text
     * @param string $format
     * @param array $options - includes 'context', 'component' and 'filearea' for pluginfile.php relinking
     * @return string
     */
    public function format_text(?string $text, $format = FORMAT_HTML, array $options = []) {
        debugging('format_text() in execution_context is deprecated, please use the new \core\webapi\formatter\field\text_field_formatter class', DEBUG_DEVELOPER);

        $context = $options['context'] ?? \context_system::instance();

        $formatter = new text_field_formatter(format::FORMAT_HTML, $context);
        $formatter->set_text_format($format)
            ->set_additional_options($options);

        if (!empty($options['context']) && !empty($options['component']) && !empty($options['filearea'])) {
            $itemid = $options['itemid'] ?? null;
            $formatter->set_pluginfile_url_options($options['context'], $options['component'], $options['filearea'], $itemid);
        } else {
            $formatter->disabled_pluginfile_url_rewrite();
        }

        return $formatter->format($text);
    }

}