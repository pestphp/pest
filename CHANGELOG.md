# Release Notes for 2.x

## Unreleased

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
