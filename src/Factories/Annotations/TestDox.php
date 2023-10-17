<?php

declare(strict_types=1);

namespace Pest\Factories\Annotations;

use Pest\Contracts\AddsAnnotations;
use Pest\Factories\TestCaseMethodFactory;

final class TestDox implements AddsAnnotations
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(TestCaseMethodFactory $method, array $annotations): array
    {
        /*
         * Escapes docblock according to
         * https://manual.phpdoc.org/HTMLframesConverter/default/phpDocumentor/tutorial_phpDocumentor.howto.pkg.html#basics.desc
         *
         * Note: '@' escaping is not needed as it cannot be the first character of the line (it always starts with @testdox).
         */
        assert($method->description !== null);
        $methodDescription = str_replace('*/', '{@*}', $method->description);

        $annotations[] = "@testdox $methodDescription";

        return $annotations;
    }
}
