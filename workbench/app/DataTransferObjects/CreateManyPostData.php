<?php

namespace Workbench\App\DataTransferObjects;

use Illuminate\Support\Collection;

class CreateManyPostData
{
    /**
     * @param  Collection<CreatePostData>  $posts
     */
    public function __construct(public Collection $posts)
    {
        //
    }
}
