<?php

namespace OpenSoutheners\LaravelDto\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use OpenSoutheners\LaravelDto\Tests\Fixtures\UpdatePostWithRouteBindingData;
use OpenSoutheners\LaravelDto\Tests\Fixtures\Post;
use OpenSoutheners\LaravelDto\Tests\Fixtures\Tag;

class ValidatedDataTransferObjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutExceptionHandling();

        Route::patch('post/{post}', function (UpdatePostWithRouteBindingData $data) {
            return response()->json($data->toArray());
        });
    }

    public function testValidatedDataTransferObjectGetsRouteBoundModel()
    {
        $post = Post::factory()->hasAttached(
            Tag::factory()->count(2)
        )->create();

        $response = $this->patchJson('post/1', []);

        $response->assertJson([
            'post' => $post->load('tags')->toArray(),
        ], true);
    }

    public function testValidatedDataTransferObjectGetsValidatedOnlyParameters()
    {
        Post::factory()->create();

        $firstTag = Tag::factory()->create();
        $secondTag = Tag::factory()->create();

        $response = $this->patchJson('post/1', [
            'tags' => '1,2',
            'post_status' => 'test_non_existing_status',
            'published_at' => '2023-09-06 17:35:53',
        ]);

        $response->assertJson([
            'tags' => [
                [
                    'id' => $firstTag->getKey(),
                    'name' => $firstTag->name,
                    'slug' => $firstTag->slug,
                ],
                [
                    'id' => $secondTag->getKey(),
                    'name' => $secondTag->name,
                    'slug' => $secondTag->slug,
                ],
            ],
            'published_at' => '2023-09-06T17:35:53.000000Z',
        ], true);
    }

    public function testDataTransferObjectWithModelSentDoesLoadRelationshipIfMissing()
    {
        $post = Post::factory()->hasAttached(
            Tag::factory()->count(2)
        )->create();

        $data = UpdatePostWithRouteBindingData::fromArray([
            'post' => $post,
        ]);

        DB::enableQueryLog();

        $this->assertNotEmpty($data->post->tags);
        $this->assertCount(2, $data->post->tags);
        $this->assertEmpty(DB::getQueryLog());
    }

    public function testDataTransferObjectWithModelSentDoesNotRunQueriesToFetchItAgain()
    {
        $post = Post::factory()->make();

        $post->setRelation('tags', []);

        DB::enableQueryLog();

        $data = UpdatePostWithRouteBindingData::fromArray([
            'post' => $post,
        ]);

        $this->assertEmpty(DB::getQueryLog());
    }
}
