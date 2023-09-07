<?php

namespace OpenSoutheners\LaravelDto\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDto\DataTransferObject;
use stdClass;

class CreatePostData extends DataTransferObject
{
    public mixed $authorEmail = 'me@d8vjork.com';

    /**
     * @param \Illuminate\Support\Collection<\Illuminate\Support\Carbon>|null $dates
     */
    public function __construct(
        public string $title,
        public array|null $tags,
        public PostStatus $postStatus,
        public ?Post $post = null,
        public array|string|null $country = null,
        public $description = '',
        public ?Collection $subscribers = null,
        public ?Authenticatable $currentUser = null,
        public ?Carbon $publishedAt = null,
        public ?stdClass $content = null,
        public ?Collection $dates = null,
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
