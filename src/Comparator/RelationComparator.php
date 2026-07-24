<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Diffs relation fieldtypes (single, many, advanced, reverse) as an ordered set of related elements
 * (FR-CMP-007). Beyond the plain equal/changed/only-side verdict this distinguishes pure ordering
 * changes (DiffStatus::REORDERED) from real add/remove churn, and exposes per-element chips
 * (kept/added/removed/moved) plus add/remove/kept counts in {@see FieldDiff::$meta} for the frontend.
 */
#[AutoconfigureTag('pimcore.comparison.field_comparator', ['priority' => 30])]
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\AsFeature(id: 'comparators.relation', group: 'comparators', name: 'Relation comparator', description: 'Classifies related elements as added / removed / kept / reordered.', status: \Pimcore\Bundle\ComparisonBundle\Feature\FeatureStatus::BETA, specRefs: ['FR-CMP-007'], dependsOn: ['core.comparator-registry'], since: '2026-07-24', backendOnly: true)]
final class RelationComparator extends AbstractFieldComparator
{
    private const RELATION_FIELDTYPES = [
        'manyToOneRelation',
        'manyToManyRelation',
        'manyToManyObjectRelation',
        'advancedManyToManyRelation',
        'advancedManyToManyObjectRelation',
        'reverseObjectRelation',
    ];

    public function supports(Data $fieldDefinition): bool
    {
        return in_array($fieldDefinition->getFieldtype(), self::RELATION_FIELDTYPES, true);
    }

    public function compare(
        mixed $leftValue,
        mixed $rightValue,
        Data $fieldDefinition,
        ComparisonContext $context,
    ): FieldDiff {
        $leftElements = $this->toElements($leftValue);
        $rightElements = $this->toElements($rightValue);

        $leftKeys = array_map(fn (ElementInterface $el): string => $this->elementKey($el), $leftElements);
        $rightKeys = array_map(fn (ElementInterface $el): string => $this->elementKey($el), $rightElements);

        $leftLookup = array_fill_keys($leftKeys, true);
        $rightLookup = array_fill_keys($rightKeys, true);

        /** @var array<string, ElementInterface> $kept */
        $kept = [];
        /** @var array<string, ElementInterface> $removed */
        $removed = [];
        /** @var array<string, ElementInterface> $added */
        $added = [];

        foreach ($leftElements as $i => $el) {
            $key = $leftKeys[$i];
            if (isset($kept[$key]) || isset($removed[$key])) {
                continue;
            }
            if (isset($rightLookup[$key])) {
                $kept[$key] = $el;
            } else {
                $removed[$key] = $el;
            }
        }
        foreach ($rightElements as $i => $el) {
            $key = $rightKeys[$i];
            if (isset($added[$key]) || isset($leftLookup[$key])) {
                continue;
            }
            $added[$key] = $el;
        }

        $leftEmpty = $leftElements === [];
        $rightEmpty = $rightElements === [];
        $sameSet = $added === [] && $removed === [];
        $sameOrder = $leftKeys === $rightKeys;

        if ($leftEmpty && $rightEmpty) {
            $status = DiffStatus::EQUAL;
        } elseif ($leftEmpty) {
            $status = DiffStatus::ONLY_RIGHT;
        } elseif ($rightEmpty) {
            $status = DiffStatus::ONLY_LEFT;
        } elseif ($sameSet && $sameOrder) {
            $status = DiffStatus::EQUAL;
        } elseif ($sameSet) {
            $status = DiffStatus::REORDERED;
        } else {
            $status = DiffStatus::CHANGED;
        }

        $reordered = $status === DiffStatus::REORDERED;

        $chips = [];
        foreach ($kept as $el) {
            $chips[] = ['label' => $this->chipLabel($el), 'state' => $reordered ? 'moved' : 'kept'];
        }
        foreach ($removed as $el) {
            $chips[] = ['label' => $this->chipLabel($el), 'state' => 'removed'];
        }
        foreach ($added as $el) {
            $chips[] = ['label' => $this->chipLabel($el), 'state' => 'added'];
        }

        $meta = [
            'counts' => [
                'added' => count($added),
                'removed' => count($removed),
                'kept' => count($kept),
            ],
            'reordered' => $reordered,
            'chips' => $chips,
        ];

        // Export fallback: left side = kept + removed (its own elements), right = kept + added.
        $leftLabels = array_map(fn (ElementInterface $el): string => $this->chipLabel($el), $leftElements);
        $rightLabels = array_map(fn (ElementInterface $el): string => $this->chipLabel($el), $rightElements);
        $leftDisplay = $leftLabels === [] ? null : implode(', ', $leftLabels);
        $rightDisplay = $rightLabels === [] ? null : implode(', ', $rightLabels);

        return $this->diff($fieldDefinition, $status, $leftDisplay, $rightDisplay, null, [], $meta);
    }

    /**
     * Normalize any relation value to an ordered list of related elements. Accepts null, a single
     * element, an array of elements, or an array of advanced-relation metadata wrappers exposing
     * getElement()/getObject(). Unresolvable entries are skipped.
     *
     * @return list<ElementInterface>
     */
    private function toElements(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if ($value instanceof ElementInterface) {
            return [$value];
        }
        if (!is_array($value)) {
            $element = $this->unwrap($value);

            return $element instanceof ElementInterface ? [$element] : [];
        }

        $elements = [];
        foreach ($value as $entry) {
            $element = $this->unwrap($entry);
            if ($element instanceof ElementInterface) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    /** Resolve a raw relation entry to an element, unwrapping advanced-relation metadata wrappers. */
    private function unwrap(mixed $entry): ?ElementInterface
    {
        if ($entry instanceof ElementInterface) {
            return $entry;
        }
        if (is_object($entry)) {
            if (method_exists($entry, 'getElement')) {
                $element = $entry->getElement();
                if ($element instanceof ElementInterface) {
                    return $element;
                }
            }
            if (method_exists($entry, 'getObject')) {
                $element = $entry->getObject();
                if ($element instanceof ElementInterface) {
                    return $element;
                }
            }
        }

        return null;
    }

    /** A stable identity key for set/order comparison: `type:id`, falling back to spl_object_id. */
    private function elementKey(ElementInterface $element): string
    {
        $id = method_exists($element, 'getId') ? $element->getId() : null;
        if ($id !== null) {
            $type = method_exists($element, 'getType') ? $element->getType() : 'element';

            return $type . ':' . $id;
        }

        return 'spl:' . spl_object_id($element);
    }

    /** A human label for a chip: key, else full path, else `type id`. */
    private function chipLabel(ElementInterface $element): string
    {
        if (method_exists($element, 'getKey')) {
            $key = $element->getKey();
            if (is_string($key) && $key !== '') {
                return $key;
            }
        }
        if (method_exists($element, 'getFullPath')) {
            $path = $element->getFullPath();
            if (is_string($path) && $path !== '') {
                return $path;
            }
        }

        $type = method_exists($element, 'getType') ? (string) $element->getType() : 'element';
        $id = method_exists($element, 'getId') ? (string) $element->getId() : '';

        return trim($type . ' ' . $id);
    }
}
