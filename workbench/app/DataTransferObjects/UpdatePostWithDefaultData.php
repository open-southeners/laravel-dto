<?php

namespace Workbench\App\DataTransferObjects;

use Illuminate\Contracts\Auth\Authenticatable;
use OpenSoutheners\LaravelDto\Attributes\BindModel;
use OpenSoutheners\LaravelDto\Attributes\WithDefaultValue;
use OpenSoutheners\LaravelDto\DataTransferObject;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;

class UpdatePostWithDefaultData extends DataTransferObject
{
    /**
     * @param  string[]  $tags
     */
    public function __construct(
        #[BindModel('slug')]
        #[WithDefaultValue('hello-world')]
        public Post $post,
        #[WithDefaultValue(Authenticatable::class)]
        public User $author,
        public Post|Tag|null $parent = null,
        public array|string|null $country = null,
        public array $tags = [],
        public string $description = ''
    ) {
        //
    }
}
