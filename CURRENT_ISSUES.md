# Current issues (v4-refactor)

## composer.lock out of sync with composer.json
- **Where**: `composer.lock` (repo root)
- **What**: `laravel/pint` is required in `composer.json` but absent from the lock; locked `illuminate/*` (13.4.0) and `orchestra/testbench` (11.1.0) don't satisfy the declared `^11 || ^12` / `^9 || ^10` constraints. `vendor/bin/pint` doesn't exist locally, so lint verification can't run.
- **Fix**: `composer update` (or widen constraints to include the installed majors, matching what 3.x/main already did for Laravel 13) and commit the refreshed lock.

## ObjectDataMapper rejects Collection-typed data for lists of plain DTOs
- **Where**: `src/Mappers/ObjectDataMapper.php` (`supports()`), interacts with `src/Mappers/CollectionDataMapper.php`
- **What**: After the supports()/priority refactor, `CollectionDataMapper` correctly declines already-`Collection` data (prevents infinite self-recursion), but `ObjectDataMapper` only accepts assoc arrays / JSON objects. A `Collection` of assoc arrays arriving at an object-typed mapping position (e.g. an `array<SomeDto>` property fed through the collection branch) raises `NoMapperFoundException` on the per-item pass. Not exercised by current tests.
- **Fix**: Decide whether `ObjectDataMapper::supports()` should accept `Collection` data and map each item (as `GenericObjectDataMapper`/`CarbonDataMapper`/`BackedEnumDataMapper` do), or whether `CollectionDataMapper::resolve()` should map items itself before recursion.

## Instance passthrough regression (v3 → v4)
- **Where**: `src/Mapper.php` (`__construct`/`takeDataFrom` destructure objects before selection), `src/Mappers/ObjectDataMapper.php` (`resolve()` nested mapping), `src/Mappers/ModelDataMapper.php` (`supports()` only takes array|string|int)
- **What**: v3 kept values that were already instances of the target type (`PropertiesMapper.php:118-130` on the 3.x branch). v4 lost this: `map($user)->to(User::class)` throws `NoMapperFoundException`; re-mapping a hydrated DTO destructures enum properties into `['name' => …, 'value' => …]` and fails; an already-`Carbon` property destructures to an empty array. Blocks any object→object re-mapping and complicates queue deserialisation.
- **Fix**: Short-circuit in `Mapper::to()` before registry resolution when the original input (keep it on the instance) is already `instanceof` the target class; ObjectDataMapper's nested branches then inherit the fix since they recurse through `map()`.

## Queue serialisation support not yet designed for v4
- **Where**: no current file — v3 had `DataTransferObject::__serialize()/__unserialize()` (collapse Models to route keys, collections to CSV, rebuild via mapper)
- **What**: v4 has no serialisation story; native `serialize()` on a mapped DTO embeds full Model rows (the problem `SerializesModels` exists to avoid on queues).
- **Fix**: Opt-in `Concerns\SerializesMapping` trait providing `__serialize()` (public props → scalars: Model→`getKey()`/route key, Collection of models→key CSV, enum→value, Carbon→ISO string, nested DTO→recurse) and `__unserialize()` (re-run `map($data)->to(static::class)` and copy properties). Depends on the instance-passthrough fix above only for edge cases; the scalar path already works.

## Dead statement in Mapper::extractProperties
- **Where**: `src/Mapper.php` (`extractProperties()`)
- **What**: `$property->isReadOnly();` return value is discarded — a no-op line, likely leftover from an abandoned readonly-handling idea.
- **Fix**: Delete the line (or implement whatever readonly handling was intended).

## Stale v3 test files segfault the suite
- **Where**: `tests/DataTransferObjectTest.php`, `tests/ValidatedDataTransferObjectTest.php` (and `tests/Unit/DataTransferObjectTest.php`)
- **What**: Full `vendor/bin/phpunit` run exits 139 (segfault) — output dies right after the header when these files run; likely unbounded recursion through `map()` on workbench DTOs. `ValidatedDataTransferObjectTest` also has 3 assertion failures when run alone.
- **Fix**: Port or delete these v3-era tests once the v4 mapping surface stabilises; find the recursion (suspect `ObjectDataMapper` ↔ `CollectionDataMapper` mutual recursion on self-referencing types).

## TypeScript generation test failing
- **Where**: `tests/MapperTest.php` — `test_map_object_to_type_script_results_in_stringified_script_code`
- **What**: Generated output diverges from expectation (model columns typed `any`, morph unions differ). Pre-existing before the registry refactor.
- **Fix**: Revisit `Support/TypeScript.php` against the expected fixture; decide the intended output shape for model-typed properties.

## Morph model resolution not ported from v3
- **Where**: `src/Mappers/ModelDataMapper.php` (large commented block at bottom)
- **What**: v3 `BindModel` morph resolution (`getMorphModel`, `ModelWith`, `using`) is commented out; `#[ResolveModel]`/`#[ModelWith]` attributes exist but are unwired.
- **Fix**: Port after mapper-selection refactor lands, implemented against `supports()`/priority semantics.
