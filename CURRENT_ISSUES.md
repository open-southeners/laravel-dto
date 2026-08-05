# Current issues (v4-refactor)

## composer.lock out of sync with composer.json
- **Where**: `composer.lock` (repo root)
- **What**: `laravel/pint` is required in `composer.json` but absent from the lock; locked `illuminate/*` (13.4.0) and `orchestra/testbench` (11.1.0) don't satisfy the declared `^11 || ^12` / `^9 || ^10` constraints. `vendor/bin/pint` doesn't exist locally, so lint verification can't run.
- **Fix**: `composer update` (or widen constraints to include the installed majors, matching what 3.x/main already did for Laravel 13) and commit the refreshed lock.

## ObjectDataMapper rejects Collection-typed data for lists of plain DTOs
- **Where**: `src/Mappers/ObjectDataMapper.php` (`supports()`), interacts with `src/Mappers/CollectionDataMapper.php`
- **What**: After the supports()/priority refactor, `CollectionDataMapper` correctly declines already-`Collection` data (prevents infinite self-recursion), but `ObjectDataMapper` only accepts assoc arrays / JSON objects. A `Collection` of assoc arrays arriving at an object-typed mapping position (e.g. an `array<SomeDto>` property fed through the collection branch) raises `NoMapperFoundException` on the per-item pass. Not exercised by current tests.
- **Fix**: Decide whether `ObjectDataMapper::supports()` should accept `Collection` data and map each item (as `GenericObjectDataMapper`/`CarbonDataMapper`/`BackedEnumDataMapper` do), or whether `CollectionDataMapper::resolve()` should map items itself before recursion.

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
