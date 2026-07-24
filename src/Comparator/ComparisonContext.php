<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Bundle\ComparisonBundle\Comparison\Normalizer;
use Pimcore\Model\DataObject\Concrete;

/**
 * Immutable per-comparison context handed to every comparator. Carries the two objects (for
 * relation/asset resolution and calculated-value evaluation), the enabled locales, and the shared
 * Normalizer. A comparator may recurse by asking the registry (passed in) for a child comparator.
 */
final class ComparisonContext
{
    /**
     * @param list<string> $locales enabled languages for localized fields (empty = all)
     */
    public function __construct(
        public readonly Concrete $left,
        public readonly Concrete $right,
        public readonly array $locales,
        public readonly Normalizer $normalizer,
        public readonly ComparatorRegistry $registry,
    ) {
    }

    public function withObjects(Concrete $left, Concrete $right): self
    {
        return new self($left, $right, $this->locales, $this->normalizer, $this->registry);
    }
}
