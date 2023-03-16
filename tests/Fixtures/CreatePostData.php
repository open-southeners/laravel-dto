<?php

namespace OpenSoutheners\LaravelDto\Tests\Fixtures;

use OpenSoutheners\LaravelDto\DataTransferObject;

class CreatePostData extends DataTransferObject
{
    public function __construct(
        public string $title,
        public array $tags,
        public PostStatus $status,
        public Post|null $post = null
    ) {
        
    }
}
