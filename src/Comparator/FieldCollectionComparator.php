<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Fieldcollection\Definition;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Container comparator for the `fieldcollections` fieldtype (FR-CMP-009). Like the
 * {@see LocalizedFieldsComparator} it holds no scalar value of its own; it recurses, producing one
 * container child {@see FieldDiff} per collection item ("Item #i") whose own children are the
 * per-field diffs of that item, each resolved from the registry.
 *
 * Item matching (v1): items are paired BY INDEX — `left[i]` against `right[i]` for
 * `i in 0..max(count)-1`. An index present on only one side surfaces as `only-left` / `only-right`.
 * Stable-key / identity matching (so a reordered or inserted item does not cascade every later row
 * into a false change) is a known open gap deferred past v1; each item row records
 * `meta.note = 'matched by index (v1)'` so the frontend can flag the caveat.
 *
 * Note on the container guard: the real left/right values are
 * `Pimcore\Model\DataObject\Fieldcollection` instances, and items are
 * `Pimcore\Model\DataObject\Fieldcollection\Data\AbstractData`. Rather than strict `instanceof`,
 * both are duck-typed (`getItems()` on the container, `getType()` / `getValueForFieldName()` on the
 * item) so the comparator works with any object exposing those accessors and stays unit-testable
 * with lightweight doubles; a `null` side simply yields an empty item list.
 */
#[AutoconfigureTag('pimcore.comparison.field_comparator', ['priority' => 40])]
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\AsFeature(id: 'comparators.field-collection', group: 'comparators', name: 'Field collection comparator', description: 'Diffs field collections as expandable nested sections; items matched by index in v1.', status: \Pimcore\Bundle\ComparisonBundle\Feature\FeatureStatus::BETA, openGaps: ['Index-based item matching only in v1; stable-key matching deferred'], specRefs: ['FR-CMP-009'], dependsOn: ['core.comparator-registry'], since: '2026-07-24', backendOnly: true)]
final class FieldCollectionComparator extends AbstractFieldComparator
{
    public function supports(Data $fieldDefinition): bool
    {
        return $fieldDefinition->getFieldtype() === 'fieldcollections';
    }

    public function compare(
        mixed $leftValue,
        mixed $rightValue,
        Data $fieldDefinition,
        ComparisonContext $context,
    ): FieldDiff {
        $leftItems = $this->itemsOf($leftValue);
        $rightItems = $this->itemsOf($rightValue);

        $count = max(count($leftItems), count($rightItems));

        $itemDiffs = [];
        for ($i = 0; $i < $count; $i++) {
            $leftItem = $leftItems[$i] ?? null;
            $rightItem = $rightItems[$i] ?? null;

            $itemDiffs[] = $this->compareItem($fieldDefinition, $leftItem, $rightItem, $i, $context);
        }

        $parentStatus = DiffStatus::EQUAL;
        foreach ($itemDiffs as $itemDiff) {
            if ($itemDiff->status->isDifference()) {
                $parentStatus = DiffStatus::CHANGED;

                break;
            }
        }

        // The container has no scalar value of its own — the item rows carry the content.
        return $this->diff($fieldDefinition, $parentStatus, null, null, null, $itemDiffs);
    }

    /**
     * Build the container "Item #i" row: its children are the per-field diffs, its status is
     * only-left / only-right for a one-sided index, else changed-if-any-child-differs.
     */
    private function compareItem(
        Data $fieldDefinition,
        ?object $leftItem,
        ?object $rightItem,
        int $index,
        ComparisonContext $context,
    ): FieldDiff {
        $type = $this->typeOf($leftItem) ?? $this->typeOf($rightItem);
        $childFds = $this->childFieldDefinitions($type);

        $childDiffs = [];
        foreach ($childFds as $childFd) {
            $childCmp = $context->registry->resolve($childFd);
            if ($childCmp === null) {
                continue;
            }

            $lv = $this->itemValue($leftItem, $childFd->getName());
            $rv = $this->itemValue($rightItem, $childFd->getName());

            $childDiffs[] = $childCmp->compare($lv, $rv, $childFd, $context);
        }

        $status = $this->itemStatus($leftItem, $rightItem, $childDiffs);

        return new FieldDiff(
            $fieldDefinition->getName() . '.' . $index,
            'Item #' . $index,
            'fieldcollectionItem',
            $status,
            null,
            null,
            null,
            $childDiffs,
            ['index' => $index, 'note' => 'matched by index (v1)'],
        );
    }

    /**
     * @param list<FieldDiff> $childDiffs
     */
    private function itemStatus(?object $leftItem, ?object $rightItem, array $childDiffs): DiffStatus
    {
        if ($leftItem !== null && $rightItem === null) {
            return DiffStatus::ONLY_LEFT;
        }
        if ($leftItem === null && $rightItem !== null) {
            return DiffStatus::ONLY_RIGHT;
        }

        foreach ($childDiffs as $childDiff) {
            if ($childDiff->status->isDifference()) {
                return DiffStatus::CHANGED;
            }
        }

        return DiffStatus::EQUAL;
    }

    /**
     * Resolve the per-item field definitions for a collection type. Requires a real definition file,
     * which may be absent (e.g. in a unit test) — a missing/failed lookup yields no child defs rather
     * than erroring, so the item still diffs at the structural (only-side) level.
     *
     * @return array<int|string, Data>
     */
    private function childFieldDefinitions(?string $type): array
    {
        if ($type === null || $type === '') {
            return [];
        }

        try {
            $def = Definition::getByKey($type);
        } catch (\Throwable) {
            return [];
        }

        return $def !== null ? $def->getFieldDefinitions() : [];
    }

    /**
     * The items of a Fieldcollection container. Duck-typed on `getItems()` so a `null` side (or any
     * non-container) yields an empty list rather than erroring.
     *
     * @return list<object>
     */
    private function itemsOf(mixed $container): array
    {
        if (is_object($container) && method_exists($container, 'getItems')) {
            $items = $container->getItems();

            return is_array($items) ? array_values($items) : [];
        }

        return [];
    }

    /** The item's collection type, duck-typed on `getType()`; `null` for a missing/typeless item. */
    private function typeOf(?object $item): ?string
    {
        if ($item !== null && method_exists($item, 'getType')) {
            $type = $item->getType();

            return is_string($type) && $type !== '' ? $type : null;
        }

        return null;
    }

    /**
     * Read one field value off an item. Prefers `getValueForFieldName()`; falls back to a
     * `get<Field>()` getter. A `null` item (missing on this side) reads as `null`.
     */
    private function itemValue(?object $item, string $name): mixed
    {
        if ($item === null) {
            return null;
        }

        if (method_exists($item, 'getValueForFieldName')) {
            return $item->getValueForFieldName($name);
        }

        $getter = 'get' . ucfirst($name);
        if (method_exists($item, $getter)) {
            return $item->{$getter}();
        }

        return null;
    }
}
