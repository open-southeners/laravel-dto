<?php

namespace OpenSoutheners\LaravelDataMapper\Contracts;

/**
 * This solely act as a tag for the class to be resolved by the Laravel DI (Container).
 *
 * So in case is used directly in a controller method this will map the request
 * with route data onto the object class constructor properties.
 */
interface RouteTransferableObject
{
    //
}
