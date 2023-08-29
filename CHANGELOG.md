# Release Notes for 2.x

## Unreleased

## [v2.16.1 (2023-08-29)](https://github.com/pestphp/pest/compare/v2.16.0...v2.16.1)

> New changelog format starting this release.

### Added
* `toHaveSameSize` expectation by @hungthai1401 in https://github.com/pestphp/pest/pull/924, https://github.com/pestphp/pest/pull/930

### Fixed
* Inconsistent type have count exception by @hungthai1401 in https://github.com/pestphp/pest/pull/923
* Datasets defined in `Pest.php` 

## [v2.16.0 (2023-08-21)](https://github.com/pestphp/pest/compare/v2.15.0...v2.16.0)

### Added

- `toBeDigits` ([#911](https://github.com/pestphp/pest/pull/911))
- `toBeCamelCase`, `toBeKebabCase`, `toBeSnakeCase`, `toBeStudlyCase`, `toHaveSnakeCaseKeys`, `toHaveKebabCaseKeys`, `toHaveCamelCaseKeys`, `toHaveStudlyCaseKeys`` ([#921](https://github.com/pestphp/pest/pull/921))
- native functions support on `arch` expectations, e.g: `expect('sleep')->toBeUsed();` ([#4](https://github.com/pestphp/pest-plugin-arch/pull/4))

### Changed

- `phpunit.xml` stub ([#915](https://github.com/pestphp/pest/pull/915))

### Fixed

- Nested sequences ([#895](https://github.com/pestphp/pest/pull/895))

## [v2.15.0 (2023-08-17)](https://github.com/pestphp/pest/compare/v2.14.1...v2.15.0)

### Added

- PHP 8.3 support ([0b261ef](https://github.com/pestphp/pest/commit/0b261ef97b7ceed20cbeeb2b0b41e08e0a8fcaa1))

## [v2.14.1 (2023-08-16)](https://github.com/pestphp/pest/compare/v2.14.0...v2.14.1)

### Changed

- Bumps PHPUnit to `^10.3.2` ([e012517](https://github.com/pestphp/pest/commit/e012517b1643002b36a68096f4a5e26682b1e175))

## [v2.14.0 (2023-08-14)](https://github.com/pestphp/pest/compare/v2.13.0...v2.14.0)

### Added

- `toBeUppercase()`, `toBeLowercase()`, `toBeAlphaNumeric()`, `toBeAlpha()` ([#906](https://github.com/pestphp/pest/pull/906))

## [v2.13.0 (2023-08-09)](https://github.com/pestphp/pest/compare/v2.12.2...v2.13.0)

### Added

- `expect()->ddWhen` and `expect()->ddUnless` ([306b7eb](https://github.com/pestphp/pest/commit/306b7eb2a6a57e570d58228b46501ad9ba4062b4))

## [v2.12.2 (2023-08-07)](https://github.com/pestphp/pest/compare/v2.12.0...v2.12.2)

### Fixed

- Running tests from `uses` parent class ([#898](https://github.com/pestphp/pest/pull/898))

## [v2.12.0 (2023-08-02)](https://github.com/pestphp/pest/compare/v2.11.0...v2.12.0)

### Added

- Allows multiple `toMatchSnapshot` per test ([#881](https://github.com/pestphp/pest/pull/881))

### Changed

- Bumps PHPUnit to `^10.2.7` ([43107c1](https://github.com/pestphp/pest/commit/43107c17436e41e23018ae31705c688168c14784))

## [v2.11.0 (2023-08-01)](https://github.com/pestphp/pest/compare/v2.10.1...v2.11.0)

### Added

- `toBeInvokable`expectation ([#891](https://github.com/pestphp/pest/pull/891))

## [v2.10.1 (2023-07-31)](https://github.com/pestphp/pest/compare/v2.10.0...v2.10.1)

### Fixed

- `not->toHaveSuffix` and `toHavePrefix` expectations ([#888](https://github.com/pestphp/pest/pull/888))

## [v2.10.0 (2023-07-31)](https://github.com/pestphp/pest/compare/v2.9.5...v2.10.0)

### Added

- `repeat` feature ([f3f35a2](https://github.com/pestphp/pest/commit/f3f35a2ed119f63eefd323a8c66d3387e908df3f))

### Fixed

- `-v` option ([86a6b32](https://github.com/pestphp/pest/commit/86a6b3271518742dc39761228687a5107551d279))

## [v2.9.5 (2023-07-22)](https://github.com/pestphp/pest/compare/v2.9.4...v2.9.5)

### Fixed

- Assertions count on arch expectations ([632ffc2](https://github.com/pestphp/pest/commit/632ffc2f8e1fe45f739b12b818426ae14700079e))

## [v2.9.4 (2023-07-22)](https://github.com/pestphp/pest/compare/v2.9.3...v2.9.4)

### Fixed

- Output on `describe` `beforeEach` failure ([5637dfa](https://github.com/pestphp/pest/commit/5637dfa75d1a331adc810935536cde7c3196af06))

## [v2.9.3 (2023-07-20)](https://github.com/pestphp/pest/compare/v2.9.2...v2.9.3)

### Fixed

- Snapshots directory on Windows environments ([cf52752](https://github.com/pestphp/pest/commit/cf5275293fe693ec2cf4dbadbadae01daaa08169))

## [v2.9.2 (2023-07-20)](https://github.com/pestphp/pest/compare/v2.9.1...v2.9.2)

### Fixed

- `beforeEach` on Windows environments ([a37a3b9](https://github.com/pestphp/pest/commit/a37a3b9f9931bc1ab1ea9e1d6d38dfb55dde3f74))

## [v2.9.1 (2023-07-20)](https://github.com/pestphp/pest/compare/v2.9.0...v2.9.1)

### Chore

- Bumps PHPUnit to `^10.2.6` ([8fdb0b3](https://github.com/pestphp/pest/commit/8fdb0b3d32ce9ee12bd182f22751c2b41a53e97b))

## [v2.9.0 (2023-07-19)](https://github.com/pestphp/pest/compare/v2.8.1...v2.9.0)

> "Spicy Summer" is the codename assigned to Pest 2.9, for full details check our announcement: [https://pestphp.com/docs/pest-spicy-summer-release](https://pestphp.com/docs/pest-spicy-summer-release)

### Added

- Built-in Snapshot Testing ([c828756](https://github.com/pestphp/pest/commit/c8287567eb8c3dbea5845b2a6f70804b094b4b60))
- Describe Blocks ([c828756](https://github.com/pestphp/pest/commit/c8287567eb8c3dbea5845b2a6f70804b094b4b60))
- Architectural Testing++ ([c828756](https://github.com/pestphp/pest/commit/c8287567eb8c3dbea5845b2a6f70804b094b4b60))
- Type Coverage Plugin ([c828756](https://github.com/pestphp/pest/commit/c8287567eb8c3dbea5845b2a6f70804b094b4b60))
- Drift Plugin ([c828756](https://github.com/pestphp/pest/commit/c8287567eb8c3dbea5845b2a6f70804b094b4b60))

## [v2.8.1 (2023-06-20)](https://github.com/pestphp/pest/compare/v2.8.0...v2.8.1)

### Fixed
- Fixes "Cannot find TestCase object on call stack" ([eb7bb34](https://github.com/pestphp/pest/commit/eb7bb348253f412e806a6ba6f0df46c0435d0dfe))

## [v2.8.0 (2023-06-19)](https://github.com/pestphp/pest/compare/v2.7.0...v2.8.0)

### Added
- Support for `globs` in `uses` ([#829](https://github.com/pestphp/pest/pull/829))

## [v2.7.0 (2023-06-15)](https://github.com/pestphp/pest/compare/v2.6.3...v2.7.0)

### Added
- Support for unexpected output on printer ([eb9f31e](https://github.com/pestphp/pest/commit/eb9f31edeb00a88c449874f3d48156128a00fff8))

### Chore
- Bumps PHPUnit to `^10.2.2` ([0e5470b](https://github.com/pestphp/pest/commit/0e5470b192b259ba2db7c02a50371216c98fc0a6))

## [v2.6.3 (2023-06-07)](https://github.com/pestphp/pest/compare/v2.6.2...v2.6.3)

### Chore
- Bumps PHPUnit to `^10.2.1` ([73a859e](https://github.com/pestphp/pest/commit/73a859ee563fe96944ba39b191dceca28ef703c2))

## [v2.6.2 (2023-06-02)](https://github.com/pestphp/pest/compare/v2.6.1...v2.6.2)

### Chore
- Bumps PHPUnit to `^10.2.0` ([a0041f1](https://github.com/pestphp/pest/commit/a0041f139cba94fe5d15318c38e275f2e2fb3350))

## [v2.6.1 (2023-04-12)](https://github.com/pestphp/pest/compare/v2.6.0...v2.6.1)

### Fixes
- PHPStorm issue output problem for tests throwing an exception before the first assertion ([#809](https://github.com/pestphp/pest/pull/809))
- Allow traits to be covered ([#804](https://github.com/pestphp/pest/pull/804))

### Chore
- Bumps PHPUnit to `^10.1.3` ([c993252](https://github.com/pestphp/pest/commit/c99325275acf1fd3759b487b93ec50473f706709))

## [v2.6.0 (2023-04-05)](https://github.com/pestphp/pest/compare/v2.5.2...v2.6.0)

### Adds
- Allows `toThrow` to be used against an exception instance ([#797](https://github.com/pestphp/pest/pull/797))

## [v2.5.2 (2023-04-19)](https://github.com/pestphp/pest/compare/v2.5.1...v2.5.2)

### Chore
- Removes `myclabs/php-enuma` dependency ([1a05df1](https://github.com/pestphp/pest/commit/1a05df14d0ce7d12583df26ff716807db6f81f13))

## [v2.5.1 (2023-04-18)](https://github.com/pestphp/pest/compare/v2.5.0...v2.5.1)

### Chore
- Bumps PHPUnit to `^10.1.1` ([ec6a817](https://github.com/pestphp/pest/commit/ec6a81735af19f5463d24545df97535d77697ec6))

## [v2.5.0 (2023-04-14)](https://github.com/pestphp/pest/compare/v2.4.0...v2.5.0)

### Chore
- Bumps PHPUnit to `^10.1.0` ([#780](https://github.com/pestphp/pest/pull/780))

## [v2.4.0 (2023-04-03)](https://github.com/pestphp/pest/compare/v2.3.0...v2.4.0)

### Added
- `skipOnWindows()`, `skipOnMac()`, and `skipOnLinux()` ([#757](https://github.com/pestphp/pest/pull/757))
- source architecture testing violation ([#1](https://github.com/pestphp/pest-plugin-arch/pull/1))([8e66263](https://github.com/pestphp/pest-plugin-arch/commit/8e66263104304e99e3d6ceda25c7ed679b27fb03))
- `toHaveProperties` may now also check values ([#760](https://github.com/pestphp/pest/pull/760))

### Fixed
- Tests on `tests/Helpers` directory not being executed ([#753](https://github.com/pestphp/pest/pull/753))
- Teamcity count ([#747](https://github.com/pestphp/pest/pull/747))
- Parallel execution when class extends class with same name ([#748](https://github.com/pestphp/pest/pull/748))
- Wording on `uses()` hint ([#745](https://github.com/pestphp/pest/pull/745/files))

## [v2.3.0 (2023-03-28)](https://github.com/pestphp/pest/compare/v2.2.3...v2.3.0)

### Added
- Better error handler about missing uses ([#743](https://github.com/pestphp/pest/pull/743))

### Fixed
- Inconsistent spelling of `dataset` ([#739](https://github.com/pestphp/pest/pull/739))

### Chore
- Bumps PHPUnit to `^10.0.19` ([3d7e621](https://github.com/pestphp/pest/commit/3d7e621b7dfc03f0b2d9dcf6eb06c26bc383f502))

## [v2.2.3 (2023-03-24)](https://github.com/pestphp/pest/compare/v2.2.2...v2.2.3)

### Fixed
- Unnecessary dataset on dataset arguments mismatch ([#736](https://github.com/pestphp/pest/pull/736))
- Parallel arguments on plugins order ([#703](https://github.com/pestphp/pest/pull/703))
- Arch plugin runtime exceptions on bad phpdocs ([2f2b51c](https://github.com/pestphp/pest/commit/2f2b51ce3d1b000be9d6add0e785fd0044931b3b))

## [v2.2.2 (2023-03-23)](https://github.com/pestphp/pest/compare/v2.2.1...v2.2.2)

### Fixed
- Edge case in parallel execution test description ([3ce6408](https://github.com/pestphp/pest/commit/3ce640819541ca6022b250e000f336d87c3e7889))

## [v2.2.1 (2023-03-22)](https://github.com/pestphp/pest/compare/v2.2.0...v2.2.1)

### Fixed
- Collision between tests names with underscores ([#724](https://github.com/pestphp/pest/pull/724))

### Chore
- Bumps PHPUnit to `^10.0.18` ([1408cff](https://github.com/pestphp/pest/commit/1408cffc028690057e44f00038f9390f776e6bfb))

## [v2.2.0 (2023-03-22)](https://github.com/pestphp/pest/compare/v2.1.0...v2.2.0)

### Added
- Improved error messages on dataset arguments mismatch ([#698](https://github.com/pestphp/pest/pull/698))
- Allows the usage of `DateTimeInterface` on multiple expectations ([#716](https://github.com/pestphp/pest/pull/716))

### Fixed
- `--dirty` option on Windows environments ([#721](https://github.com/pestphp/pest/pull/721))
- Parallel exit code when `phpunit.xml` is outdated ([14dd5cb](https://github.com/pestphp/pest/commit/14dd5cb57b9432300ac4e8095f069941cb43bdb5))

## [v2.1.0 (2023-03-21)](https://github.com/pestphp/pest/compare/v2.0.2...v2.1.0)

### Added
- `only` test case method ([bcd1503](https://github.com/pestphp/pest/commit/bcd1503cade938853a55c1283b02b6b820ea0b69))

### Fixed
- Issues with different characters on test names ([715](https://github.com/pestphp/pest/pull/715))

## [v2.0.2 (2023-03-20)](https://github.com/pestphp/pest/compare/v2.0.1...v2.0.2)

### Fixed
- `Pest.php` not being loaded in certain scenarios ([b887116](https://github.com/pestphp/pest/commit/b887116e5ce9a69403ad620cad20f0a029474eb5))

## [v2.0.1 (2023-03-20)](https://github.com/pestphp/pest/compare/v2.0.0...v2.0.1)

### Fixed
- Wrong `version` configuration key on `composer.json` ([8f91f40](https://github.com/pestphp/pest/commit/8f91f40e8ea8b35e04b7989bed6a8f9439e2a2d6))

## [v2.0.0 (2023-03-20)](https://github.com/pestphp/pest/compare/v1.22.6...v2.0.0)

Please consult the [upgrade guide](https://pestphp.com/docs/upgrade-guide) and [release notes](https://pestphp.com/docs/announcing-pest2) in the official Pest documentation.
