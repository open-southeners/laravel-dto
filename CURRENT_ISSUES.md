# Current issues (v4-refactor)

## Morph model resolution not ported from v3
- **Where**: `src/Mappers/ModelDataMapper.php` (large commented block at bottom)
- **What**: v3 `BindModel` morph resolution (`getMorphModel`, `ModelWith`, `using`) is commented out; `#[ResolveModel]`/`#[ModelWith]` attributes exist but are unwired.
- **Fix**: Port after mapper-selection refactor lands, implemented against `supports()`/priority semantics. Explicitly deferred this round (known-gaps fix round only covered U1–U7 above).

## Stale v3 `docs/` need a rewrite for v4
- **Where**: `docs/` (Vitepress site content, still describes `DataTransferObject`, `make:dto`, `dto:typescript`, `BindModel*` attributes removed/renamed in 4.0.0)
- **What**: Nothing in `docs/` reflects the v4 mapper-registry API (`map()->to()`, `MapperRegistry::register()`, `MappableObject`, `SerializesMapping`, etc). Explicitly deferred this round.
- **Fix**: Full rewrite once the v4 API surface settles (post morph-resolution port, since that changes `ModelDataMapper` docs too).

## `SerializesMapping`'s `MappableObject` fallback has no dedicated test
- **Where**: `src/Concerns/SerializesMapping.php` (`serialiseObject()` — the non-`Stringable`, non-recursive-trait branch that falls back to `get_object_vars()`)
- **What**: `tests/SerializesMappingTest.php` covers Model/Collection/enum/Carbon/nested-`SerializesMapping` properties, but no fixture exercises a plain `MappableObject` (not `Stringable`, not itself using the trait) as a serialisable property, so the `array_map` fallback branch is untested.
- **Fix**: Add a workbench `MappableObject` fixture (no `Stringable`, no `SerializesMapping`) as a property on one of the serialisable test DTOs and assert its public properties round-trip.

## PHPStan: pre-existing `level: max` debt now visible
- **Where**: whole `src/` tree, notably `Support/ValidationRules.php`, `Support/TypeScript.php`, `Attributes/ResolveModel.php`, `Mappers/ObjectDataMapper.php`, `Mappers/ModelDataMapper.php`, `PropertyInfoExtractor.php`
- **What**: `phpstan.neon`'s `checkMissingIterableValueType: false` (removed this round — invalid parameter under PHPStan 2.x, `analyse` refused to run at all with it present) had been silently suppressing a swathe of `missingType.iterableValue`/`missingType.generics` findings; with it gone, `vendor/bin/phpstan analyse` reports 150 pre-existing errors across the codebase unrelated to this round's changes (mostly missing generic/iterable value types and a handful of `argument.type`/`method.nonObject` findings in `TypeScript.php` and `ResolveModel.php`). One `arguments.count` finding introduced by this round's Laravel-11/12/13 `resolveFromAttribute()` version bridge in `ObjectDataMapper` is scoped out via a targeted `ignoreErrors` entry (see `phpstan.neon`) since it's a deliberate 1-arg call that only exists to support the pre-13 signature.
- **Fix**: A dedicated pass to either fix or baseline the remaining 150 findings (`vendor/bin/phpstan analyse --generate-baseline` as a stopgap, or actually add the missing generics/iterable value types file by file). Out of scope for the known-gaps fix round — none of these are new regressions.
