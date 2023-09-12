<?php

namespace OpenSoutheners\LaravelDto\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDto\Contracts\ValidatedDataTransferObject;
use OpenSoutheners\LaravelDto\DataTransferObject;
use stdClass;

class UpdatePostWithRouteBindingData extends DataTransferObject implements ValidatedDataTransferObject
{
    /**
     * @param \Illuminate\Support\Collection<\OpenSoutheners\LaravelDto\Tests\Fixtures\Tag>|null $tags
     */
    public function __construct(
        public Post $post,
        public ?string $title = null,
        public ?stdClass $content = null,
        public ?PostStatus $postStatus = null,
        public ?Collection $tags = null,
        public ?Carbon $publishedAt = null,
        public ?Authenticatable $currentUser = null
    ) {
        // 
    }

    public static function request(): string
    {
        return PostUpdateFormRequest::class;
    }
}