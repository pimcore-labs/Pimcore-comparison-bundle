<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparison;

use Pimcore\Bundle\ComparisonBundle\Comparator\ComparatorRegistry;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparisonContext;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Concrete;

/**
 * Orchestrates a two-object diff (base-idea §6.1/6.2): guard (both Concrete, same class — C-1/C-6),
 * walk the layout, resolve a comparator per field, assemble the DiffResult. Stateless (C-4): it
 * loads objects on demand and persists nothing.
 *
 * Permission masking (C-2) is applied by the caller passing the set of field names the current user
 * may NOT see; those fields are emitted as `hidden` without values. The service itself performs no
 * element-permission check — that is the controller's job, per request.
 */
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\AsFeature(id: 'core.comparison-service', group: 'core', name: 'Comparison service', description: 'Orchestrates a two-object diff: guards (same class, both Concrete), walks the layout, assembles the DiffResult.', status: \Pimcore\Bundle\ComparisonBundle\Feature\FeatureStatus::BETA, specRefs: ['C-1', 'C-5', 'C-6', 'FR-CMP-001', 'FR-CMP-015', 'FR-CMP-016'], since: '2026-07-24', backendOnly: true)]
final class ComparisonService
{
    /**
     * @param array<string, list<string>> $excludedFields per-class excluded field names
     */
    public function __construct(
        private readonly FieldWalker $fieldWalker,
        private readonly ComparatorRegistry $registry,
        private readonly Normalizer $normalizer,
        private readonly array $excludedFields = [],
    ) {
    }

    /**
     * @param array{locales?: list<string>, hiddenFields?: list<string>} $options
     */
    public function compareById(int $leftId, int $rightId, array $options = []): DiffResult
    {
        if ($leftId === $rightId) {
            throw ComparisonException::sameObject($leftId);
        }

        $left = DataObject::getById($leftId);
        if ($left === null) {
            throw ComparisonException::notFound($leftId);
        }
        $right = DataObject::getById($rightId);
        if ($right === null) {
            throw ComparisonException::notFound($rightId);
        }
        if (!$left instanceof Concrete) {
            throw ComparisonException::notConcrete($leftId);
        }
        if (!$right instanceof Concrete) {
            throw ComparisonException::notConcrete($rightId);
        }

        return $this->compare($left, $right, $options);
    }

    /**
     * @param array{locales?: list<string>, hiddenFields?: list<string>} $options
     */
    public function compare(Concrete $left, Concrete $right, array $options = []): DiffResult
    {
        $leftClass = $left->getClass();
        $rightClass = $right->getClass();
        if ($leftClass->getId() !== $rightClass->getId()) {
            throw ComparisonException::classMismatch($leftClass->getName(), $rightClass->getName());
        }

        $context = new ComparisonContext(
            $left,
            $right,
            $options['locales'] ?? [],
            $this->normalizer,
            $this->registry,
        );

        $hidden = array_flip($options['hiddenFields'] ?? []);
        $excluded = $this->excludedFields[$leftClass->getName()] ?? [];

        $fields = [];
        foreach ($this->fieldWalker->walk($leftClass, $excluded) as $walked) {
            $definition = $walked->definition;
            $name = $definition->getName();

            if (isset($hidden[$name])) {
                $fields[] = $this->hiddenDiff($walked);

                continue;
            }

            $fields[] = $this->compareField($walked, $context);
        }

        return new DiffResult(
            (int) $left->getId(),
            (int) $right->getId(),
            $leftClass->getName(),
            $this->attachSections($fields, $this->fieldWalker->walk($leftClass, $excluded)),
        );
    }

    private function compareField(WalkedField $walked, ComparisonContext $context): FieldDiff
    {
        $definition = $walked->definition;
        $comparator = $this->registry->resolve($definition);

        if ($comparator === null) {
            return $this->notComparable($walked, 'No comparator supports this field type.');
        }

        try {
            $leftValue = $context->left->getValueForFieldName($definition->getName());
            $rightValue = $context->right->getValueForFieldName($definition->getName());

            return $comparator->compare($leftValue, $rightValue, $definition, $context);
        } catch (\Throwable $e) {
            return $this->notComparable($walked, $e->getMessage());
        }
    }

    private function hiddenDiff(WalkedField $walked): FieldDiff
    {
        $definition = $walked->definition;

        return new FieldDiff(
            $definition->getName(),
            $this->labelOf($walked),
            $definition->getFieldtype(),
            DiffStatus::HIDDEN,
        );
    }

    private function notComparable(WalkedField $walked, string $reason): FieldDiff
    {
        $definition = $walked->definition;

        return new FieldDiff(
            $definition->getName(),
            $this->labelOf($walked),
            $definition->getFieldtype(),
            DiffStatus::NOT_COMPARABLE,
            null,
            null,
            null,
            [],
            ['reason' => $reason],
        );
    }

    private function labelOf(WalkedField $walked): string
    {
        $title = method_exists($walked->definition, 'getTitle') ? (string) $walked->definition->getTitle() : '';

        return $title !== '' ? $title : $walked->definition->getName();
    }

    /**
     * Fold the section label into each field's meta (rendering groups by it). Sections come from the
     * same ordered walk that produced the fields, so indices line up.
     *
     * @param list<FieldDiff>   $fields
     * @param list<WalkedField> $walked
     *
     * @return list<FieldDiff>
     */
    private function attachSections(array $fields, array $walked): array
    {
        $out = [];
        foreach ($fields as $i => $field) {
            $section = $walked[$i]->section ?? null;
            if ($section === null) {
                $out[] = $field;

                continue;
            }
            $out[] = new FieldDiff(
                $field->name,
                $field->label,
                $field->fieldtype,
                $field->status,
                $field->leftDisplay,
                $field->rightDisplay,
                $field->inlineDiff,
                $field->children,
                ['section' => $section] + $field->meta,
            );
        }

        return $out;
    }
}
