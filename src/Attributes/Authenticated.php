<?php

namespace OpenSoutheners\LaravelDataMapper\Attributes;

use Attribute;
use Illuminate\Container\Attributes\Authenticated as BaseAttribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Authenticated extends BaseAttribute
{
    //
}
