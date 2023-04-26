<?php

namespace OpenSoutheners\LaravelDto\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BindModelWith
{
    public function __construct(public array|string $relationships)
    {
        // 
    }
}
