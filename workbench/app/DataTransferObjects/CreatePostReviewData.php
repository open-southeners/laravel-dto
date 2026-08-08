<?php

namespace Workbench\App\DataTransferObjects;

use Illuminate\Support\Collection;

class CreatePostReviewData
{
    /**
     * @param  Collection<int, CreateReviewCommentData>  $comments
     */
    public function __construct(
        public string $title,
        public Collection $comments,
    ) {
        //
    }
}
