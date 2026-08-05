<?php

namespace OpenSoutheners\LaravelDataMapper;

function map(mixed $input): Mapper
{
    return new Mapper($input);
}
