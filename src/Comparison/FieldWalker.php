<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparison;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Layout;

/**
 * Walks a class LAYOUT definition (not the flat field list) so panels/tabs/regions are preserved as
 * sections. Mirrors core's own extractDataDefinitions recursion, but stops at each Data leaf — a
 * container Data (localizedfields, fieldcollections, objectbricks, classificationstore) is itself a
 * leaf here; its own comparator expands the children.
 *
 * When no layout is defined it falls back to the flat field-definition list.
 */
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\AsFeature(id: 'core.field-walker', group: 'core', name: 'Field walker', description: 'Walks the class layout definition, preserving panels/tabs/regions as sections.', status: \Pimcore\Bundle\ComparisonBundle\Feature\FeatureStatus::IN_PROGRESS, openGaps: ['Exercised via the CLI smoke + live integration; no dedicated PHPUnit yet'], specRefs: ['C-2', 'FR-CMP-002'], since: '2026-07-24', backendOnly: true)]
final class FieldWalker
{
    /**
     * @param list<string> $excludedNames field names to skip (from per-class config)
     *
     * @return list<WalkedField>
     */
    public function walk(ClassDefinition $class, array $excludedNames = []): array
    {
        $root = $class->getLayoutDefinitions();
        $fields = [];

        if ($root instanceof Layout) {
            $this->collect($root, null, $fields);
        }

        if ($fields === []) {
            // No layout (or an empty one): fall back to the flat definition list.
            foreach ($class->getFieldDefinitions() as $definition) {
                $fields[] = new WalkedField($definition, null);
            }
        }

        if ($excludedNames !== []) {
            $fields = array_values(array_filter(
                $fields,
                static fn (WalkedField $f): bool => !in_array($f->definition->getName(), $excludedNames, true),
            ));
        }

        return $fields;
    }

    /**
     * @param list<WalkedField> $out
     */
    private function collect(Layout|Data $node, ?string $section, array &$out): void
    {
        if ($node instanceof Data) {
            $out[] = new WalkedField($node, $section);

            return;
        }

        // A layout container. Use its title (or name) as the section for its descendants, but keep
        // the nearest *named* ancestor so deeply nested unnamed containers inherit a useful label.
        $ownLabel = $this->containerLabel($node);
        $childSection = $ownLabel ?? $section;

        if (!$node->hasChildren()) {
            return;
        }

        foreach ($node->getChildren() as $child) {
            if ($child instanceof Layout || $child instanceof Data) {
                $this->collect($child, $childSection, $out);
            }
        }
    }

    private function containerLabel(Layout $node): ?string
    {
        $title = method_exists($node, 'getTitle') ? trim((string) $node->getTitle()) : '';
        if ($title !== '') {
            return $title;
        }
        $name = trim((string) $node->getName());

        return $name !== '' ? $name : null;
    }
}
