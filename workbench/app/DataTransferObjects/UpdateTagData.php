<?php

namespace Workbench\App\DataTransferObjects;

use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDataMapper\Attributes\Authenticated;
use OpenSoutheners\LaravelDataMapper\Attributes\ResolveModel;
use OpenSoutheners\LaravelDataMapper\Attributes\Validate;
use OpenSoutheners\LaravelDataMapper\Contracts\RouteTransferableObject;
use Workbench\App\Http\Requests\TagUpdateFormRequest;
use Workbench\App\Models\Film;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;

#[Validate(TagUpdateFormRequest::class)]
class UpdateTagData implements RouteTransferableObject
{
    /**
     * @param  Collection<Post|Film>  $taggable
     */
    public function __construct(
        public Tag $tag,
        #[ResolveModel([Post::class => 'slug', Film::class])]
        public Collection $taggable,
        public array $taggableType,
        public string $name,
        #[Authenticated]
        public User $authUser
    ) {
        //
    }
}
