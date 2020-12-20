# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [v0.3.17 (2020-12-20)](https://github.com/pestphp/pest/compare/v0.3.16...v0.3.17)
### Fixed
- Class inheritance with `depends()` ([#236](https://github.com/pestphp/pest/pull/236))

## [v0.3.16 (2020-12-13)](https://github.com/pestphp/pest/compare/v0.3.15...v0.3.16)
### Changed
- Moves expectation API for external plugin ([5d7f262](https://github.com/pestphp/pest/commit/5d7f262f4ab280a660a85900f402eebb23abfda8))

## [v0.3.15 (2020-12-04)](https://github.com/pestphp/pest/compare/v0.3.14...v0.3.15)
### Added
- Support for PHPUnit 9.5.0 ([#234](https://github.com/pestphp/pest/pull/234))
- Support for extending expectation API ([#232](https://github.com/pestphp/pest/pull/232))

### Fixed
- Static analysis while using string as key for datasets ([#233](https://github.com/pestphp/pest/pull/233))

## [v0.3.14 (2020-11-28)](https://github.com/pestphp/pest/compare/v0.3.13...v0.3.14)
### Added
- `pest:dusk` command ([#223](https://github.com/pestphp/pest/pull/223))
- Better feedback on errors in `toMatchArray` and `toMatchObject` ([#231](https://github.com/pestphp/pest/pull/231))

## [v0.3.13 (2020-11-23)](https://github.com/pestphp/pest/compare/v0.3.12...v0.3.13)
### Added
- `toMatchArray` expectation ([7bea51f](https://github.com/pestphp/pest/commit/7bea51fe09dd2eca7093e4c34cf2dab2e8d39fa5), [3fd24d9](https://github.com/pestphp/pest/commit/3fd24d96d3145dcebdb0aab40aa8b76faa8b6979))
- Add Pest options to `--help` output ([#217](https://github.com/pestphp/pest/pull/217))

### Fixed
- Resolve issue with name resolution in `depends()` ([#216](https://github.com/pestphp/pest/pull/216))

## [v0.3.12 (2020-11-11)](https://github.com/pestphp/pest/compare/v0.3.11...v0.3.12)
### Added
- Add support for PHPUnit 9.4.3 ([#219](https://github.com/pestphp/pest/pull/219))

## [v0.3.11 (2020-11-09)](https://github.com/pestphp/pest/compare/v0.3.10...v0.3.11)
### Changed
- Improved the exception output for the TeamCity printer (usage with phpstorm plugin) ([#215](https://github.com/pestphp/pest/pull/215))

## [v0.3.10 (2020-11-01)](https://github.com/pestphp/pest/compare/v0.3.9...v0.3.10)
### Added
- Add support for PHPUnit 9.4.2 ([d177ab5](https://github.com/pestphp/pest/commit/d177ab5ec2030c5bb8e418d10834c370c94c433d))

## [v0.3.9 (2020-10-13)](https://github.com/pestphp/pest/compare/v0.3.8...v0.3.9)
### Added
- Add support for named datasets in description output ([#134](https://github.com/pestphp/pest/pull/134))
- Add Pest version to `--help` output ([#203](https://github.com/pestphp/pest/pull/203))
- Add support for PHPUnit 9.4.1 ([#207](https://github.com/pestphp/pest/pull/207))

## [v0.3.8 (2020-10-03)](https://github.com/pestphp/pest/compare/v0.3.7...v0.3.8)
### Added
- Add support for PHPUnit 9.4.0 ([#199](https://github.com/pestphp/pest/pull/199))

### Fixed
- Fix chained higher order assertions returning void ([#196](https://github.com/pestphp/pest/pull/196))

## [v0.3.7 (2020-09-30)](https://github.com/pestphp/pest/compare/v0.3.6...v0.3.7)
### Added
- Add support for PHPUnit 9.3.11 ([#193](https://github.com/pestphp/pest/pull/193))

## [v0.3.6 (2020-09-21)](https://github.com/pestphp/pest/compare/v0.3.5...v0.3.6)
### Added
- `toMatch` expectation ([#191](https://github.com/pestphp/pest/pull/191))
- `toMatchConstraint` expectation ([#190](https://github.com/pestphp/pest/pull/190))

## [v0.3.5 (2020-09-16)](https://github.com/pestphp/pest/compare/v0.3.4...v0.3.5)
### Added
- `toStartWith` and `toEndWith` expectations ([#187](https://github.com/pestphp/pest/pull/187))

## [v0.3.4 (2020-09-15)](https://github.com/pestphp/pest/compare/v0.3.3...v0.3.4)
### Added
- `toMatchObject` expectation ([4e184b2](https://github.com/pestphp/pest/commit/4e184b2f906c318a5e9cd38fe693cdab5c48d8a2))

## [v0.3.3 (2020-09-13)](https://github.com/pestphp/pest/compare/v0.3.2...v0.3.3)
### Added
- `toHaveKeys` expectation ([204f343](https://github.com/pestphp/pest/commit/204f343831adc17bb3734553c24fac92d02f27c7))

## [v0.3.2 (2020-09-12)](https://github.com/pestphp/pest/compare/v0.3.1...v0.3.2)
### Added
- Support to PHPUnit 9.3.9, and 9.3.10 ([1318bf9](https://github.com/pestphp/pest/commit/97f98569bc86e8b87f8cde963fe7b4bf5399623b))

## [v0.3.1 (2020-08-29)](https://github.com/pestphp/pest/compare/v0.3.0...v0.3.1)
### Added
- Support to PHPUnit 9.3.8 ([#174](https://github.com/pestphp/pest/pull/174))

## [v0.3.0 (2020-08-27)](https://github.com/pestphp/pest/compare/v0.2.3...v0.3.0)
### Added
- Expectation API (TODO)
- PHPUnit 9.3 and PHP 8 support ([#128](https://github.com/pestphp/pest/pull/128))
- Fowards `$this` calls to globals ([#169](https://github.com/pestphp/pest/pull/169))

### Fixed
- don't decorate output if --colors=never is set ([36b879f](https://github.com/pestphp/pest/commit/36b879f97d7b187c87a94eb60af5b7d3b7253d56))

## [v0.2.3 (2020-07-01)](https://github.com/pestphp/pest/compare/v0.2.2...v0.2.3)
### Added
- `--init` and `pest:install` artisan command output changes ([#118](https://github.com/pestphp/pest/pull/118), [db7c4b1](https://github.com/pestphp/pest/commit/db7c4b174f0974969450dda71dcd649ef0c073a3))
- `--version` option to view the current version of Pest ([9ea51ca](https://github.com/pestphp/pest/commit/9ea51caf3f74569debb1e465992e9ea916cb80fe))

## [v0.2.2 (2020-06-21)](https://github.com/pestphp/pest/compare/v0.2.1...v0.2.2)
### Added
- `depends` phpunit feature ([#103](https://github.com/pestphp/pest/pull/103))

### Fixes
- datasets name conflit ([#101](https://github.com/pestphp/pest/pull/101))

## [v0.2.1 (2020-06-17)](https://github.com/pestphp/pest/compare/v0.2.0...v0.2.1)
### Fixes
- Multiple `uses` in the same path override previous `uses` ([#97](https://github.com/pestphp/pest/pull/97))

## [v0.2.0 (2020-06-14)](https://github.com/pestphp/pest/compare/v0.1.5...v0.2.0)
### Adds
- `--init` option to install Pest on a new blank project ([70b3c7e](https://github.com/pestphp/pest/commit/70b3c7ea1ddb031f3bbfaabdc28d56270608ebbd))
- pending higher orders tests aka tests without description ([aa1917c](https://github.com/pestphp/pest/commit/aa1917c28d9b69c2bd1d51f986c4f61318ee7e16))

### Fixed
- `--verbose` and `--colors` options not being used by printers ([#51](https://github.com/pestphp/pest/pull/51))
- missing support on windows ([#61](https://github.com/pestphp/pest/pull/61))

### Changed
- `helpers.php` stub provides now namespaced functions
- functions provided by plugins are now namespaced functions:

```php
use function Pest\Faker\faker;

it('foo', function () {
    $name = faker()->name;
});
```

## [v0.1.5 (2020-05-24)](https://github.com/pestphp/pest/compare/v0.1.4...v0.1.5)
### Fixed
- Missing default decorated output on coverage ([88d2391](https://github.com/pestphp/pest/commit/88d2391d2e6fe9c9416462734b9b523cb418f469))

## [v0.1.4 (2020-05-24)](https://github.com/pestphp/pest/compare/v0.1.3...v0.1.4)
### Added
- Support to Lumen on artisan commands ([#18](https://github.com/pestphp/pest/pull/18))

### Fixed
- Mockery tests without assertions being considered risky ([415f571](https://github.com/pestphp/pest/commit/415f5719101b30c11d87f74810a71686ef2786c6))

## [v0.1.3 (2020-05-21)](https://github.com/pestphp/pest/compare/v0.1.2...v0.1.3)
### Added
- `Plugin::uses()` method for making traits globally available ([6c4be01](https://github.com/pestphp/pest/commit/6c4be0190e9493702a976b996bbbf5150cc6bb53))

## [v0.1.2 (2020-05-15)](https://github.com/pestphp/pest/compare/v0.1.1...v0.1.2)
### Added
- Support to custom helpers ([#7](https://github.com/pestphp/pest/pull/7))

## [v0.1.1 (2020-05-14)](https://github.com/pestphp/pest/compare/v0.1.0...v0.1.1)
### Added
- `test` function without any arguments returns the current test case ([6fc55be](https://github.com/pestphp/pest/commit/6fc55becc8aecff685a958617015be1a4c118b01))

### Fixed
- "No coverage driver error" now returns proper error on Laravel ([28d8822](https://github.com/pestphp/pest/commit/28d8822de01f4fa92c62d8b8e019313f382b97e9))

## [v0.1.0 (2020-05-09)](https://github.com/pestphp/pest/commit/de2929077b344a099ef9c2ddc2f48abce14e248f)
### Added
- First version
