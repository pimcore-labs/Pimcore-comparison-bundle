<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Diffs scalar fieldtypes (input, number, select, checkbox, date/time, and the enum-ish selects)
 * with normalization-aware equality. Priority sits below the type-specific comparators (relations,
 * text, containers) and above the fallback.
 */
#[AutoconfigureTag('pimcore.comparison.field_comparator', ['priority' => 10])]
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\AsFeature(id: 'comparators.scalar', group: 'comparators', name: 'Scalar comparator', description: 'Diffs scalar fieldtypes (input, number, select, checkbox, date) with normalization-aware equality.', status: \Pimcore\Bundle\ComparisonBundle\Feature\FeatureStatus::BETA, specRefs: ['FR-CMP-004'], dependsOn: ['core.comparator-registry'], since: '2026-07-24', backendOnly: true)]
final class ScalarComparator extends AbstractFieldComparator
{
    private const SCALAR_FIELDTYPES = [
        'input', 'numeric', 'slider', 'select', 'checkbox', 'date', 'datetime', 'time',
        'country', 'language', 'gender', 'firstname', 'lastname', 'email', 'booleanSelect',
        'inputQuantityValue', 'urlSlug', 'consent',
    ];

    public function supports(Data $fieldDefinition): bool
    {
        return in_array($fieldDefinition->getFieldtype(), self::SCALAR_FIELDTYPES, true);
    }

    public function compare(
        mixed $leftValue,
        mixed $rightValue,
        Data $fieldDefinition,
        ComparisonContext $context,
    ): FieldDiff {
        $equal = $context->normalizer->scalarEquals($leftValue, $rightValue);
        $status = $this->statusFor($leftValue, $rightValue, $equal);

        return $this->diff(
            $fieldDefinition,
            $status,
            $this->render($fieldDefinition, $leftValue, $context),
            $this->render($fieldDefinition, $rightValue, $context),
        );
    }
}
