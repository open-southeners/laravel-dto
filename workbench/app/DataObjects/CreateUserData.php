<?php

namespace Workbench\App\DataObjects;

readonly class CreateUserData
{
    public function __construct(public string $name, public string $email)
    {
        //
    }
}
