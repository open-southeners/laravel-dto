<?php

namespace OpenSoutheners\LaravelDataMapper;

use ReflectionProperty;

final class MappingValue
{
    /**
     * @param  class-string|string|null  $objectClass
     * @param  class-string|string|null  $collectClass
     * @param  array<string>  $providedKeys
     */
    public function __construct(
        public readonly mixed $data,
        public readonly ?string $objectClass = null,
        public readonly ?string $collectClass = null,
        public readonly ?ReflectionProperty $property = null,
        public readonly ?string $path = null,
        public readonly array $providedKeys = [],
    ) {
        //
    }
}
