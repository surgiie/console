# Release Notes

## [Unreleased](https://github.com/surgiie/console/compare/v0.3.4...master)

## [v0.3.4](https://github.com/surgiie/console/compare/v0.3.3...v0.3.4) - 2022-10-23
### Changed

- Fix bug where `showPerformanceStats` and `getOrAskForInput` are referencing old function name by @surgiie in https://github.com/surgiie/console/pull/5
## [v0.3.2](https://github.com/surgiie/console/compare/v0.3.0...v0.3.2) - 2022-10-22

## [v0.3.3](https://github.com/surgiie/console/compare/v0.3.2...v0.3.3) - 2022-10-23
### Changed

- When pcntl is not installed fallback to `nunomaduro/laravel-console-task` for `runTask` by @surgiie in https://github.com/surgiie/console/pull/4
## [v0.3.2](https://github.com/surgiie/console/compare/v0.3.0...v0.3.2) - 2022-10-22

### Changed

- Filter `null` values from data on `Command` during `execute`
## [v0.3.1](https://github.com/surgiie/console/compare/v0.3.0...v0.3.1) - 2022-10-22

### Changed

- Fix typo/lang redundancy in `LoadsEnvFiles` and `LoadsJsonFiles` traits by @surgiie in https://github.com/surgiie/console/pull/2

## [v0.3.0](https://github.com/surgiie/console/compare/v0.2.0...v0.3.0) - 2022-10-22

### Changed

- Fix arbitraryData options being registered when an existing signature option is already available by @surgiie in https://github.com/surgiie/console/pull/1

### Added

- Added new `LoadsEnvFiles` trait for loading env file variables both into and not into `$_ENV` by @surgiie in https://github.com/surgiie/console/pull/1

## [v0.2.0](https://github.com/surgiie/console/compare/v0.1.0...v0.2.0) - 2022-10-20

### Changed

- Default message for `FileMustNotExist` rule by @surgiie in https://github.com/surgiie/console/commit/8b351d8e1fa0fac7e6fb66f4b2ab63de956a7ccd

### Added

- Added new `FileOrDirectoryExists` @surgiie in https://github.com/surgiie/console/commit/8b351d8e1fa0fac7e6fb66f4b2ab63de956a7ccd
