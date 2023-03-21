# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
