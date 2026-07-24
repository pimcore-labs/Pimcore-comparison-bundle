<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\EqualComparisonInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Last-resort comparator for unknown/custom fieldtypes (FR-CMP-012). It handles every field
 * definition, so it sits at the lowest priority and is only reached when no type-specific comparator
 * matched. Values are rendered with the fielddefinition's own getVersionPreview (via the inherited
 * {@see AbstractFieldComparator::render()}); equality reuses the type's
 * {@see EqualComparisonInterface::isEqual()} when it provides one, otherwise the rendered strings are
 * compared through the normalizer.
 */
#[AutoconfigureTag('pimcore.comparison.field_comparator', ['priority' => -100])]
final class FallbackComparator extends AbstractFieldComparator
{
    public function supports(Data $fieldDefinition): bool
    {
        return true;
    }

    public function compare(
        mixed $leftValue,
        mixed $rightValue,
        Data $fieldDefinition,
        ComparisonContext $context,
    ): FieldDiff {
        $leftDisplay = $this->render($fieldDefinition, $leftValue, $context);
        $rightDisplay = $this->render($fieldDefinition, $rightValue, $context);

        if ($fieldDefinition instanceof EqualComparisonInterface) {
            try {
                $equal = $fieldDefinition->isEqual($leftValue, $rightValue);
            } catch (\Throwable) {
                $equal = $context->normalizer->scalarEquals($leftDisplay, $rightDisplay);
            }
        } else {
            $equal = $context->normalizer->scalarEquals($leftDisplay, $rightDisplay);
        }

        $status = $this->statusFor($leftValue, $rightValue, $equal);

        return $this->diff($fieldDefinition, $status, $leftDisplay, $rightDisplay);
    }
}
