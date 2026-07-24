<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;

/**
 * Shared helpers for comparators: label resolution, value rendering (reusing Pimcore's own
 * per-fieldtype getVersionPreview), presence/emptiness, and the common status decision.
 */
abstract class AbstractFieldComparator implements FieldComparatorInterface
{
    protected function label(Data $fieldDefinition): string
    {
        $title = method_exists($fieldDefinition, 'getTitle') ? (string) $fieldDefinition->getTitle() : '';

        return $title !== '' ? $title : $fieldDefinition->getName();
    }

    /**
     * Render a value to a human display string, reusing the fielddefinition's own version-preview
     * where it provides one (Pimcore already renders inputs, dates, selects, etc.); otherwise a
     * best-effort scalar cast.
     */
    protected function render(Data $fieldDefinition, mixed $value, ComparisonContext $context): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            $preview = $fieldDefinition->getVersionPreview($value, null, []);
            if (is_string($preview) && $preview !== '' && $preview !== 'no preview') {
                return $preview;
            }
        } catch (\Throwable) {
            // fall through to scalar cast
        }

        if (is_scalar($value)) {
            return (string) $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }

    protected function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * The common equal / changed / only-left / only-right decision for a scalar-ish pair, given a
     * pre-computed equality verdict.
     */
    protected function statusFor(mixed $left, mixed $right, bool $equal): DiffStatus
    {
        $leftEmpty = $this->isEmpty($left);
        $rightEmpty = $this->isEmpty($right);

        if ($leftEmpty && $rightEmpty) {
            return DiffStatus::EQUAL;
        }
        if ($leftEmpty) {
            return DiffStatus::ONLY_RIGHT;
        }
        if ($rightEmpty) {
            return DiffStatus::ONLY_LEFT;
        }

        return $equal ? DiffStatus::EQUAL : DiffStatus::CHANGED;
    }

    protected function diff(
        Data $fieldDefinition,
        DiffStatus $status,
        ?string $leftDisplay,
        ?string $rightDisplay,
        ?array $inlineDiff = null,
        array $children = [],
        array $meta = [],
    ): FieldDiff {
        return new FieldDiff(
            $fieldDefinition->getName(),
            $this->label($fieldDefinition),
            $fieldDefinition->getFieldtype(),
            $status,
            $leftDisplay,
            $rightDisplay,
            $inlineDiff,
            $children,
            $meta,
        );
    }
}
