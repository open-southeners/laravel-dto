<?php

namespace OpenSoutheners\LaravelDataMapper\Exceptions;

use OpenSoutheners\LaravelDataMapper\MappingValue;
use RuntimeException;

final class NoMapperFoundException extends RuntimeException
{
    /**
     * Create a new exception instance for the given unresolved mapping value.
     */
    public static function forValue(MappingValue $value): self
    {
        $message = sprintf(
            'No mapper found to map [%s] into [%s]',
            get_debug_type($value->data),
            $value->objectClass ?? 'unknown'
        );

        if ($value->path) {
            $message .= sprintf(' at path [%s]', $value->path);
        }

        return new self($message);
    }
}
