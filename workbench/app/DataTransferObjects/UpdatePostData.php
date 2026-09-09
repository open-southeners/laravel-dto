<?php

namespace Workbench\App\DataTransferObjects;

use Workbench\App\Models\Post;

class UpdatePostData
{
    /**
     * @param  string[]  $tags
     */
    public function __construct(
        public ?Post $post,
        public ?Post $parent = null,
        public array|string|null $country = null,
        public array $tags = [],
        public string $description = '',
    ) {
        //
    }
}
