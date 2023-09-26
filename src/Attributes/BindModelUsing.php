<?php

namespace OpenSoutheners\LaravelDto\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BindModelUsing
{
    public function __construct(public string $attribute)
    {
        //
    }
}
