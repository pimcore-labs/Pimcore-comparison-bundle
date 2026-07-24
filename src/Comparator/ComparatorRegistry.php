<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Resolves the comparator for a field definition. Comparators are injected priority-first (the tag's
 * `priority` attribute, highest wins) so the first that `supports()` a field is used; a project
 * registers a higher-priority comparator to override a core one. The FallbackComparator sits at the
 * lowest priority and supports everything, so resolution never fails.
 */
final class ComparatorRegistry
{
    /** @var list<FieldComparatorInterface> */
    private array $comparators;

    /**
     * @param iterable<FieldComparatorInterface> $comparators priority-ordered (highest first)
     */
    public function __construct(
        #[AutowireIterator('pimcore.comparison.field_comparator')]
        iterable $comparators,
    ) {
        $this->comparators = $comparators instanceof \Traversable
            ? iterator_to_array($comparators, false)
            : array_values($comparators);
    }

    public function resolve(Data $fieldDefinition): ?FieldComparatorInterface
    {
        foreach ($this->comparators as $comparator) {
            if ($comparator->supports($fieldDefinition)) {
                return $comparator;
            }
        }

        return null;
    }

    /** @return list<FieldComparatorInterface> priority-ordered */
    public function all(): array
    {
        return $this->comparators;
    }
}
