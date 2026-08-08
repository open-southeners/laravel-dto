# v4 known-gaps fix round

Fix the laravel-data-mapper entries from project-rezero's `KNOWN_GAPS.md`
(`/Users/d8vjork/Projects/OpenSoutheners/project-rezero/KNOWN_GAPS.md`, `## data-mapper`
section) plus the package's own `CURRENT_ISSUES.md` hygiene items. Work happens in THIS repo
(`/Users/d8vjork/Projects/OpenSoutheners/OSS/laravel-data-mapper`, branch `v4-refactor`).

**Context / constraints**

- The testbed app at `/Users/d8vjork/Projects/OpenSoutheners/project-rezero` consumes this
  package via a path-repo symlink — its suite (`php artisan test`, 151 passed / 2 skipped) is
  the integration harness. Its `tests/Feature/DataMapper/MapperCasesTest.php` pins several of
  these gaps (expect-throw or skip); those pins are EXPECTED to start failing as fixes land —
  do not "fix" the testbed from package units; a final sweep unit updates it separately.
- Package conventions: conventional-commit style history, Pint (`pint.json`), PHPStan
  (`phpstan.neon`), CHANGELOG.md Keep-a-Changelog with `[Unreleased]` (breaking changes on
  v4-refactor are acceptable without BC shims but MUST be logged — CLAUDE.md rule).
- **No unit edits `CHANGELOG.md` or `CURRENT_ISSUES.md`** — parallel units would collide.
  Each unit reports its changelog entry text; the final polish unit writes them all.
- Verification baseline after U1: `vendor/bin/phpunit` green (full suite, no segfault),
  `vendor/bin/pint --test` clean, `vendor/bin/phpstan` no new errors.
- Laravel compat: composer now allows illuminate ^11 || ^12 || ^13 — fixes must work across
  those majors (relevant for U3's container signature).

## Units

### U1 — Suite hygiene (sequential, first)
- `composer update` so the lock satisfies composer.json (pint + testbench install; illuminate 13
  stays current). Commit refreshed lock.
- Delete stale v3 test files that segfault the suite: `tests/DataTransferObjectTest.php`,
  `tests/ValidatedDataTransferObjectTest.php`, `tests/Unit/DataTransferObjectTest.php`
  (v3 behavior lives on the 3.x branch; v4 equivalents exist/are coming in v4 tests).
- Remove the dead `$property->isReadOnly();` statement in `src/Mapper.php`.
- TypeScript failing test (`tests/MapperTest.php::test_map_object_to_type_script_...`):
  investigate briefly; if the fix is small/obvious, fix `Support/TypeScript.php`; otherwise
  mark the test skipped with a reference to CURRENT_ISSUES.md and report.
- Verify: full `vendor/bin/phpunit` runs to completion green (report counts), pint clean.

### U2 — Instance/Model passthrough (after U1; owns `src/Mapper.php`, `src/Mappers/ModelDataMapper.php` if needed)
- Keep the ORIGINAL input on the `Mapper` instance (takeDataFrom currently destructures
  non-Model/Collection objects in the constructor — retain the raw object too).
- Short-circuit in `Mapper::to()`: if original input is already `instanceof` the target class
  (and target is not a Collection wrap situation), return it as-is BEFORE registry resolution.
  Nested properties inherit the fix because ObjectDataMapper recurses through `map()` — but
  nested recursion passes destructured values, so ensure the nested path also short-circuits
  (the nested `map($value)` call receives the original property value — verify enum/Carbon/
  Model/DTO instances survive).
- Semantics decisions (document in tests): passthrough wins over MappableObject re-mapping
  when the value is already the target type (v3 semantics); Collection targets keep current
  behavior (through/collect path untouched); `stdClass` data into a typed DTO still maps.
- Tests: port the relevant rezero cases — `map($model)->to(Model::class)` identity,
  hydrated-DTO re-map round-trip (enum + Carbon + Model + nested MappableObject props),
  `map($enumInstance)->to(SameEnum::class)`, `map($carbon)->to(Carbon::class)`.
- Fixes ledger entries: "No Model/instance passthrough" + "Instance passthrough regression".

### U3 — Contextual attribute fix (after U1, parallel with U2; owns `src/Mappers/ObjectDataMapper.php` + all mapper class declarations)
- Fix `ObjectDataMapper` (`resolveFromAttribute` call): build the matching constructor
  `ReflectionParameter` for the promoted property and call the container across Laravel 11/12/13
  signature differences (L11/12: 1-arg; L13: requires `ReflectionParameter` second arg —
  detect via reflection on `Container::resolveFromAttribute` parameters, not version sniffing).
  Handle non-promoted properties gracefully (fall back or clear error).
- Drop `final` from all built-in mappers under `src/Mappers/` (registry priorities invite
  subclass overrides; rezero had to copy whole implementations). Keep classes cohesive —
  no new hook extraction beyond what un-finaling gives.
- Tests: DTO with `#[Authenticated]` + `#[Inject]` properties maps (workbench has auth
  helpers/testbench actingAs), attribute value wins over input data, subclassing a built-in
  mapper and registering it at higher priority works.
- Fixes ledger entries: "#[Authenticated]/#[Inject] crash" + "Built-in mappers final".

### U4 — Config merge (after U1, parallel; owns `src/ServiceProvider.php`, `config/data-mapper.php`)
- `mergeConfigFrom` in `register()` so `config('data-mapper.*')` works unpublished.
- Remove the dead `types_generation.*` keys from config (the generator commands were removed
  in 4.0) — breaking-change changelog entry to report.
- Tests: testbench test asserting defaults resolve without publishing.
- Fixes ledger entry: "Config is never merged".

### U5 — Collection of plain DTOs (after U2 AND U3 — touches `src/Mappers/CollectionDataMapper.php` and interacts with ObjectDataMapper + passthrough)
- Make `map([['name'=>'a'],['name'=>'b']])->through(Collection::class)->to(SomeDto::class)`
  yield `Collection<SomeDto>`. Preferred: `CollectionDataMapper::resolve()` maps each item via
  `map($item)->withContext(...)->to($objectClass)` instead of re-dispatching the whole wrapped
  Collection; keep the anti-recursion guard. Same for `->through('array')`.
- Watch interactions: items already instances (U2 passthrough) pass through; JSON array of
  objects strings; CSV path unchanged.
- Tests: list-of-assoc-arrays → Collection<DTO> and array<DTO>; mixed already-instance items;
  the rezero case 17 shape exactly.
- Fixes ledger entry: "Collection of plain DTOs is unsupported".

### U6 — `Concerns\SerializesMapping` trait (after U2 + U5)
- New opt-in trait per CURRENT_ISSUES design: `__serialize()` collapses public props to
  scalars (Model → `getQueueableId()`/route key; Eloquent/Support Collection of models → array
  of keys; BackedEnum → value; CarbonInterface → ISO-8601 string; nested DTO using the trait →
  recurse; plain scalars/arrays as-is; null stays null). `__unserialize()` rebuilds via
  `map($data)->to(static::class)` and copies properties onto `$this`.
- Tests: DTO with Model + Collection<Model> + enum + Carbon props: `serialize()` payload
  contains no Eloquent attribute data (assert on payload string), `unserialize()` re-queries
  and yields equivalent DTO; queued-job round-trip through testbench queue if cheap.
- Fixes ledger entry: "No queue serialization story".

### U7 — Package polish (sequential, last package unit)
- Write ALL changelog entries reported by U1–U6 into `CHANGELOG.md` `[Unreleased]`
  (Keep-a-Changelog sections; reader-facing wording).
- Update `CURRENT_ISSUES.md`: remove fixed entries; keep/add remaining open items (morph
  `#[ResolveModel]`/`#[ModelWith]` port still unwired — explicitly deferred this round; stale
  v3 `docs/` rewrite deferred; TypeScript test status per U1 outcome).
- Final serial verify: `vendor/bin/phpunit` green, `vendor/bin/pint --test` clean,
  `vendor/bin/phpstan analyse` no new errors vs baseline,
  AND the rezero integration suite: `cd /Users/d8vjork/Projects/OpenSoutheners/project-rezero
  && php artisan test` — EXPECTED failures are exactly the gap-pinning tests
  (MapperCasesTest cases 17/20 + any workaround-asserting tests); report the precise list,
  fix nothing in rezero.

### U8 — Commit split (package repo)
- commit-splitter over the working tree at the PACKAGE repo, conventional-commit style,
  one commit per logical fix (roughly one per unit; hygiene may split into lock/tests/dead-code).
  Leave untracked `CLAUDE.md` and `tinkerwell-mapper-cases.php` uncommitted. No trailers, no push.

### U9 — Rezero sweep (after U8, separate repo: /Users/d8vjork/Projects/OpenSoutheners/project-rezero)
- Remove now-redundant app workarounds: `app/Support/Mappers/AppObjectDataMapper.php`,
  `app/Support/Mappers/InstancePassthroughMapper.php` + their DataMapperServiceProvider
  registrations (verify package now covers both), keep `ColorDataMapper` (legit custom mapper).
- Update `tests/Feature/DataMapper/MapperCasesTest.php`: cases 8/17/20 assert fixed upstream
  behavior (no skips); adjust any other assertions the fixes changed.
- Optionally demonstrate `SerializesMapping` on one DTO + assert in ActivityJobTest area.
- Update `KNOWN_GAPS.md`: mark each fixed data-mapper entry "Fixed upstream in v4-refactor
  (commit <hash>)" keeping the historical record; leave unfixed entries (morph attributes,
  stale docs) open.
- Verify: full rezero `php artisan test` green again (report counts), pint clean.
- Commits in the rezero repo (feature/rezero-testbed branch), same conventions as before.

## Dependency graph

U1 → {U2, U3, U4 parallel} → U5 → U6 → U7 → U8 → U9

## Deferred (explicitly not this round)

- Morph resolution port (`#[ResolveModel]`/`#[ModelWith]`) — large feature, stays in
  CURRENT_ISSUES.md backlog.
- Full `docs/` rewrite for v4 — stays in CURRENT_ISSUES.md backlog.
