<?php

namespace OpenSoutheners\LaravelDto\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class WithDefaultValue
{
    public function __construct(public mixed $value)
    {
        // 
    }
}