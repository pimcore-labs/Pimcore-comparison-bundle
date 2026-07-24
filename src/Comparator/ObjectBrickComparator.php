<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Container comparator for the `objectbricks` fieldtype (FR-CMP-010). Like the localized-fields
 * comparator it does not diff a scalar value itself; instead it recurses, but the container's items
 * are keyed BY BRICK TYPE (not by index) — a product carries at most one brick of each type.
 *
 * The value on either side is a `Pimcore\Model\DataObject\Objectbrick` container (or null). Rather
 * than a strict `instanceof`, the container is duck-typed via `getItems()` (a list of brick-data
 * objects, each exposing `getType()`), so the comparator works with any object exposing those
 * accessors (and is unit-testable with lightweight doubles). A `null` side contributes no bricks.
 *
 * For every brick type present on either side a CONTAINER child {@see FieldDiff} is produced whose
 * children are the per-field diffs of that brick (resolved through the registry). A brick present on
 * only one side becomes an only-left / only-right child; otherwise the child is changed-if-any-inner
 * -field-differs, else equal. The brick's inner field definitions are resolved from the live
 * Objectbrick definition; when that lookup is unavailable (e.g. a pure unit test with no kernel) the
 * child field set is empty and the brick child reflects presence only.
 */
#[AutoconfigureTag('pimcore.comparison.field_comparator', ['priority' => 40])]
final class ObjectBrickComparator extends AbstractFieldComparator
{
    public function supports(Data $fieldDefinition): bool
    {
        return $fieldDefinition->getFieldtype() === 'objectbricks';
    }

    public function compare(
        mixed $leftValue,
        mixed $rightValue,
        Data $fieldDefinition,
        ComparisonContext $context,
    ): FieldDiff {
        $leftBricks = $this->bricksOf($leftValue);
        $rightBricks = $this->bricksOf($rightValue);

        // Stable order: left brick types first, then any right-only types.
        $types = array_keys($leftBricks);
        foreach (array_keys($rightBricks) as $type) {
            if (!in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        $brickDiffs = [];
        foreach ($types as $type) {
            $leftBrick = $leftBricks[$type] ?? null;
            $rightBrick = $rightBricks[$type] ?? null;

            try {
                $def = \Pimcore\Model\DataObject\Objectbrick\Definition::getByKey($type);
            } catch (\Throwable) {
                $def = null;
            }
            $childFds = $def !== null ? $def->getFieldDefinitions() : [];

            $childrenDiffs = [];
            foreach ($childFds as $childFd) {
                $childCmp = $context->registry->resolve($childFd);
                if ($childCmp === null) {
                    continue;
                }

                $lv = $this->brickValue($leftBrick, $childFd->getName());
                $rv = $this->brickValue($rightBrick, $childFd->getName());

                $childrenDiffs[] = $childCmp->compare($lv, $rv, $childFd, $context);
            }

            if ($leftBrick !== null && $rightBrick === null) {
                $brickStatus = DiffStatus::ONLY_LEFT;
            } elseif ($leftBrick === null && $rightBrick !== null) {
                $brickStatus = DiffStatus::ONLY_RIGHT;
            } else {
                $brickStatus = DiffStatus::EQUAL;
                foreach ($childrenDiffs as $c) {
                    if ($c->status->isDifference()) {
                        $brickStatus = DiffStatus::CHANGED;

                        break;
                    }
                }
            }

            $brickDiffs[] = new FieldDiff(
                $fieldDefinition->getName() . '.' . $type,
                $type,
                'objectbrick',
                $brickStatus,
                null,
                null,
                null,
                $childrenDiffs,
                ['type' => $type],
            );
        }

        $parentStatus = DiffStatus::EQUAL;
        foreach ($brickDiffs as $b) {
            if ($b->status->isDifference()) {
                $parentStatus = DiffStatus::CHANGED;

                break;
            }
        }

        // The container has no scalar value of its own — the brick rows carry the content.
        return $this->diff($fieldDefinition, $parentStatus, null, null, null, $brickDiffs);
    }

    /**
     * Duck-typed extraction of a container's bricks keyed by their type. A `null` side (or any
     * non-container) yields an empty map rather than erroring.
     *
     * @return array<string, object>
     */
    private function bricksOf(mixed $container): array
    {
        if (!is_object($container) || !method_exists($container, 'getItems')) {
            return [];
        }

        $bricks = [];
        foreach ($container->getItems() as $item) {
            if (is_object($item) && method_exists($item, 'getType')) {
                $bricks[$item->getType()] = $item;
            }
        }

        return $bricks;
    }

    /**
     * Read a single field value from a brick-data object. Duck-typed on `getValueForFieldName()` so a
     * `null` brick (an absent brick on one side) yields `null`.
     */
    private function brickValue(?object $brick, string $name): mixed
    {
        if ($brick !== null && method_exists($brick, 'getValueForFieldName')) {
            return $brick->getValueForFieldName($name);
        }

        return null;
    }
}
