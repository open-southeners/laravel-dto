<?php

namespace Workbench\App\DataTransferObjects;

use OpenSoutheners\LaravelDataMapper\Concerns\SerializesMapping;

class SerializablePostWrapperData
{
    use SerializesMapping;

    public function __construct(
        public string $label,
        public SerializablePostData $summary,
    ) {
        //
    }
}
