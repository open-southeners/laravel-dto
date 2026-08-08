<?php

namespace OpenSoutheners\LaravelDataMapper\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Workbench\App\DataObjects\CreateUserData;
use Workbench\App\DataTransferObjects\CreatePostData;

use function OpenSoutheners\LaravelDataMapper\map;

class ObjectMappingTest extends TestCase
{
    public function test_mapping_to_object_from_array()
    {
        $data = map([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ])->to(CreateUserData::class);

        $this->assertIsString($data->name);
        $this->assertIsString($data->email);

        $this->assertEquals('John Doe', $data->name);
        $this->assertEquals('john@example.com', $data->email);
    }

    public function test_mapping_to_object_from_json_string()
    {
        $data = map(json_encode([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]))->to(CreateUserData::class);

        $this->assertIsString($data->name);
        $this->assertIsString($data->email);

        $this->assertEquals('John Doe', $data->name);
        $this->assertEquals('john@example.com', $data->email);
    }

    public function test_untyped_array_property_populated_from_list_of_strings()
    {
        // Regression: a plain `array`/`?array` property with no docblock
        // generic (e.g. `?array $tags`) used to be classified with an
        // unresolved `mixed` collection value type, which then threw trying
        // to map each item into a `mixed` target.
        $data = map([
            'title' => 'a',
            'tags' => ['x'],
            'postStatus' => 'hidden',
        ])->to(CreatePostData::class);

        $this->assertIsArray($data->tags);
        $this->assertEquals(['x'], $data->tags);
    }

    public function test_untyped_array_property_with_assoc_array_items_stays_raw()
    {
        $data = map([
            'title' => 'a',
            'tags' => ['colour' => 'red', 'size' => 'large'],
            'postStatus' => 'hidden',
        ])->to(CreatePostData::class);

        $this->assertIsArray($data->tags);
        $this->assertEquals(['colour' => 'red', 'size' => 'large'], $data->tags);
    }

    public function test_docblock_generic_collection_property_still_maps_items()
    {
        $data = map([
            'title' => 'a',
            'tags' => ['x'],
            'postStatus' => 'hidden',
            'dates' => ['2024-01-01', '2024-02-01'],
        ])->to(CreatePostData::class);

        $this->assertInstanceOf(Collection::class, $data->dates);
        $this->assertCount(2, $data->dates);
        $this->assertInstanceOf(Carbon::class, $data->dates->first());
        $this->assertTrue($data->dates->first()->isSameDay('2024-01-01'));
    }

    public function test_collection_typed_property_without_generic_gets_a_collection_of_raw_items()
    {
        $data = map([
            'title' => 'a',
            'tags' => ['x'],
            'postStatus' => 'hidden',
            'subscribers' => ['a@example.com', 'b@example.com'],
        ])->to(CreatePostData::class);

        $this->assertInstanceOf(Collection::class, $data->subscribers);
        $this->assertEquals(['a@example.com', 'b@example.com'], $data->subscribers->values()->all());
    }
}
