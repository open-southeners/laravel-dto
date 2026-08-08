<?php

namespace OpenSoutheners\LaravelDataMapper\Tests;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use OpenSoutheners\LaravelDataMapper\Events\MappingResolved;
use OpenSoutheners\LaravelDataMapper\Exceptions\NoMapperFoundException;
use Workbench\App\DataTransferObjects\PostSummaryData;
use Workbench\App\DataTransferObjects\UpdatePostData;
use Workbench\App\Enums\PostStatus;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Factories\PostFactory;
use Workbench\Database\Factories\UserFactory;

use function OpenSoutheners\LaravelDataMapper\map;

class InstancePassthroughTest extends TestCase
{
    public function test_mapping_model_instance_to_same_model_class_returns_same_instance_without_querying()
    {
        DB::connection()->enableQueryLog();

        $user = UserFactory::new()->create();

        DB::connection()->flushQueryLog();

        $result = map($user)->to(User::class);

        $this->assertSame($user, $result);
        $this->assertEmpty(DB::connection()->getQueryLog());
    }

    public function test_mapping_enum_instance_to_same_enum_class_returns_same_instance()
    {
        $status = PostStatus::Hidden;

        $result = map($status)->to(PostStatus::class);

        $this->assertSame($status, $result);
    }

    public function test_mapping_carbon_instance_to_carbon_class_returns_same_instance()
    {
        $publishedAt = Carbon::now();

        $result = map($publishedAt)->to(Carbon::class);

        $this->assertSame($publishedAt, $result);
    }

    public function test_hydrated_dto_round_trip_with_enum_carbon_model_and_collection_properties_returns_same_instance()
    {
        $post = PostFactory::new()->create();
        $reviewers = UserFactory::new()->count(2)->create();

        // Build the DTO the normal way first, so its properties end up holding
        // real instances (enum, Carbon, Model, Collection<Model>) rather than
        // raw scalars.
        $dto = map([
            'status' => 'hidden',
            'publishedAt' => Carbon::now()->timestamp,
            'post' => $post->id,
            'reviewers' => $reviewers->pluck('id')->all(),
        ])->to(PostSummaryData::class);

        $this->assertInstanceOf(PostStatus::class, $dto->status);
        $this->assertInstanceOf(Carbon::class, $dto->publishedAt);
        $this->assertInstanceOf(Post::class, $dto->post);
        $this->assertInstanceOf(Collection::class, $dto->reviewers);

        // Re-mapping the already-hydrated DTO must not destructure its enum,
        // Carbon, Model or Collection properties: the whole DTO short-circuits
        // straight back out as the same instance.
        $result = map($dto)->to(PostSummaryData::class);

        $this->assertSame($dto, $result);
    }

    public function test_nested_property_value_already_model_instance_hydrates_without_querying()
    {
        DB::connection()->enableQueryLog();

        $post = PostFactory::new()->create();

        DB::connection()->flushQueryLog();

        $result = map(['post' => $post])->to(UpdatePostData::class);

        $this->assertSame($post, $result->post);
        $this->assertEmpty(DB::connection()->getQueryLog());
    }

    public function test_mapping_model_instance_to_different_model_class_still_throws()
    {
        $user = UserFactory::new()->create();

        $this->expectException(NoMapperFoundException::class);

        map($user)->to(Post::class);
    }

    public function test_array_through_collection_to_carbon_target_unaffected_by_passthrough()
    {
        $timestamps = [1747939147, 1757939147];

        $result = map($timestamps)->through(Collection::class)->to(Carbon::class);

        $this->assertSame(Collection::class, get_class($result));
        $this->assertEquals($timestamps[0], $result->first()->timestamp);
        $this->assertEquals($timestamps[1], $result->last()->timestamp);
    }

    public function test_mapping_collection_instance_to_collection_class_keeps_existing_behaviour()
    {
        $collection = Collection::make(['a', 'b']);

        $result = map($collection)->to(Collection::class);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertNotSame($collection, $result);
        $this->assertEquals($collection->all(), $result->all());
    }

    public function test_no_mapping_resolved_event_dispatched_on_passthrough()
    {
        Event::fake([MappingResolved::class]);

        $user = UserFactory::new()->create();

        map($user)->to(User::class);

        Event::assertNotDispatched(MappingResolved::class);
    }
}
