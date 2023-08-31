<?php

namespace OpenSoutheners\LaravelDto\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use OpenSoutheners\LaravelDto\DataTransferObject;

class CreatePostData extends DataTransferObject
{
    public mixed $authorEmail = 'me@d8vjork.com';

    public function __construct(
        public string $title,
        public array|null $tags,
        public PostStatus $postStatus,
        public ?Post $post = null,
        public array|string|null $country = null,
        public $description = '',
        public ?Authenticatable $currentUser = null,
        $authorEmail = null
    ) {
        $this->authorEmail = $authorEmail;
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
