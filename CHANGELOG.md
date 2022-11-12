# Release Notes

## [Unreleased](https://github.com/surgiie/console/compare/v0.8.0...master)


## [v0.8.0](https://github.com/surgiie/console/compare/v0.7.0...v0.8.0) - 2022-11-12
### Changed

Remove use of `realpath` in `consoleView` for better support for `phar://` file by @surgiie in https://github.com/surgiie/console/pull/16
Update blade to `v0.4.0` and remove unsused code not removed in previous release. by @surgiie in https://github.com/surgiie/console/pull/15


## [v0.7.0](https://github.com/surgiie/console/compare/v0.6.0...v0.7.0) - 2022-11-11
### Changed

Removed `runTask` due to bad flicker effect, should utilize laravel zero's task command by @surgiie in https://github.com/surgiie/console/pull/14

## [v0.6.0](https://github.com/surgiie/console/compare/v0.5.6...v0.6.0) - 2022-11-07
### Changed

Update blade dependency by @surgiie
## [v0.5.6](https://github.com/surgiie/console/compare/v0.5.5...v0.5.6) - 2022-11-06

### Changed
- Move `clearTerminalLine` to command by @surgiie https://github.com/surgiie/console/pulls/12
## [v0.5.5](https://github.com/surgiie/console/compare/v0.5.4...v0.5.5) - 2022-11-05

### Changed
- Remove empty line from clear terminal line task, delegate to developer @surgiie https://github.com/surgiie/console/pulls/11
## [v0.5.4](https://github.com/surgiie/console/compare/v0.5.3...v0.5.4) - 2022-11-05

### Changed
- Undo broken loader output from previous release by @surgiie https://github.com/surgiie/console/commit/848af4d1242f8c7f62ab9e9104c120d47187e7be
## [v0.5.3](https://github.com/surgiie/console/compare/v0.5.2...v0.5.3) - 2022-11-05

### Changed
- Remove `commands()` and `components()` from `Task` as output is public on command and can be used in `$this` context in the `runTask` callback by @surgiie in https://github.com/surgiie/console/commit/1cdc119b9c2dd5fd5ebef3870c457631b6de0d9a. Fixes issue where output cannot be asserted on artisan command tests.


## [v0.5.2](https://github.com/surgiie/console/compare/v0.5.1...v0.5.2) - 2022-11-05

### Changed
- Add extra spacing to finished message for `runTask` to line up better with output from `$this->components` by @surgiie https://github.com/surgiie/console/pull/10

## [v0.5.1](https://github.com/surgiie/console/compare/v0.5.0...v0.5.1) - 2022-11-04
### Changed
- Handle array input being used in tests/artisan testing during arbitraryOptions parsing by @surgiie https://github.com/surgiie/console/pull/9
## [v0.5.0](https://github.com/surgiie/console/compare/v0.4.0...v0.5.0) - 2022-11-04
### Changed
- `getProperty` uses a new *defined* array property called properties by @surgiie https://github.com/surgiie/console/pull/8
- `getProperty` callback is now nullable by default, return false if not set by @surgiie https://github.com/surgiie/console/pull/8
- `validator` function uses `en` for locale, can be customized via new `getValidationLangLocal` in `WithValidation` trait by @surgiie https://github.com/surgiie/console/pull/8
- `App::call` and `app()` calls have been replaced with `$this->laravel->call` function calls by @surgiie https://github.com/surgiie/console/pull/8
- Pass `invade($input)->tokens` to `OptionsParser` instead of `$argv` to improve testability by @surgiie https://github.com/surgiie/console/pull/8
- `getOrAskForInput` updates `$this->data` when input is valid by @surgiie https://github.com/surgiie/console/pull/8
- `getOrAskForInput` can optionally perform pre/post validation transformation by @surgiie https://github.com/surgiie/console/pull/8
- Use new base `Surgiie\Console\Support\Task` for `CommandTask` and `BackupCommandTask` by @surgiie https://github.com/surgiie/console/pull/8

### Added
- `getData` for getting data from `$this->data` by @surgiie https://github.com/surgiie/console/pull/8
- `validator` lang can be customized via new `getValidationLangLocal` in `WithValidation` trait by @surgiie https://github.com/surgiie/console/pull/8
- `spatie/invade` by @surgiie https://github.com/surgiie/console/pull/8
- Base `Task` class to reuse code @surgiie https://github.com/surgiie/console/pull/8

## [v0.4.0](https://github.com/surgiie/console/compare/v0.3.5...v0.4.0) - 2022-10-24
### Changed
- `$this->clearTerminalLine()` outputs empty line when escape sequence is not supported by @surgiie https://github.com/surgiie/console/pull/7
- lang changes in default error for `FileMustNotExist` and `FileExists rule by @surgiie https://github.com/surgiie/console/pull/7
### Added

- Adds a new `BackupCommandTask` for when pctnl extension is not enabled by @surgiie in https://github.com/surgiie/console/pull/7
## [v0.3.5](https://github.com/surgiie/console/compare/v0.3.4...v0.3.5) - 2022-10-24
### Changed
`$this->exit` now throws new `ExitCommandException` instead of calling `exit` by @surgiie https://github.com/surgiie/console/pull/6
### Added

- Adds a new `ExitCommandException` by @surgiie in https://github.com/surgiie/console/pull/6
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
