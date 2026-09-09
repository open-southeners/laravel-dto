# v4 mapper-selection refactor: supports()/priority + MapperRegistry

Branch: `v4-refactor`. Goal: deterministic mapper selection and a clean extension
surface, replacing fractional scoring.

## Design decisions (fixed — do not re-decide)

1. **Selection**: first-match-wins over priority-ordered mappers.
   `DataMapper::supports(MappingValue $value): bool` replaces `assert()`/`score()`.
   Higher priority wins; priority is set at registration time (int, default `0`).
   Built-ins keep today's effective precedence via explicit priorities:
   MappableObjectMapper 100, CollectionDataMapper 80, ModelDataMapper 70,
   CarbonDataMapper 60, BackedEnumDataMapper 50, GenericObjectDataMapper 40,
   ObjectDataMapper 30.
2. **Mappers return values**: `resolve(MappingValue $value): mixed` returns the
   mapped result. No mutation of `MappingValue`.
3. **`MappingValue` is fully immutable** (all-readonly), and grows context:
   ```php
   final class MappingValue
   {
       public function __construct(
           public readonly mixed $data,
           public readonly ?string $objectClass = null,
           public readonly ?string $collectClass = null,
           public readonly ?\ReflectionProperty $property = null,
           public readonly ?string $path = null,
           public readonly array $providedKeys = [],
       ) {}
   }
   ```
   `originalData` is dropped (with no mutation, `data` never diverges) — update
   `ModelDataMapper` which reads it.
4. **`MapperRegistry`** (new, `src/MapperRegistry.php`), bound as a container
   singleton. API:
   - `register(string|Mappers\DataMapper $mapper, int $priority = 0): void`
   - `resolveFor(MappingValue $value): ?Mappers\DataMapper` (first `supports()`
     in descending priority; ties resolve by registration order, earlier wins)
   - `all(): array` (instances, priority-ordered; instantiate via container
     once, cache instances)
   The static `$mappers` / `registerMapper()` / `getMappers()` on
   `ServiceProvider` are **removed** (v4 is unreleased; no BC shim).
5. **`map()` is single-argument**: `function map(mixed $input): Mapper`.
   `Mapper::__construct` loses the variadic wrap and the
   "unwrap single-element countable" magic (`Mapper.php:28-30`).
6. **No match ⇒ throw** `Exceptions\NoMapperFoundException` (new) from
   `Mapper::to()`, message includes `get_debug_type($data)` and target class.
   Exception: when `to()` is called with no target class and no through class,
   preserving raw pass-through is NOT allowed either — a mapper must match.
7. **Event instead of logging**: new `Events\MappingResolved` with
   `public readonly string $mapperClass` and `public readonly MappingValue $value`.
   Dispatched in `DataMapper::__invoke()`. Delete the `Log::withContext` /
   `config('app.debug')` block.
8. **Rename** `Contracts\MapeableObject` → `Contracts\MappableObject`
   (method `mappingFrom(MappingValue $value): mixed` — now returns the result),
   `Mappers\MapeableObjectMapper` → `Mappers\MappableObjectMapper`.
9. **Nested mapping context**: `Mapper` gains
   `withContext(?\ReflectionProperty $property = null, ?string $path = null): static`
   used by `ObjectDataMapper` when recursing into properties, so nested
   `MappingValue`s carry `property` and `path` (e.g. `author`, `tags.2`).
   `ObjectDataMapper` also fills `providedKeys` with the normalised incoming
   keys of the current object mapping.
10. Clean up commented `dd()`/`dump()` noise in `Mapper::to()`; leave the
    commented morph-porting block in `ModelDataMapper` (separate WIP, log it).

## Verification

- `vendor/bin/phpunit tests/MapperTest.php tests/ObjectMappingTest.php`
  Baseline before refactor: ObjectMappingTest green; MapperTest 12 tests /
  2 failures — `test_map_multiple_numeric_ids_as_args...` (variadic magic being
  removed; test gets rewritten) and `test_map_object_to_type_script...`
  (pre-existing, out of scope, stays failing).
- `vendor/bin/pint --dirty --test` and `vendor/bin/phpstan analyse` should not
  regress.
- Do NOT gate on `tests/DataTransferObjectTest.php` /
  `tests/ValidatedDataTransferObjectTest.php` (stale v3 tests; segfault —
  logged in CURRENT_ISSUES.md).

## Units

### Unit 1 — Core: MappingValue, DataMapper, Registry, Mapper, wiring (sequential, first)
Files: `src/MappingValue.php`, `src/Mappers/DataMapper.php`,
`src/MapperRegistry.php` (new), `src/Exceptions/NoMapperFoundException.php` (new),
`src/Events/MappingResolved.php` (new), `src/Mapper.php`, `src/functions.php`,
`src/ServiceProvider.php`.
Implements decisions 1–7, 9 (the `withContext` method), 10. The seven concrete
mappers will not compile against the new abstract yet — that is Unit 2; do not
touch them beyond what compiles the core.

### Unit 2 — Rewrite concrete mappers (sequential, after Unit 1)
Files: all of `src/Mappers/*.php` except `DataMapper.php`.
Convert each `assert()` into `supports()` (boolean AND of the *hard*
requirements only; drop soft signals into `resolve()` branching where needed),
make `resolve()` return the value. Rename `MapeableObjectMapper` →
`MappableObjectMapper` (decision 8, mapper side). `ObjectDataMapper` recursion
passes context per decision 9. Fix `CarbonDataMapper`'s contradictory
assertions: supports = target is CarbonInterface AND data is string|int|iterable.
`ModelDataMapper`: replace `originalData` reads with `data`.

### Unit 3 — Contract rename + Support classes (parallel with Unit 2, after Unit 1)
Files: `src/Contracts/MapeableObject.php` → `src/Contracts/MappableObject.php`
(git mv), `src/Support/TypeScript.php`, `src/Support/ValidationRules.php`.
Rename interface, change `mappingFrom` to return `mixed` per decision 8, update
both implementors' signatures (their `mappingFrom` must now return the built
result instead of assigning to `$mappingValue->data`).

### Unit 4 — Tests (sequential, after Units 2+3)
Files: `tests/MapperTest.php`, `tests/ObjectMappingTest.php`,
`tests/MapperRegistryTest.php` (new).
- Rewrite `test_map_multiple_numeric_ids_as_args...` to `map([1, 2])`.
- New registry tests: user-registered mapper with higher priority wins over a
  built-in for the same input; equal priority resolves by registration order;
  `NoMapperFoundException` thrown when nothing supports; same input + target
  always selects the same mapper (run selection twice, assert same class).
- Keep the TypeScript test untouched (known pre-existing failure).

## Non-goals (log, don't do)

- Porting morph / `ResolveModel` / `ModelWith` support (commented block in
  `ModelDataMapper`).
- Post-mapping `wasProvided()` API (WeakMap result tracking) — `providedKeys`
  on `MappingValue` is the enabler, the public API comes later.
- Fixing stale v3 test files or the TypeScript generation failure.
