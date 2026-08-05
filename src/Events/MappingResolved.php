<?php

namespace OpenSoutheners\LaravelDataMapper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use OpenSoutheners\LaravelDataMapper\Mappers\DataMapper;
use OpenSoutheners\LaravelDataMapper\MappingValue;

final class MappingResolved
{
    use Dispatchable;

    /**
     * @param  class-string<DataMapper>  $mapperClass
     */
    public function __construct(
        public readonly string $mapperClass,
        public readonly MappingValue $value,
    ) {
        //
    }
}
