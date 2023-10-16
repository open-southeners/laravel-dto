<?php

namespace OpenSoutheners\LaravelDto\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\OpenSoutheners\LaravelDto\Tests\Fixtures\Post>
 */
class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $title = $this->faker->title();

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => PostStatus::Published->value,
        ];
    }
}
