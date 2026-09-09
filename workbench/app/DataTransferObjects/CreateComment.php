<?php

namespace Workbench\App\DataTransferObjects;

class CreateComment
{
    public function __construct(
        public string $content,
        public array $tags
    ) {
        //
    }
}
