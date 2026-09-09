<?php

namespace Workbench\App\DataTransferObjects;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDataMapper\Concerns\SerializesMapping;
use Workbench\App\Enums\PostStatus;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class SerializablePostData
{
    use SerializesMapping;

    /**
     * @param  Collection<int, User>  $reviewers
     */
    public function __construct(
        public string $title,
        public PostStatus $status,
        public Carbon $publishedAt,
        public ?Post $post,
        public Collection $reviewers,
        public ?string $note = null,
    ) {
        //
    }
}
