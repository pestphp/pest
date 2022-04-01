<?php

use Pest\ConfigLoader;
use Pest\Support\Reflection;

it('fallbacks to default path if no phpunit file is found', function () {
    $instance = new ConfigLoader('fake-path');

    expect(Reflection::getPropertyValue($instance, 'config'))->toBeNull();
    expect($instance->getConfigurationFilePath())->toBeFalse();
    expect($instance->getTestsDirectory())->toBe(ConfigLoader::DEFAULT_TESTS_PATH);
});

it('fallbacks to default path if phpunit is not a valid XML')->skip();
it('fallbacks to default path if failing to read phpunit content')->skip();
it('fallbacks to default path if there is no test suites directory')->skip();
it('fallbacks to default path if test suite directory has no value')->skip();
it('fallbacks to default path if test suite directory does not exist')->skip();
it('returns the parent folder of first test suite directory')->skip();
