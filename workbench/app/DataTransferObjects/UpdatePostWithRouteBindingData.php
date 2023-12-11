<?php

namespace Workbench\App\DataTransferObjects;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDto\Attributes\AsType;
use OpenSoutheners\LaravelDto\Attributes\BindModel;
use OpenSoutheners\LaravelDto\Contracts\ValidatedDataTransferObject;
use OpenSoutheners\LaravelDto\DataTransferObject;
use stdClass;
use Workbench\App\Enums\PostStatus;
use Workbench\App\Http\Requests\PostUpdateFormRequest;
use Workbench\App\Models\Post;

#[AsType('UpdatePostFormData')]
class UpdatePostWithRouteBindingData extends DataTransferObject implements ValidatedDataTransferObject
{
    /**
     * @param  \Illuminate\Support\Collection<\Workbench\App\Models\Tag>|null  $tags
     */
    public function __construct(
        #[BindModel(with: 'tags')]
        public Post $post,
        public ?string $title = null,
        public ?stdClass $content = null,
        public ?PostStatus $postStatus = null,
        public ?Collection $tags = null,
        public ?CarbonImmutable $publishedAt = null,
        public ?Authenticatable $currentUser = null
    ) {
        //
    }

    public static function request(): string
    {
        return PostUpdateFormRequest::class;
    }
}
