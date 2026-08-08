<?php

namespace OpenSoutheners\LaravelDataMapper\Tests;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Workbench\App\DataTransferObjects\CreatePostReviewData;
use Workbench\App\DataTransferObjects\CreateReviewCommentData;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

use function OpenSoutheners\LaravelDataMapper\map;

class CollectionOfObjectsTest extends TestCase
{
    public function test_list_of_assoc_arrays_through_collection_maps_to_collection_of_plain_dtos()
    {
        $result = map([['name' => 'a'], ['name' => 'b']])
            ->through(Collection::class)
            ->to(CollectionOfObjectsFixtureDto::class);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(CollectionOfObjectsFixtureDto::class, $result->first());
        $this->assertSame('a', $result->first()->name);
        $this->assertSame('b', $result->last()->name);
    }

    public function test_list_of_assoc_arrays_through_array_maps_to_array_of_plain_dtos()
    {
        $result = map([['name' => 'a'], ['name' => 'b']])
            ->through('array')
            ->to(CollectionOfObjectsFixtureDto::class);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(CollectionOfObjectsFixtureDto::class, $result[0]);
        $this->assertSame('a', $result[0]->name);
        $this->assertSame('b', $result[1]->name);
    }

    public function test_json_array_of_objects_string_maps_to_collection_of_plain_dtos()
    {
        $json = json_encode([['name' => 'a'], ['name' => 'b']]);

        $result = map($json)->through(Collection::class)->to(CollectionOfObjectsFixtureDto::class);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame('a', $result->first()->name);
        $this->assertSame('b', $result->last()->name);
    }

    public function test_items_already_instances_of_target_pass_through_unchanged_mixed_with_raw_arrays()
    {
        $existing = new CollectionOfObjectsFixtureDto('existing');

        $result = map([$existing, ['name' => 'b']])
            ->through(Collection::class)
            ->to(CollectionOfObjectsFixtureDto::class);

        $this->assertSame($existing, $result->first());
        $this->assertInstanceOf(CollectionOfObjectsFixtureDto::class, $result->last());
        $this->assertSame('b', $result->last()->name);
    }

    public function test_empty_list_maps_to_empty_collection()
    {
        $result = map([])->through(Collection::class)->to(CollectionOfObjectsFixtureDto::class);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_empty_list_through_array_maps_to_empty_array()
    {
        $result = map([])->through('array')->to(CollectionOfObjectsFixtureDto::class);

        $this->assertSame([], $result);
    }

    public function test_null_items_are_dropped_rather_than_mapped()
    {
        // Documented behaviour: a `null` "hole" in the list has no sensible
        // target instance, so it is filtered out rather than surfaced as an
        // error (pre-existing `->filter()` behaviour, unaffected by mapping
        // items individually).
        $result = map([['name' => 'a'], null, ['name' => 'b']])
            ->through(Collection::class)
            ->to(CollectionOfObjectsFixtureDto::class);

        $this->assertCount(2, $result);
        $this->assertSame(['a', 'b'], $result->values()->map->name->all());
    }

    public function test_nested_docblock_generic_dto_collection_property_maps_each_item_on_a_workbench_dto()
    {
        $result = map([
            'title' => 'Great post',
            'comments' => [
                ['author' => 'Alice', 'comment' => 'Nice read'],
                ['author' => 'Bob', 'comment' => 'Agreed'],
            ],
        ])->to(CreatePostReviewData::class);

        $this->assertInstanceOf(Collection::class, $result->comments);
        $this->assertCount(2, $result->comments);
        $this->assertInstanceOf(CreateReviewCommentData::class, $result->comments->first());
        $this->assertSame('Alice', $result->comments->first()->author);
        $this->assertSame('Nice read', $result->comments->first()->comment);
        $this->assertSame('Bob', $result->comments->last()->author);
    }

    public function test_collection_of_already_hydrated_models_maps_each_item_without_querying()
    {
        $reviewers = UserFactory::new()->count(2)->create();

        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();

        $result = map(['title' => 'x', 'reviewers' => $reviewers])->to(ReviewersFixtureDto::class);

        $this->assertInstanceOf(Collection::class, $result->reviewers);
        $this->assertSame($reviewers->all(), $result->reviewers->all());
        $this->assertEmpty(DB::connection()->getQueryLog());
    }

    public function test_ids_array_through_collection_still_issues_a_single_query_for_model_targets()
    {
        $users = UserFactory::new()->count(2)->create();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $result = map($users->pluck('id')->all())->through(Collection::class)->to(User::class);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertSame(1, $queries);
    }
}

final class CollectionOfObjectsFixtureDto
{
    public function __construct(public string $name)
    {
        //
    }
}

final class ReviewersFixtureDto
{
    /**
     * @param  Collection<int, User>  $reviewers
     */
    public function __construct(
        public string $title,
        public Collection $reviewers,
    ) {
        //
    }
}
