<?php

namespace OpenSoutheners\LaravelDataMapper\Tests;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use OpenSoutheners\LaravelDataMapper\Events\MappingResolved;
use OpenSoutheners\LaravelDataMapper\Exceptions\NoMapperFoundException;
use OpenSoutheners\LaravelDataMapper\MapperRegistry;
use OpenSoutheners\LaravelDataMapper\Mappers\BackedEnumDataMapper;
use OpenSoutheners\LaravelDataMapper\Mappers\DataMapper;
use OpenSoutheners\LaravelDataMapper\Mappers\ModelDataMapper;
use OpenSoutheners\LaravelDataMapper\MappingValue;
use Workbench\App\DataObjects\CreateUserData;
use Workbench\App\Enums\PostStatus;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

use function OpenSoutheners\LaravelDataMapper\map;

class MapperRegistryTest extends TestCase
{
    public function test_user_registered_mapper_with_higher_priority_wins_over_built_in()
    {
        app(MapperRegistry::class)->register(UppercasingEnumFixtureMapper::class, 200);

        $result = map('hidden')->to(PostStatus::class);

        $this->assertSame('HIDDEN', $result);
    }

    public function test_equal_priority_resolves_by_registration_order()
    {
        app(MapperRegistry::class)->register(FirstFixtureMapper::class, 10);
        app(MapperRegistry::class)->register(SecondFixtureMapper::class, 10);

        $result = map('anything')->to(UnmappableFixtureTarget::class);

        $this->assertSame('first', $result);
    }

    public function test_no_mapper_found_exception_thrown_when_nothing_supports_value()
    {
        $this->expectException(NoMapperFoundException::class);

        map('plain string')->to(UnmappableFixtureTarget::class);
    }

    public function test_resolving_same_input_and_target_twice_selects_same_mapper_class()
    {
        $mappingValue = new MappingValue(data: '1', objectClass: User::class);

        $registry = app(MapperRegistry::class);

        $first = $registry->resolveFor($mappingValue);
        $second = $registry->resolveFor($mappingValue);

        $this->assertInstanceOf(ModelDataMapper::class, $first);
        $this->assertSame(get_class($first), get_class($second));
    }

    public function test_map_multiple_ids_through_base_collection_to_model_returns_base_collection_of_users()
    {
        $users = UserFactory::new()->count(2)->create();

        $result = map('1, 2')->through(Collection::class)->to(User::class);

        $this->assertSame(Collection::class, get_class($result));
        $this->assertEquals($users->first()->email, $result->first()->email);
        $this->assertEquals($users->last()->email, $result->last()->email);
    }

    public function test_map_assoc_array_to_data_object_is_not_misrouted_to_collection_mapper()
    {
        $result = map(['name' => 'X', 'email' => 'y@z.com'])->to(CreateUserData::class);

        $this->assertInstanceOf(CreateUserData::class, $result);
        $this->assertSame('X', $result->name);
        $this->assertSame('y@z.com', $result->email);
    }

    public function test_mapping_resolved_event_is_dispatched_with_winning_mapper_class()
    {
        Event::fake([MappingResolved::class]);

        $result = map('hidden')->to(PostStatus::class);

        $this->assertSame(PostStatus::Hidden, $result);

        Event::assertDispatched(
            MappingResolved::class,
            fn (MappingResolved $event) => $event->mapperClass === BackedEnumDataMapper::class
        );
    }

    public function test_subclass_of_built_in_mapper_registered_with_higher_priority_wins()
    {
        app(MapperRegistry::class)->register(UppercasingBackedEnumSubclassFixtureMapper::class, 200);

        $result = map('hidden')->to(PostStatus::class);

        $this->assertSame('HIDDEN-SUBCLASS', $result);
    }
}

/**
 * Extends the built-in `BackedEnumDataMapper` (rather than implementing
 * `DataMapper` from scratch) to prove built-in mappers are no longer `final`
 * and can be overridden by subclasses registered at a higher priority.
 */
final class UppercasingBackedEnumSubclassFixtureMapper extends BackedEnumDataMapper
{
    public function resolve(MappingValue $mappingValue): mixed
    {
        $result = parent::resolve($mappingValue);

        return $result instanceof \BackedEnum ? strtoupper($result->value).'-SUBCLASS' : $result;
    }
}

/**
 * Fixture mapper that intercepts string input targeting PostStatus,
 * territory normally owned by the built-in BackedEnumDataMapper.
 */
final class UppercasingEnumFixtureMapper extends DataMapper
{
    public function supports(MappingValue $mappingValue): bool
    {
        return $mappingValue->objectClass === PostStatus::class && is_string($mappingValue->data);
    }

    public function resolve(MappingValue $mappingValue): mixed
    {
        return strtoupper($mappingValue->data);
    }
}

/**
 * Fixture mapper class used purely as an unmappable target: nothing (built-in
 * or fixture, unless explicitly registered) supports resolving into it.
 */
final class UnmappableFixtureTarget
{
    //
}

final class FirstFixtureMapper extends DataMapper
{
    public function supports(MappingValue $mappingValue): bool
    {
        return $mappingValue->objectClass === UnmappableFixtureTarget::class;
    }

    public function resolve(MappingValue $mappingValue): mixed
    {
        return 'first';
    }
}

final class SecondFixtureMapper extends DataMapper
{
    public function supports(MappingValue $mappingValue): bool
    {
        return $mappingValue->objectClass === UnmappableFixtureTarget::class;
    }

    public function resolve(MappingValue $mappingValue): mixed
    {
        return 'second';
    }
}
