<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Feature\Attribute;

/**
 * Declares that a test (class or method) covers a feature id. Tests declare what they cover, not the
 * other way round; the coverage ingest reads these + the JUnit report to derive each feature's PHPUnit
 * evidence. The Playwright equivalent is a `@feature:<id>` tag in the spec title.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class CoversFeature
{
    public function __construct(public string $id)
    {
    }
}
