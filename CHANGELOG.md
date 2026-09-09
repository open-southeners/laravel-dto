# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Mapper registry**: custom mappers are now registered on the `MapperRegistry` container singleton with an explicit priority (`register(MyMapper::class, priority: 100)`), and higher-priority mappers win over built-ins for the same input
- `MappingResolved` event dispatched on every mapping resolution, carrying the winning mapper class and the mapping context — replaces the previous debug logging
- Mapping context now carries the target property, a dot-notation `path` (e.g. `tags.2`) for nested mappings, and the set of provided input keys
- `map_arrays_through` config option: set it to `Collection::class` to get collections instead of plain arrays when mapping array input without an explicit `->through()`; inline `->through()` always takes precedence
- **Instance passthrough**: mapping a value that's already an instance of the target class (a `Model`, an enum, a `Carbon`, a hydrated DTO, ...) now hands it back as-is instead of destructuring and re-hydrating it — restores v3 behaviour for `map($user)->to(User::class)`, re-mapping an already-mapped DTO, and nested object/enum/date properties that arrive pre-typed
- All 7 built-in mappers (`ObjectDataMapper`, `CollectionDataMapper`, `ModelDataMapper`, `GenericObjectDataMapper`, `MappableObjectMapper`, `BackedEnumDataMapper`, `CarbonDataMapper`) are no longer `final`, so a subclass can be registered at a higher priority to override or extend built-in behaviour without copying the whole implementation
- Mapping a plain array/collection of associative arrays straight to a DTO class now works: `map([['name' => 'a'], ['name' => 'b']])->through(Collection::class)->to(SomeDto::class)` yields a `Collection<SomeDto>` (each item mapped individually), and the same applies to `->through('array')` and to `Collection<Model>`-typed properties fed a collection of already-hydrated models (which skip re-querying)
- `Concerns\SerializesMapping` trait: opt-in `__serialize()`/`__unserialize()` for mapped DTOs so they can be queued safely — public properties collapse to plain scalars (`Model` → key, `Collection`/array of models → array of keys, backed enum → value, `Carbon` → ISO-8601 string, nested DTO using the trait → recurses) and are rebuilt on the way back through the normal mapper pipeline, so a queued job payload never embeds full Eloquent attribute data

### Changed

- Mapper selection is now deterministic: the first registered mapper (by descending priority) whose `supports()` check passes handles the value — replacing the previous assertion-scoring system, so the same input and target class always resolve to the same mapper
- `map()` now takes a single argument; pass multiple values as an array (`map([1, 2])` instead of `map(1, 2)`), and single-element arrays are no longer unwrapped implicitly
- `mappingFrom()` on mappable objects now returns the mapping result instead of mutating the mapping value
- `MapeableObject` interface renamed to `MappableObject`
- Package config is now merged automatically (`mergeConfigFrom`), so `config('data-mapper.*')` resolves its defaults without requiring `vendor:publish` first

### Fixed

- Mapping an array into an explicitly `Collection`-typed target (including plain `Collection` DTO properties without docblock generics) no longer returns a plain array — the explicit target now takes precedence over the `map_arrays_through` inference
- Mapping a value no mapper can handle now throws `NoMapperFoundException` (with the input type and target class in the message) instead of silently returning the input unmapped
- Date mapping to `Carbon`/`CarbonImmutable` no longer competes against unrelated mappers due to contradictory internal assertions
- `#[Authenticated]`/`#[Inject]` (and any other container contextual attribute) on a DTO property no longer crashes on Laravel 13, where the container's attribute resolution requires an extra argument the package wasn't passing; a non-promoted property carrying one of these attributes now raises a clear error instead of a confusing argument-count crash
- An untyped/`mixed`-generic `array`/`Collection` DTO property (no docblock generic to map each item against) no longer throws — the raw value is kept as-is, still wrapped into the declared collection type
- TypeScript generation (`map($dto)->to(TypeScript::class)`) no longer mislabels members of a union-typed property (e.g. `parent: Post | Tag | null`) with the name of whichever class happened to be processed last

### Removed

- **Breaking:** dead `types_generation.*` config keys removed — the `dto:typescript`/type-generation commands they configured were already removed in 4.0.0


## [4.0.0] - 2025-06-08

### Added

- Attribute `OpenSoutheners\LaravelDto\Attributes\Inject` to inject container stuff
- Attribute `OpenSoutheners\LaravelDto\Attributes\Authenticated` that uses base `Illuminate\Container\Attributes\Authenticated` to inject current authenticated user
- Ability to register custom mappers (extending package functionality)
- `OpenSoutheners\LaravelDto\Contracts\MapeableObject` interface to add custom mapping logic to your own objects classes
- ObjectMapper now extracts type info from generics inside collections typed properties [#1]

### Changed

- Package renamed to `open-southeners/laravel-data-mapper`
- Config file changed and renamed to `config/data-mapper.php` (publish the new one using `php artisan vendor:publish --tag="laravel-data-mapper"`)
- Full refactor [#7]

### Removed

- Abstract class `OpenSoutheners\LaravelDto\DataTransferObject` (using POPO which means _Plain Old Php Objects_)
- Attribute `OpenSoutheners\LaravelDto\Attributes\WithDefaultValue` (when using with `Illuminate\Contracts\Auth\Authenticatable` can be replaced by `OpenSoutheners\LaravelDto\Attributes\Authenticated`)
- Artisan commands: `make:dto`, `dto:typescript`

## [3.8.0] - 2026-04-13

### Added

- Laravel 13 support

## [3.7.0] - 2025-03-04

### Added

- Laravel 12 support

### Removed

- PHP 8.1 support
- Laravel 9 and 10 support

## [3.6.0] - 2024-08-19

### Added

- Command `make:dto` now opens the file after is generated

### Changed

- `extended-php` dependency replaced with `extended-laravel` (required to use `OpensGeneratedFiles` trait on the `make:dto` command)

## [3.5.4] - 2024-08-16

### Changed

- New dependency name replaced `laravel-helpers` with `extended-php`

## [3.5.3] - 2024-06-12

### Fixed

- Filtering reset keys to numeric when string needed

## [3.5.2] - 2024-06-12

### Fixed

- Filtering on collections with not null data

## [3.5.1] - 2024-06-12

### Fixed

- Filtering on collections with not null data

## [3.5.0] - 2024-05-23

### Added

- Improve model morph mapping sending multiple IDs and their matches types. [Documentation link](https://docs.opensoutheners.com/laravel-dto/creating-dtos#morph-binding)

## [3.4.0] - 2024-04-18

### Added

- DataTransferObjects now have `dump` and `dd` methods

### Fixed

- `toArray` method not returning everything when DTOs where used on the request and later on reused. **Caveat: Only one DTO per request until v4 refactor**

## [3.3.0] - 2024-03-13

### Added

- Laravel 11 support
- Collected data transfer objects now mapped properly using `fromArray`

## [3.2.3] - 2023-12-14

### Fixed

- From request context with DTO's `filled` method not working properly

## [3.2.2] - 2023-11-20

### Fixed

- Route resolution on the model's logic (`resolveRouteBindingQuery`)

## [3.2.1] - 2023-11-15

### Fixed

- Route binding when nested DTO is being used with same parameter key as route binding

## [3.2.0] - 2023-11-13

### Changed

- Models route binding now uses Eloquent's Model `resolveRouteBindingQuery` method
- Models uses default routing key (`getRouteKeyName`) only when `BindModel` PHP attribute doesn't have the using argument

### Fixed

- Routes binding models through DTOs now are injected back to route parameters (to be reused in every other part of the software)

## [3.1.3] - 2023-11-09

### Fixed

- `BackedEnum` typed properties with union types falling back to original value

## [3.1.2] - 2023-10-25

### Fixed

- laravel-helpers package version constraints so it doesn't conflict with any other package that uses it in the same way (or even the root project)

## [3.1.1] - 2023-10-23

### Fixed

- Serialisation using old `BindModelUsing` instead of new `BindModel` attribute

## [3.1.0] - 2023-10-23

### Added

- JSON strings to collections (arrays or Illuminate's Collection instances)

## [3.0.3] - 2023-10-17

### Fixed

- Regression introduced by morphs fix using abstract `Model` class

## [3.0.2] - 2023-10-17

### Fixed

- Morph binding using generic Illuminate's model class (not instantiable), fallbacks to `Relation::morphMap`

## [3.0.1] - 2023-10-17

### Fixed

- Passing through with no type casting already matching typed properties (enums, objects, etc...)

## [3.0.0] - 2023-10-16

### Added

- Morph support for data transfer objects model properties and collection properties. For e.g: `public Film|Post $taggable, public string $taggableType`
- Simplificated `OpenSoutheners\LaravelDto\Attributes\BindModel` attribute grouping all the model binding options.
- Support for `OpenSoutheners\LaravelDto\Attributes\BindModel` attribute in morph properties, which can be also used to customise the type key name. For e.g. from the same example from above:

```php
#[BindModel(
  using: [Post::class => 'slug'],
  with: [Post::class => 'tags', Film::class => ['tags', 'posts']],
  morphTypeKey: 'tagType'
)]
public Film|Post $taggable,
public string $tagType
```

### Changed

- **Replace all `#[BindModelUsing('attribute')]` to `#[BindModel(using: 'attribute')]` in your code (take in mind it must not be repeated under the same property)**
- **Replace all `#[BindModelWith(['relation1', 'relation2'])]` to `#[BindModel(with: ['relation1', 'relation2'])]` in your code (take in mind it must not be repeated under the same property)**

## [2.2.1] - 2023-10-17

### Fixed

- Enums parsing when one is being passed to DTOs (thanks @coclav 🎉) [#6]

## [2.2.0] - 2023-10-16

### Added

- `OpenSoutheners\LaravelDto\Attributes\WithDefaultValue` attribute to set default value
- `#[WithDefaultValue(Authenticatable::class)]` will set as default value the authenticated user (`Auth::user()`)

## [2.1.1] - 2023-10-13

### Fixed

- Now `\stdClass` properties get properly serialised as multidimensional arrays

## [2.1.0] - 2023-10-13

### Added

- TypeScript `.d.ts` generation command with `--declarations` option

### Changed

- Default TypeScript types generation command options now have some of them on the config file

### Fixed

- Config file now is exposed to be published using `vendor:publish --provider="OpenSoutheners\\LaravelDto\\ServiceProvider"` or `vendor:publish --tag="config"` commands
- Non typed properties when nullable or not while TypeScript types generation

## [2.0.1] - 2023-10-12

### Fixed

- Keys normalisation on types generator (snake case when enabled from config)
- Types generator when empty collections

## [2.0.0] - 2023-10-12

### Added

- Serialisation for DTO objects so now they can be sent to queued jobs
- `dto:typescript` command for generating TypeScript types based on your application's DTOs

## [1.10.11] - 2023-10-11

### Fixed

- Sending multiple values (for e.g. 1,2,5) on a collection with `BindModelUsing` attribute or custom model's `getRouteKeyName` method now works querying all models
- `make:dto` command now does not generate a `ValidatedDataTransferObject` with request static method on it

## [1.10.10] - 2023-10-09

### Fixed

- Request option for `make:dto` command when sent without a value fails

## [1.10.9] - 2023-10-06

### Fixed

- `make:dto` with `_id` ending properties

## [1.10.8] - 2023-10-06

### Fixed

- Fix `make:dto` command when validated form request sent with properties that has children items like `array.*.item`

## [1.10.7] - 2023-10-06

### Fixed

- `DataTransferObject::toArray` when DTO constructed `fromArray` is getting request stuff, it doesn't get all properties

## [1.10.6] - 2023-10-05

### Fixed

- `make:dto` with request option doesn’t add class string to static method

## [1.10.5] - 2023-10-05

### Fixed

- `make:dto` command with validated requests sent to option

## [1.10.4] - 2023-10-05

### Fixed

- Validation not applied for ValidatedDataTransferObject interface DTOs

## [1.10.3] - 2023-10-04

### Changed

- `ValidatedDataTransferObject` interface is no longer resolved, instead `DataTransferObject` class will be the one resolved (for those DTOs that doesn't have a `FormRequest` class on them, validated form data)

## [1.10.2] - 2023-10-04

### Changed

- Better error reporting when DTO class is being bound to a controller

## [1.10.1] - 2023-10-04

### Fixed

- Collections does not get mapped when Illuminate's collection has been sent to DTO

## [1.10.0] - 2023-09-27

### Changed

- Model binding defaults to primary key instead of `Model::getRouteKeyName()` (which should be used for those coming from routes instead)
- Route bound models are using specified attributes instead of default to IDs (model's primary keys). For e.g. `posts/{post:slug}` will use slug on the DTO query

### Fixed

- BindModelUsing now uses the attribute on the binding query

## [1.9.0] - 2023-09-26

### Added

- `OpenSoutheners\LaravelDto\Attributes\BindModelUsing` property PHP attribute class for use attribute to do the binding/serialisation

## [1.8.3] - 2023-09-26

### Fixed

- Issue mapping custom objects from data collections (native arrays or Illuminate's collections)

## [1.8.2] - 2023-09-22

### Added

- `make:dto --request` command now accepts empty request option to create empty base DTO class with empty request method

### Fixed

- `make:dto --request` now fill request method properly

## [1.8.1] - 2023-09-12

### Fixed

- Collections binding models were returning model instance instead of array or `Illuminate\Support\Collection` with the models inside
- `DataTransferObject::filled()` method now checks within route parameters as well as sent request body data if is within request context
- `DataTransferObject::toArray()` method now returns arrays with nested `toArray` calls when collections or models

## [1.8.0] - 2023-09-07

### Added

- `php artisan make:dto --request='App\Http\Requests\PostCreateFormRequest' PostCreateData` will now fill the request part and all properties for you with their types (**experimental**)
- Map arrays or json strings into generic objects (`\stdClass`) or custom objects (using their classes)
- Map string dates into `Illuminate\Support\Carbon` or `Carbon\Carbon` or `Carbon\CarbonImmutable` instances

## [1.7.1] - 2023-09-01

### Fixed

- Laravel collections not being mapped properly
- Arrays not mapping properly when not containing strings (arrays with arrays inside)

## [1.7.0] - 2023-09-01

### Added

- Command option `--request` to create `ValidatedDataTransferObject` with request method in it.

## [1.6.0] - 2023-08-31

### Added

- Add binding resolution so DataTransferObjects can act as validated requests using `ValidatedDataTransferObject` interface
- Add authenticated user “automagical” binding to `DataTransferObject` property when possible (need to be typed as Authenticatable illuminate's contract)

### Changed

- Now route parameters are merged into DataTransferObjects when running within requests context

## [1.5.1] - 2023-08-10

### Fixed

- Minor issue, now doesn't query when sending model instance using `DataTransferObject::fromArray()` method

## [1.5.0] - 2023-05-02

### Changed

- Properties returned from `toArray` are now snake cased (from `myPropertyName` to `my_property_name`)
- Improved `toArray` to return just some modified properties (from defaults)

## [1.4.1] - 2023-04-26

### Added

- `toArray` method to DTOs

### Fixed

- `BindModelWith` attribute on collection typed properties

## [1.4.0] - 2023-04-26

### Added

- Introducing mapped types using docblock `@param` type like the following: `@param array<\App\Models\MyModel> $models` or `@param \Illuminate\Support\Collection<\App\Models\MyModel> $models`
- Optional normalisation option for properties names (`workspace_id` to `workspace`, `post_tags` to `postTags`, etc) to package config file (publish using command `php artisan vendor:publish --provider="OpenSoutheners\LaravelDto\ServiceProvider"`)
- Attribute `OpenSoutheners\LaravelDto\Attributes\NormaliseProperties` to use in some DTO classes that needs properties normalisation (when globally disabled from config)
- Attribute `OpenSoutheners\LaravelDto\Attributes\BindModelWith` to bind to model with relationships included

### Changed

- Now using `symfony/property-info` for better property assertion (so many bugs and inconsistencies on promoted properties in PHP8+ assertions)

## [1.3.1] - 2023-03-21

### Changed

- `DataTransferObject::fromArray` now uses `initialize` method instead of `withDefaults` (internal change, shouldn't affect anything)

## [1.3.0] - 2023-03-17

### Changed

- `DataTransferObject::filled` method refactored with better logic

## [1.2.0] - 2023-03-16

### Added

- `DataTransferObject::fromRequest()` method shortcut (does same as `DataTransferObject::fromArray($request->validated())` or `DataTransferObject::fromArray($request->all())`)

## [1.1.0] - 2023-03-16

### Added

- `make:dto` command to generate data transfer object classes

## [1.0.0] - 2023-03-16

### Added

- Initial release!
