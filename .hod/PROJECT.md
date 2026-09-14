# The intention of this project

Pest is a testing framework for PHP with a focus on simplicity. A PHP developer uses it to write the tests of an application or of a package, and an AI agent reads its output. It is a package, and `src/Functions.php` holds its public API.

Install the dependencies with `composer install`. Run the tests with `composer test`. Start the program with `./bin/pest`.

- `src/` — the framework: the plugins, the expectation API, the test case and the console output
- `bin/` — the executable `pest` and the worker of the parallel runner
- `overrides/` — the classes of PHPUnit that Pest replaces, which `src/Bootstrappers/BootOverrides.php` loads at boot
- `resources/` — the views of the console output and the base configuration of PHPUnit
- `stubs/` — the files that `pest --init` writes into a project
- `tests/` — the tests of the framework, in Pest itself
- `tests-external/` — the tests that must sit outside `tests/`
- `docker/` — the image that runs the tests on one version of PHP
