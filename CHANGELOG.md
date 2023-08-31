# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
