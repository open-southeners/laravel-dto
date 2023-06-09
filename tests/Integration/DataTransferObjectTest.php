<?php

namespace OpenSoutheners\LaravelDto\Tests\Integration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Mockery;
use OpenSoutheners\LaravelDto\Tests\Fixtures\CreatePostData;
use OpenSoutheners\LaravelDto\Tests\Fixtures\Post;
use OpenSoutheners\LaravelDto\Tests\Fixtures\PostStatus;
use OpenSoutheners\LaravelDto\Tests\Fixtures\UpdatePostData;
use Orchestra\Testbench\TestCase;

class DataTransferObjectTest extends TestCase
{
    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database');
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function testDataTransferObjectFromRequest()
    {
        /** @var CreatePostFormRequest */
        $mock = Mockery::mock(app(CreatePostFormRequest::class))->makePartial();

        $mock->shouldReceive('route')->andReturn('example');
        $mock->shouldReceive('validated')->andReturn([
            'title' => 'Hello world',
            'tags' => 'foo,bar,test',
            'post_status' => PostStatus::Published->value,
        ]);

        // Not absolutely the same but does the job...
        app()->bind(Request::class, fn () => $mock);

        $data = CreatePostData::fromRequest($mock);

        $this->assertTrue($data->postStatus instanceof PostStatus);
        $this->assertEquals('Hello world', $data->title);
        $this->assertIsArray($data->tags);
        $this->assertContains('bar', $data->tags);
    }

    public function testDataTransferObjectFromArrayWithModels()
    {
        $post = Post::create([
            'id' => 1,
            'title' => 'Lorem ipsum',
            'status' => PostStatus::Hidden->value,
        ]);

        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => 'foo,bar,test',
            'post_status' => PostStatus::Published->value,
            'post_id' => 1,
        ]);

        $this->assertTrue($data->postStatus instanceof PostStatus);
        $this->assertEquals('Hello world', $data->title);
        $this->assertIsArray($data->tags);
        $this->assertContains('bar', $data->tags);
        $this->assertTrue($data->post->is($post));
    }

    public function testDataTransferObjectFilledViaRequest()
    {
        /** @var CreatePostFormRequest */
        $mock = Mockery::mock(app(Request::class))->makePartial();

        $mock->shouldReceive('route')->andReturn('example');
        $mock->shouldReceive('has')->withArgs(['post_status'])->andReturn(true);
        $mock->shouldReceive('has')->withArgs(['postStatus'])->andReturn(true);
        $mock->shouldReceive('has')->withArgs(['post'])->andReturn(false);

        app()->bind(Request::class, fn () => $mock);

        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => '',
            'post_status' => PostStatus::Published->value,
        ]);

        $this->assertFalse($data->filled('tags'));
        $this->assertTrue($data->filled('post_status'));
        $this->assertFalse($data->filled('post'));
    }

    public function testDataTransferObjectWithoutPropertyKeysNormalisationWhenDisabledFromConfig()
    {
        config(['data-transfer-objects.normalise_properties' => false]);

        $post = Post::create([
            'id' => 2,
            'title' => 'Hello ipsum',
            'status' => PostStatus::Hidden->value,
        ]);

        $parentPost = Post::create([
            'id' => 1,
            'title' => 'Lorem ipsum',
            'status' => PostStatus::Hidden->value,
        ]);

        $data = UpdatePostData::fromArray([
            'post_id' => 2,
            'parent' => 1,
            'tags' => 'test,hello',
        ]);

        $this->assertTrue($data->post_id?->is($post));
        $this->assertTrue($data->parent?->is($parentPost));
    }
}

class CreatePostFormRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => ['string'],
            'tags' => ['string'],
            'post_status' => [Rule::enum(PostStatus::class)],
        ];
    }
}
