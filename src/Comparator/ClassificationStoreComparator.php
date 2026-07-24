<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Container comparator for the `classificationstore` fieldtype (FR-CMP-011). Pimcore's
 * Classificationstore has no core diff-preview override, so this comparator walks it itself: a
 * Classificationstore value holds group → key → value entries, per language, and each (group, key,
 * language) triple becomes its own leaf {@see FieldDiff} grouped under a per-group container row.
 *
 * Note on the container guard: the real left/right values are `Pimcore\Model\DataObject\Classificationstore`
 * containers (a class that may be `final`). Rather than a strict `instanceof`, every container access is
 * duck-typed and guarded by `method_exists`, so the comparator works with any object exposing the
 * accessors and stays unit-testable with a lightweight double; a `null` side simply yields an empty map.
 *
 * Enumerating the keys of a group normally needs the classification-store config service (not available
 * in a pure unit), so the comparator drives its walk off the container's own `getItems()` map — a nested
 * `[groupId => [keyId => [language => value]]]` structure — which a test may pre-supply. `getActiveGroups()`
 * (groupId => bool) and the per-key `getLocalizedKeyValue()` accessor are also duck-typed for the real
 * container path.
 */
#[AutoconfigureTag('pimcore.comparison.field_comparator', ['priority' => 40])]
final class ClassificationStoreComparator extends AbstractFieldComparator
{
    public function supports(Data $fieldDefinition): bool
    {
        return $fieldDefinition->getFieldtype() === 'classificationstore';
    }

    public function compare(
        mixed $leftValue,
        mixed $rightValue,
        Data $fieldDefinition,
        ComparisonContext $context,
    ): FieldDiff {
        $leftItems = $this->itemsOf($leftValue);
        $rightItems = $this->itemsOf($rightValue);

        $groupIds = $this->union(
            array_keys($leftItems),
            array_keys($rightItems),
            array_keys(array_filter($this->activeGroups($leftValue))),
            array_keys(array_filter($this->activeGroups($rightValue))),
        );

        $groupDiffs = [];
        foreach ($groupIds as $groupId) {
            $leftGroup = $this->asArray($leftItems[$groupId] ?? null);
            $rightGroup = $this->asArray($rightItems[$groupId] ?? null);

            $groupDiffs[] = $this->compareGroup(
                $fieldDefinition,
                (int) $groupId,
                $leftGroup,
                $rightGroup,
                $context,
            );
        }

        $parentStatus = DiffStatus::EQUAL;
        foreach ($groupDiffs as $groupDiff) {
            if ($groupDiff->status->isDifference()) {
                $parentStatus = DiffStatus::CHANGED;

                break;
            }
        }

        // The container has no scalar value of its own — the group/key rows carry the content.
        return $this->diff($fieldDefinition, $parentStatus, null, null, null, $groupDiffs);
    }

    /**
     * Build the per-group container row ("Group $groupId") whose children are the (key, language) leaves.
     *
     * @param array<int|string, mixed> $leftGroup  [keyId => [language => value]]
     * @param array<int|string, mixed> $rightGroup [keyId => [language => value]]
     */
    private function compareGroup(
        Data $fieldDefinition,
        int $groupId,
        array $leftGroup,
        array $rightGroup,
        ComparisonContext $context,
    ): FieldDiff {
        $keyIds = $this->union(array_keys($leftGroup), array_keys($rightGroup));

        $leaves = [];
        foreach ($keyIds as $keyId) {
            $leftKey = $this->asArray($leftGroup[$keyId] ?? null);
            $rightKey = $this->asArray($rightGroup[$keyId] ?? null);

            $languages = $this->union(array_keys($leftKey), array_keys($rightKey));
            foreach ($languages as $language) {
                $leftVal = $leftKey[$language] ?? null;
                $rightVal = $rightKey[$language] ?? null;

                $equal = $context->normalizer->scalarEquals($leftVal, $rightVal);
                $status = $this->statusFor($leftVal, $rightVal, $equal);

                $leaves[] = $this->leaf($fieldDefinition, $groupId, $keyId, $language, $leftVal, $rightVal, $status);
            }
        }

        $groupStatus = DiffStatus::EQUAL;
        foreach ($leaves as $leaf) {
            if ($leaf->status->isDifference()) {
                $groupStatus = DiffStatus::CHANGED;

                break;
            }
        }

        return new FieldDiff(
            $fieldDefinition->getName() . '.' . $groupId,
            'Group ' . $groupId,
            'classificationstoreGroup',
            $groupStatus,
            null,
            null,
            null,
            $leaves,
            ['group' => $groupId],
        );
    }

    /**
     * Build a single (group, key, language) leaf row.
     */
    private function leaf(
        Data $fieldDefinition,
        int $groupId,
        int|string $keyId,
        int|string|null $language,
        mixed $leftVal,
        mixed $rightVal,
        DiffStatus $status,
    ): FieldDiff {
        $lang = ($language === null || $language === '') ? null : (string) $language;
        $suffix = $lang !== null ? '.' . $lang : '';
        $labelSuffix = $lang !== null ? ' [' . $lang . ']' : '';

        return new FieldDiff(
            $fieldDefinition->getName() . '.' . $groupId . '.' . $keyId . $suffix,
            'Group ' . $groupId . ' / Key ' . $keyId . $labelSuffix,
            'classificationstoreKey',
            $status,
            $this->stringify($leftVal),
            $this->stringify($rightVal),
            null,
            [],
            ['group' => $groupId, 'key' => $keyId, 'language' => $lang],
        );
    }

    /**
     * The nested item map of a Classificationstore container. Duck-typed on `getItems()` so a `null`
     * side (or any non-container) yields an empty map rather than erroring.
     *
     * @return array<int|string, mixed> [groupId => [keyId => [language => value]]]
     */
    private function itemsOf(mixed $container): array
    {
        if (is_object($container) && method_exists($container, 'getItems')) {
            $items = $container->getItems();

            return is_array($items) ? $items : [];
        }

        return [];
    }

    /**
     * The active groups of a Classificationstore container (groupId => bool). Duck-typed on
     * `getActiveGroups()`; `[]` for a `null` side (or any non-container).
     *
     * @return array<int, bool>
     */
    private function activeGroups(mixed $container): array
    {
        if (is_object($container) && method_exists($container, 'getActiveGroups')) {
            $groups = $container->getActiveGroups();

            return is_array($groups) ? $groups : [];
        }

        return [];
    }

    /**
     * Read a single localized key value off the real container. Duck-typed on `getLocalizedKeyValue()`;
     * the signature varies across Pimcore versions, so the call is wrapped in try/catch and returns
     * `null` on any failure. (Not used by the items-map walk; kept for the real-container read path.)
     */
    private function groupValue(mixed $container, int $groupId, int|string $keyId, ?string $language): mixed
    {
        if (!is_object($container) || !method_exists($container, 'getLocalizedKeyValue')) {
            return null;
        }

        try {
            return $container->getLocalizedKeyValue($groupId, $keyId, $language ?? 'default', true, true);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Render a scalar/label value to a display string; `null` stays `null`.
     */
    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param mixed $value
     *
     * @return array<int|string, mixed>
     */
    private function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Sorted, de-duplicated union of one or more key lists.
     *
     * @param array<int, int|string> ...$lists
     *
     * @return list<int|string>
     */
    private function union(array ...$lists): array
    {
        $merged = array_merge([], ...$lists);
        $unique = array_values(array_unique($merged));
        sort($unique);

        return $unique;
    }
}
