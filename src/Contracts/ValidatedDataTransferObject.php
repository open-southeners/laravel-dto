<?php

namespace OpenSoutheners\LaravelDto\Contracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

interface ValidatedDataTransferObject
{
    /**
     * Get form request that this data transfer object is based from.
     */
    public static function request(): string;

    /**
     * Initialise data transfer object from a request.
     */
    public static function fromRequest(Request|FormRequest $request): static;
    
    /**
     * Initialise data transfer object from array.
     */
    public static function fromArray(...$args): static;
}
