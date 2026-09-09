<?php

namespace OpenSoutheners\LaravelDataMapper\Exceptions;

use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

final class UnresolvableContextualAttributeException extends RuntimeException
{
    /**
     * Create a new exception instance for a property carrying a contextual
     * attribute that couldn't be matched to a constructor parameter.
     *
     * @param  ReflectionClass<object>  $class
     */
    public static function forProperty(ReflectionClass $class, ReflectionProperty $property): self
    {
        return new self(sprintf(
            'Cannot resolve contextual attribute on [%s::$%s]: property is not constructor-promoted and no constructor parameter named [%s] exists.',
            $class->getName(),
            $property->getName(),
            $property->getName()
        ));
    }
}
