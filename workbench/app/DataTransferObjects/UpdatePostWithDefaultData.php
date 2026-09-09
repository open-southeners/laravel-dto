<?php

namespace Workbench\App\DataTransferObjects;

use OpenSoutheners\LaravelDataMapper\Attributes\Authenticated;
use OpenSoutheners\LaravelDataMapper\Attributes\ResolveModel;
use OpenSoutheners\LaravelDataMapper\Contracts\RouteTransferableObject;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;

class UpdatePostWithDefaultData implements RouteTransferableObject
{
    /**
     * @param  string[]  $tags
     */
    public function __construct(
        #[ResolveModel('slug')]
        public Post $post,
        #[Authenticated]
        public User $author,
        public Post|Tag|null $parent = null,
        public array|string|null $country = null,
        public array $tags = [],
        public string $description = ''
    ) {
        //
    }
}
