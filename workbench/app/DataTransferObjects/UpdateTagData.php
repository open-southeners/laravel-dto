<?php

namespace Workbench\App\DataTransferObjects;

use Illuminate\Contracts\Auth\Authenticatable;
use OpenSoutheners\LaravelDto\Attributes\BindModel;
use OpenSoutheners\LaravelDto\Attributes\WithDefaultValue;
use OpenSoutheners\LaravelDto\Contracts\ValidatedDataTransferObject;
use OpenSoutheners\LaravelDto\DataTransferObject;
use Workbench\App\Http\Requests\TagUpdateFormRequest;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;

class UpdateTagData extends DataTransferObject implements ValidatedDataTransferObject
{
    public function __construct(
        #[BindModel]
        public Tag $tag,
        #[BindModel]
        public ?Post $post,
        public string $name,
        #[WithDefaultValue(Authenticatable::class)]
        public User $authUser
    ) {
        //
    }

    /**
     * Get form request that this data transfer object is based from.
     */
    public static function request(): string
    {
        return TagUpdateFormRequest::class;
    }
}
