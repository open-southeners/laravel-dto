<?php

namespace OpenSoutheners\LaravelDataMapper\Tests;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as DatabaseCollection;
use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDataMapper\Support\TypeScript;
use stdClass;
use Workbench\App\DataTransferObjects\UpdatePostWithDefaultData;
use Workbench\App\Enums\PostStatus;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

use function OpenSoutheners\LaravelDataMapper\map;

class MapperTest extends TestCase
{
    public function test_map_numeric_id_to_model_results_in_model_instance()
    {
        $user = UserFactory::new()->create();

        $result = map('1')->to(User::class);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->email, $result->email);
    }

    public function test_map_multiple_numeric_ids_to_model_results_in_collection_of_model_instances()
    {
        $users = UserFactory::new()->count(2)->create();

        $result = map('1, 2')->to(User::class);

        $this->assertInstanceOf(DatabaseCollection::class, $result);
        $this->assertEquals($users->first()->email, $result->first()->email);
        $this->assertEquals($users->last()->email, $result->last()->email);
    }

    public function test_map_array_of_numeric_ids_to_model_results_in_array_of_model_instances()
    {
        $users = UserFactory::new()->count(2)->create();

        // A plain (non-assoc) array with no explicit `->through()` infers the
        // 'array' through-class, so the result is a plain array of models
        // rather than a DatabaseCollection.
        $result = map([1, 2])->to(User::class);

        $this->assertIsArray($result);
        $this->assertInstanceOf(User::class, $result[0]);
        $this->assertEquals($users->first()->email, $result[0]->email);
        $this->assertEquals($users->last()->email, $result[1]->email);
    }

    public function test_map_multiple_numeric_ids_to_model_through_base_collection_results_in_base_collection_of_model_instances()
    {
        $users = UserFactory::new()->count(2)->create();

        $result = map('1, 2')->through(Collection::class)->to(User::class);

        $this->assertTrue(get_class($result) === Collection::class);
        $this->assertEquals($users->first()->email, $result->first()->email);
        $this->assertEquals($users->last()->email, $result->last()->email);
    }

    public function test_map_numeric_timestamp_to_carbon_results_in_carbon_instance()
    {
        $timestamp = 1747939147;
        $result = map($timestamp)->to(Carbon::class);

        $this->assertTrue(get_class($result) === \Illuminate\Support\Carbon::class);
        $this->assertEquals($timestamp, $result->timestamp);
    }

    public function test_map_multiple_numeric_timestamps_to_carbon_results_in_collection_of_carbon_instances()
    {
        $timestamps = [1747939147, 1757939147];

        $result = map($timestamps)->through(Collection::class)->to(Carbon::class);

        $this->assertTrue(get_class($result) === Collection::class);
        $this->assertEquals(head($timestamps), $result->first()->timestamp);
        $this->assertEquals(last($timestamps), $result->last()->timestamp);
    }

    public function test_map_multiple_numeric_csv_timestamps_to_carbon_results_in_collection_of_carbon_instances()
    {
        $timestamps = [1747939147, 1757939147];

        $result = map(implode(',', $timestamps))->through(Collection::class)->to(Carbon::class);

        $this->assertTrue(get_class($result) === Collection::class);
        $this->assertEquals(head($timestamps), $result->first()->timestamp);
        $this->assertEquals(last($timestamps), $result->last()->timestamp);
    }

    public function test_map_array_to_generic_object_results_in_std_class_instance()
    {
        $input = ['hello' => 'world', 'foo' => 'bar'];

        $result = map($input)->to(stdClass::class);

        $this->assertTrue(get_class($result) === stdClass::class);
        $this->assertEquals($input['hello'], $result->hello);
        $this->assertEquals($input['foo'], $result->foo);
    }

    public function test_map_array_of_arrays_to_generic_object_results_in_collection_of_std_class_instances()
    {
        $firstObject = ['hello' => 'world', 'foo' => 'bar'];
        $secondObject = ['one' => 'first', 'two' => 'second'];

        $result = map([$firstObject, $secondObject])->through(Collection::class)->to(stdClass::class);

        $this->assertTrue(get_class($result) === Collection::class);
        $this->assertEquals($firstObject['hello'], $result[0]->hello);
        $this->assertEquals($secondObject['one'], $result[1]->one);
    }

    public function test_map_string_to_backed_enum_result_in_backed_enum_instance()
    {
        $result = map('hidden')->to(PostStatus::class);

        $this->assertTrue(get_class($result) === PostStatus::class);
        $this->assertTrue($result === PostStatus::Hidden);
    }

    public function test_map_array_of_strings_to_backed_enum_in_collection_of_backed_enum_instances()
    {
        $result = map(['hidden', 'published'])->through(Collection::class)->to(PostStatus::class);

        $this->assertTrue(get_class($result) === Collection::class);
        $this->assertTrue($result[0] === PostStatus::Hidden);
        $this->assertTrue($result[1] === PostStatus::Published);
    }

    public function test_map_object_to_type_script_results_in_stringified_script_code()
    {
        $result = (string) map(UpdatePostWithDefaultData::class)->to(TypeScript::class);

        $this->assertIsString($result);
        $this->assertStringContainsString('post: Post,', $result);
        $this->assertStringContainsString('author: User,', $result);
        $this->assertStringContainsString('parent: Post | Tag | null,', $result);
    }
}
