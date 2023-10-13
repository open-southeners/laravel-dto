# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
