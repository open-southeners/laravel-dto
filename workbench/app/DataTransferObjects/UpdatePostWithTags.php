<?php

namespace Workbench\App\DataTransferObjects;

use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDto\DataTransferObject;
use Workbench\App\Models\Post;

class UpdatePostWithTags extends DataTransferObject
{
    public function __construct(
        public Post $post,
        public string $title,
        public Collection $tags
    ) {
    }
}
