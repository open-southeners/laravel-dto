<?php

namespace OpenSoutheners\LaravelDataMapper\Attributes;

use Attribute;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Inject implements ContextualAttribute
{
    public function __construct(public string $value)
    {
        //
    }

    /**
     * Resolve the currently authenticated user.
     *
     * @return Authenticatable|null
     */
    public static function resolve(self $attribute, Container $container)
    {
        return $container->make($attribute->value);
    }
}
