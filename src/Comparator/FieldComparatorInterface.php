<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;

/**
 * The Comparator SPI (base-idea §6.2). Register an implementation as a service tagged
 * `pimcore.comparison.field_comparator` (a `priority` attribute orders resolution; the first
 * supporting comparator wins). Projects override core behaviour with a higher-priority comparator.
 *
 * Implementations are autoconfigured onto the tag — implementing this interface is enough.
 */
interface FieldComparatorInterface
{
    /** Whether this comparator handles the given field definition. */
    public function supports(Data $fieldDefinition): bool;

    /**
     * Compare a single field's left and right values into a normalized {@see FieldDiff}.
     *
     * @param mixed $leftValue  raw value from the left object (via getValueForFieldName / getter)
     * @param mixed $rightValue raw value from the right object
     */
    public function compare(
        mixed $leftValue,
        mixed $rightValue,
        Data $fieldDefinition,
        ComparisonContext $context,
    ): FieldDiff;
}
