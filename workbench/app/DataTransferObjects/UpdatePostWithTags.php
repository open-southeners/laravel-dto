<?php

namespace Workbench\App\DataTransferObjects;

use Illuminate\Support\Collection;
use Workbench\App\Models\Post;

class UpdatePostWithTags
{
    public function __construct(
        public Post $post,
        public string $title,
        public Collection $tags
    ) {}
}
