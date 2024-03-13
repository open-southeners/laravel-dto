<?php

namespace Workbench\App\DataTransferObjects;

use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDto\DataTransferObject;

class CreateManyPostData extends DataTransferObject
{
    /**
     * @param  \Illuminate\Support\Collection<\Workbench\App\DataTransferObjects\CreatePostData>  $posts
     */
    public function __construct(public Collection $posts)
    {
        //
    }
}
