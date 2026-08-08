<?php

namespace Workbench\App\DataTransferObjects;

class CreateReviewCommentData
{
    public function __construct(
        public string $author,
        public string $comment,
    ) {
        //
    }
}
