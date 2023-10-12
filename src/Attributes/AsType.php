<?php

namespace OpenSoutheners\LaravelDto\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class AsType
{
    public function __construct(public string $typeName)
    {
        // 
    }
}