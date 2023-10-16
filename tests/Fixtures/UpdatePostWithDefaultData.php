<?php

namespace OpenSoutheners\LaravelDto\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use OpenSoutheners\LaravelDto\Attributes\BindModelUsing;
use OpenSoutheners\LaravelDto\Attributes\WithDefaultValue;
use OpenSoutheners\LaravelDto\DataTransferObject;

class UpdatePostWithDefaultData extends DataTransferObject
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        #[BindModelUsing('slug')]
        #[WithDefaultValue('hello-world')]
        public Post $post,
        #[WithDefaultValue(Authenticatable::class)]
        public User $author,
        public ?Post $parent = null,
        public array|string|null $country = null,
        public array $tags = [],
        public string $description = ''
    ) {
        //
    }
}
