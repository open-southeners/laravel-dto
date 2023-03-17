<?php

namespace OpenSoutheners\LaravelDto\Tests\Fixtures;

use OpenSoutheners\LaravelDto\DataTransferObject;

class CreatePostData extends DataTransferObject
{
    public function __construct(
        public string $title,
        public array|null $tags,
        public PostStatus $postStatus,
        public Post|null $post = null,
        public array|string|null $country = null
    ) {
        // 
    }

    /**
     * Add default data to data transfer object.
     */
    public function withDefaults(): void
    {
        if (empty($this->tags)) {
            $this->tags = ['generic', 'post'];
        }
    }
}
