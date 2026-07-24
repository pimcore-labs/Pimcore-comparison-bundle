<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Container comparator for the `localizedfields` fieldtype (FR-CMP-008). It does not diff a scalar
 * value itself; instead it recurses, resolving a child comparator from the registry for each inner
 * field definition and running it once per language, so every (field, locale) pair becomes its own
 * child {@see FieldDiff} row.
 *
 * Per-language sub-rows: each child row's name is `childName.locale`, its label carries a ` [locale]`
 * suffix and its meta records `locale`/`language`. A missing translation on only one side is NOT
 * handled here — the per-locale value is read for both sides and handed to the child (scalar)
 * comparator, which surfaces the gap as `only-left` / `only-right` via its normal empty-side logic.
 *
 * Note on the container guard: the real left/right values are `Pimcore\Model\DataObject\Localizedfield`
 * instances (a `final` class). Rather than a strict `instanceof`, the container is duck-typed via
 * `getLocalizedValue()` so the comparator works with any object exposing that accessor (and so it is
 * unit-testable with a lightweight double); a `null` side simply yields `null` for every locale.
 */
#[AutoconfigureTag('pimcore.comparison.field_comparator', ['priority' => 40])]
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\AsFeature(id: 'comparators.localized', group: 'comparators', name: 'Localized fields comparator', description: 'Diffs localized fields per language as one sub-row per enabled language.', status: \Pimcore\Bundle\ComparisonBundle\Feature\FeatureStatus::BETA, specRefs: ['FR-CMP-008'], dependsOn: ['core.comparator-registry'], since: '2026-07-24', backendOnly: true)]
final class LocalizedFieldsComparator extends AbstractFieldComparator
{
    public function supports(Data $fieldDefinition): bool
    {
        return $fieldDefinition->getFieldtype() === 'localizedfields';
    }

    public function compare(
        mixed $leftValue,
        mixed $rightValue,
        Data $fieldDefinition,
        ComparisonContext $context,
    ): FieldDiff {
        $locales = $context->locales;
        if ($locales === []) {
            try {
                $locales = \Pimcore\Tool::getValidLanguages();
            } catch (\Throwable) {
                $locales = [];
            }
        }

        $children = $fieldDefinition instanceof Localizedfields
            ? $fieldDefinition->getFieldDefinitions()
            : [];

        if ($children === [] || $locales === []) {
            return $this->diff($fieldDefinition, DiffStatus::EQUAL, null, null, null, []);
        }

        $childrenDiffs = [];
        foreach ($locales as $locale) {
            foreach ($children as $childFd) {
                $childCmp = $context->registry->resolve($childFd);
                if ($childCmp === null) {
                    continue;
                }

                $lv = $this->readLocalized($leftValue, $childFd->getName(), $locale);
                $rv = $this->readLocalized($rightValue, $childFd->getName(), $locale);

                $childDiff = $childCmp->compare($lv, $rv, $childFd, $context);

                // Re-wrap so the row is scoped to the locale: qualified name, labelled suffix and
                // locale/language recorded in meta (child meta preserved).
                $childrenDiffs[] = new FieldDiff(
                    $childFd->getName() . '.' . $locale,
                    $childDiff->label . ' [' . $locale . ']',
                    $childDiff->fieldtype,
                    $childDiff->status,
                    $childDiff->leftDisplay,
                    $childDiff->rightDisplay,
                    $childDiff->inlineDiff,
                    $childDiff->children,
                    ['locale' => $locale, 'language' => $locale] + $childDiff->meta,
                );
            }
        }

        $parentStatus = DiffStatus::EQUAL;
        foreach ($childrenDiffs as $c) {
            if ($c->status->isDifference()) {
                $parentStatus = DiffStatus::CHANGED;

                break;
            }
        }

        // The container has no scalar value of its own — the child rows carry the content.
        return $this->diff($fieldDefinition, $parentStatus, null, null, null, $childrenDiffs);
    }

    /**
     * Read a single localized value from a container. Duck-typed on `getLocalizedValue()` so a `null`
     * side (or any non-container) yields `null` for every locale rather than erroring.
     */
    private function readLocalized(mixed $container, string $name, string $locale): mixed
    {
        if (is_object($container) && method_exists($container, 'getLocalizedValue')) {
            return $container->getLocalizedValue($name, $locale);
        }

        return null;
    }
}
