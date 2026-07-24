<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparison;

use Pimcore\Model\DataObject\ClassDefinition\Data;

/**
 * A Data leaf discovered by the {@see FieldWalker}, tagged with the layout section (panel/tab title)
 * it lives under so the diff table can group rows the way the class layout does.
 */
final class WalkedField
{
    public function __construct(
        public readonly Data $definition,
        public readonly ?string $section,
    ) {
    }
}
