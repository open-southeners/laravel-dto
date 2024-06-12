<?php

namespace Workbench\App\DataTransferObjects;

use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDto\DataTransferObject;

class CreateComment extends DataTransferObject
{
    public function __construct(
        public string $content,
        public array $tags
    ) {
        //
    }
}
