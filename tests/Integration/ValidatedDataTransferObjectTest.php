<?php

namespace OpenSoutheners\LaravelDto\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ], true);
    }
}
