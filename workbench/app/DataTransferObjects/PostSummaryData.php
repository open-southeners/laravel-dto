<?php

namespace Workbench\App\DataTransferObjects;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Workbench\App\Enums\PostStatus;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class PostSummaryData
{
    /**
     * @param  Collection<int, User>  $reviewers
     */
    public function __construct(
        public PostStatus $status,
        public Carbon $publishedAt,
        public Post $post,
        public Collection $reviewers,
    ) {
        //
    }
}
