<?php

namespace OpenSoutheners\LaravelDataMapper\Tests;

use Illuminate\Support\Collection;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

use function OpenSoutheners\LaravelDataMapper\map;

class ReproPostDemo
{
    /** @param Collection<int, User> $reviewers */
    public function __construct(
        public string $title,
        public ?Collection $reviewers = null,
    ) {}
}

class ReproPlainCollectionDemo
{
    public function __construct(
        public string $title,
        public ?Collection $tags = null,
    ) {}
}

class NestedCollectionReproTest extends TestCase
{
    public function test_docblock_generic_model_collection_property_maps_ids_to_models()
    {
        UserFactory::new()->count(2)->create();

        $result = map(['title' => 'x', 'reviewers' => [1, 2]])->to(ReproPostDemo::class);

        $this->assertInstanceOf(Collection::class, $result->reviewers);
        $this->assertInstanceOf(User::class, $result->reviewers->first());
    }

    public function test_plain_collection_property_without_generics_still_gets_a_collection()
    {
        // Regression: through-class inference ('array' default) must not leak
        // into an explicitly Collection-typed target and unwrap it to an array.
        $result = map(['title' => 'x', 'tags' => ['a', 'b']])->to(ReproPlainCollectionDemo::class);

        $this->assertInstanceOf(Collection::class, $result->tags);
        $this->assertEquals(['a', 'b'], $result->tags->values()->all());
    }

    public function test_mapping_array_directly_to_collection_target_returns_collection()
    {
        $result = map(['a', 'b'])->to(Collection::class);

        $this->assertInstanceOf(Collection::class, $result);
    }
}
