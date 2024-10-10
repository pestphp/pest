# CONTRIBUTING

Contributions are welcome, and are accepted via pull requests.
Please review these guidelines before submitting any pull requests.

## Process

1. Fork the project
1. Create a new branch
1. Code, test, commit and push
1. Open a pull request detailing your changes. Make sure to follow the [template](.github/PULL_REQUEST_TEMPLATE.md)

## Guidelines

* Please ensure the coding style running `composer lint`.
* Send a coherent commit history, making sure each individual commit in your pull request is meaningful.
* You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
* Please remember that we follow [SemVer](http://semver.org/).

## Setup

Clone your fork, then install the dev dependencies:
```bash
composer install
```
## Lint

Lint your code:
```bash
composer lint
```
## Tests

Update the snapshots:
```bash
composer update:snapshots
```
Run all tests:
```bash
composer test
```

Check types:
```bash
composer test:type:check
```

Unit tests:
```bash
composer test:unit
```

Integration tests:
```bash
composer test:integration
```

## Simplified setup using Docker

If you have Docker installed, you can quickly get all dependencies for Pest in place using
our Docker files. Assuming you have the repository cloned, you may run the following
commands:

1. `make build` to build the Docker image
2. `make install` to install Composer dependencies
3. `make test` to run the project tests and analysis tools

If you want to check things work against a specific version of PHP, you may include
the `PHP` build argument when building the image:

```bash
make build ARGS="--build-arg PHP=8.3"
```

The default PHP version will always be the lowest version of PHP supported by Pest.
