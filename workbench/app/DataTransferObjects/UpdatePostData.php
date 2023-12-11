<?php

namespace Workbench\App\DataTransferObjects;

use OpenSoutheners\LaravelDto\DataTransferObject;
use Workbench\App\Models\Post;

class UpdatePostData extends DataTransferObject
{
    /**
     * @param  string[]  $tags
     */
    public function __construct(
        public ?Post $post_id,
        public ?Post $parent = null,
        public array|string|null $country = null,
        public array $tags = [],
        public string $description = '',
    ) {
        //
    }
}
