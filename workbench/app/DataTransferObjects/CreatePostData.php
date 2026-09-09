<?php

namespace Workbench\App\DataTransferObjects;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDataMapper\Attributes\Authenticated;
use OpenSoutheners\LaravelDataMapper\Contracts\RouteTransferableObject;
use stdClass;
use Workbench\App\Enums\PostStatus;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class CreatePostData implements RouteTransferableObject
{
    public mixed $authorEmail = 'me@d8vjork.com';

    /**
     * @param  Collection<Carbon>|null  $dates
     */
    public function __construct(
        public string $title,
        public ?array $tags,
        public PostStatus $postStatus,
        public ?Post $post = null,
        public array|string|null $country = null,
        public $description = '',
        public ?Collection $subscribers = null,
        #[Authenticated]
        public ?User $currentUser = null,
        public ?Carbon $publishedAt = null,
        public ?stdClass $content = null,
        public ?Collection $dates = null,
        $authorEmail = null
    ) {
        if (count($this->tags) === 0) {
            $this->tags = ['generic', 'post'];
        }

        $this->authorEmail = $authorEmail;
    }
}
