# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [v1.13.0 (2021-07-28)](https://github.com/pestphp/pest/compare/v1.12.0...v1.13.0)
### Added
- A cleaner output when running the Pest runner in PhpStorm ([#350](https://github.com/pestphp/pest/pull/350)) 
- `toBeIn` expectation ([#363](https://github.com/pestphp/pest/pull/363))

### Fixed
- `skip` with false condition marking test as skipped ([22b822c](https://github.com/pestphp/pest/commit/22b822ce87a3d19d84960fa5c93eb286820b525d))

## [v1.12.0 (2021-07-26)](https://github.com/pestphp/pest/compare/v1.11.0...v1.12.0)
### Added
- `--force` option to override tests in `pest:test` artisan command ([#353](https://github.com/pestphp/pest/pull/353))
- Support for PHPUnit `^9.3.7` ([ca9d783](https://github.com/pestphp/pest/commit/ca9d783cf942a2caabc85ff7a728c7f28350c67a))

### Fixed
- `beforeAll` and `afterAll` behind called multiple times per test ([#357](https://github.com/pestphp/pest/pull/357))

## [v1.11.0 (2021-07-21)](https://github.com/pestphp/pest/compare/v1.10.0...v1.11.0)
### Added
- Support for interacting with datasets in higher order tests ([#352](https://github.com/pestphp/pest/pull/352))

### Changed
- The unit test stub now uses the expectation API ([#348](https://github.com/pestphp/pest/pull/348))

### Fixed
- PhpStorm will no longer show 0 assertions in the output ([#349](https://github.com/pestphp/pest/pull/349))

## [v1.10.0 (2021-07-12)](https://github.com/pestphp/pest/compare/v1.9.1...v1.10.0)
### Added
- The ability to use higher order expectations inside higher order tests ([#341](https://github.com/pestphp/pest/pull/341))

## [v1.9.1 (2021-07-11)](https://github.com/pestphp/pest/compare/v1.9.0...v1.9.1)
### Fixed
- Callable `expect` values in higher order tests failing if the value was an existing method name ([#334](https://github.com/pestphp/pest/pull/344)) 

## [v1.9.0 (2021-07-09)](https://github.com/pestphp/pest/compare/v1.8.0...v1.9.0)
### Changed
- You may now pass just an exception message when using the `throws` method ([#339](https://github.com/pestphp/pest/pull/339)) 

## [v1.8.0 (2021-07-08)](https://github.com/pestphp/pest/compare/v1.7.1...v1.8.0)
### Added
- A new `tap` and test case aware `expect` methods for higher order tests ([#331](https://github.com/pestphp/pest/pull/331))
- Access to test case methods and properties when using `skip` ([#338](https://github.com/pestphp/pest/pull/338))

## [v1.7.1 (2021-06-24)](https://github.com/pestphp/pest/compare/v1.7.0...v1.7.1)
### Fixed
- The `and` method not being usable in Higher Order expectations ([#330](https://github.com/pestphp/pest/pull/330))

## [v1.7.0 (2021-06-19)](https://github.com/pestphp/pest/compare/v1.6.0...v1.7.0)
### Added
- Support for non-callable values in the sequence method, which will be passed as `toEqual` ([#323](https://github.com/pestphp/pest/pull/323))
- Support for nested Higher Order Expectations ([#324](https://github.com/pestphp/pest/pull/324))

## [v1.6.0 (2021-06-18)](https://github.com/pestphp/pest/compare/v1.5.0...v1.6.0)
### Added
- Adds a new `json` expectation method to improve testing with JSON strings ([#325](https://github.com/pestphp/pest/pull/325))
- Adds dot notation support to the `toHaveKey` and `toHaveKeys` expectations ([#322](https://github.com/pestphp/pest/pull/322))

## [v1.5.0 (2021-06-15)](https://github.com/pestphp/pest/compare/v1.4.0...v1.5.0)
### Changed
- Moves plugins from the `require` section to the core itself ([#317](https://github.com/pestphp/pest/pull/317)), ([#318](https://github.com/pestphp/pest/pull/318)), ([#320](https://github.com/pestphp/pest/pull/320))

## [v1.4.0 (2021-06-10)](https://github.com/pestphp/pest/compare/v1.3.2...v1.4.0)
### Added
- Support for multiple datasets (Matrix) on the `with` method ([#303](https://github.com/pestphp/pest/pull/303))
- Support for incompleted tests ([49de462](https://github.com/pestphp/pest/commit/49de462250cf9f65f09e13eaf6dcc0e06865b930))

## [v1.3.2 (2021-06-07)](https://github.com/pestphp/pest/compare/v1.3.1...v1.3.2)
### Fixed 
- Test cases with the @ symbol in the directory fail ([#308](https://github.com/pestphp/pest/pull/308))

## [v1.3.1 (2021-06-06)](https://github.com/pestphp/pest/compare/v1.3.0...v1.3.1)
### Added
- Added for PHPUnit 9.5.5 ([#310](https://github.com/pestphp/pest/pull/310))

### Changed
- Lock minimum Pest plugin versions ([#306](https://github.com/pestphp/pest/pull/306))

## [v1.3.0 (2021-05-23)](https://github.com/pestphp/pest/compare/v1.2.1...v1.3.0)
### Added
- Named datasets no longer show the arguments ([#302](https://github.com/pestphp/pest/pull/302))

### Fixed
- Wraps global functions within `function_exists` ([#300](https://github.com/pestphp/pest/pull/300))

## [v1.2.1 (2021-05-14)](https://github.com/pestphp/pest/compare/v1.2.0...v1.2.1)
### Fixed
- Laravel commands failing with new `--test-directory` option ([#297](https://github.com/pestphp/pest/pull/297))

## [v1.2.0 (2021-05-13)](https://github.com/pestphp/pest/compare/v1.1.0...v1.2.0)
### Added
- Adds JUnit / Infection support ([#291](https://github.com/pestphp/pest/pull/291))
- `--test-directory` command line option ([#283](https://github.com/pestphp/pest/pull/283))

## [v1.1.0 (2021-05-02)](https://github.com/pestphp/pest/compare/v1.0.5...v1.1.0)
### Added
- Possibility of "hooks" being added using the "uses" function ([#282](https://github.com/pestphp/pest/pull/282))

## [v1.0.5 (2021-03-31)](https://github.com/pestphp/pest/compare/v1.0.4...v1.0.5)
### Added
- Add `--browse` option to `pest:dusk` command ([#280](https://github.com/pestphp/pest/pull/280))
- Support for PHPUnit 9.5.4 ([#284](https://github.com/pestphp/pest/pull/284))

## [v1.0.4 (2021-03-17)](https://github.com/pestphp/pest/compare/v1.0.3...v1.0.4)
### Added
- Support for PHPUnit 9.5.3 ([#278](https://github.com/pestphp/pest/pull/278))

## [v1.0.3 (2021-03-13)](https://github.com/pestphp/pest/compare/v1.0.2...v1.0.3)
### Added
- Support for test extensions ([#269](https://github.com/pestphp/pest/pull/269))

## [v1.0.2 (2021-02-04)](https://github.com/pestphp/pest/compare/v1.0.1...v1.0.2)
### Added
- Support for PHPUnit 9.5.2 ([#267](https://github.com/pestphp/pest/pull/267))

## [v1.0.1 (2021-01-18)](https://github.com/pestphp/pest/compare/v1.0.0...v1.0.1)
### Added
- Support for PHPUnit 9.5.1 ([#261](https://github.com/pestphp/pest/pull/261))

### Fixed
- Fix `TestCase@expect` PHPDoc tag ([#251](https://github.com/pestphp/pest/pull/251))

## [v1.0.0 (2021-01-03)](https://github.com/pestphp/pest/compare/v0.3.19...v1.0.0)
### Added
- `pest:test --dusk` option ([#245](https://github.com/pestphp/pest/pull/245))

### Changed
- Stable version
- Updates init structure ([#240](https://github.com/pestphp/pest/pull/240))

## [v0.3.19 (2020-12-27)](https://github.com/pestphp/pest/compare/v0.3.18...v0.3.19)
### Fixed
- Fix binary path in `pest:dusk` command ([#239](https://github.com/pestphp/pest/pull/239))

## [v0.3.18 (2020-12-26)](https://github.com/pestphp/pest/compare/v0.3.17...v0.3.18)
### Added
- `toBeJson()` expectation ([plugin-expectations#2](https://github.com/pestphp/pest-plugin-expectations/pull/2))

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
- Forwards `$this` calls to globals ([#169](https://github.com/pestphp/pest/pull/169))

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
- datasets name conflict ([#101](https://github.com/pestphp/pest/pull/101))

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
