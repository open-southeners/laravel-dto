<?php

namespace Workbench\App\DataTransferObjects;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDataMapper\Attributes\AsType;
use OpenSoutheners\LaravelDataMapper\Attributes\Authenticated;
use OpenSoutheners\LaravelDataMapper\Attributes\ModelWith;
use OpenSoutheners\LaravelDataMapper\Attributes\Validate;
use OpenSoutheners\LaravelDataMapper\Contracts\RouteTransferableObject;
use stdClass;
use Workbench\App\Enums\PostStatus;
use Workbench\App\Http\Requests\PostUpdateFormRequest;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;

#[AsType('UpdatePostFormData')]
#[Validate(PostUpdateFormRequest::class)]
class UpdatePostWithRouteBindingData implements RouteTransferableObject
{
    /**
     * @param  Collection<Tag>|null  $tags
     */
    public function __construct(
        #[ModelWith(['tags'])]
        public Post $post,
        public ?string $title = null,
        public ?stdClass $content = null,
        public ?PostStatus $postStatus = null,
        public ?Collection $tags = null,
        public ?CarbonImmutable $publishedAt = null,
        #[Authenticated]
        public ?User $currentUser = null
    ) {
        //
    }
}
