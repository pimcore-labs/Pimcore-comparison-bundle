<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Feature\Attribute;

use Pimcore\Bundle\ComparisonBundle\Feature\FeatureStatus;

/**
 * Declares a product feature ON the class that implements it. Governing principle: declaration is
 * authored, state is computed — you declare what a feature IS (id, group, name, description, status,
 * open gaps, spec refs, deps); whether it is tested is derived from the test reports. Putting the
 * attribute on the implementing class means you cannot declare a feature that has no code, and
 * deleting the code deletes the feature.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsFeature
{
    /**
     * @param string        $id          dot-path id, e.g. `comparators.relation`
     * @param string        $group       one of {@see self::GROUPS}
     * @param string        $name        human label
     * @param string        $description one sentence: what it does, not how
     * @param FeatureStatus $status      declared status (capped by evidence at report time)
     * @param string[]      $openGaps    known, accepted shortfalls; a feature may not be `stable` with any
     * @param string[]      $specRefs    requirement ids this feature implements (`FR-CMP-*`, `C-*`, `T-*`)
     * @param string[]      $dependsOn   other feature ids this one stands on
     * @param string        $since       version/date the feature appeared
     * @param bool          $backendOnly if true, `stable` does not require a Playwright test
     */
    public function __construct(
        public string $id,
        public string $group,
        public string $name,
        public string $description,
        public FeatureStatus $status = FeatureStatus::PLANNED,
        public array $openGaps = [],
        public array $specRefs = [],
        public array $dependsOn = [],
        public string $since = '',
        public bool $backendOnly = false,
    ) {
    }

    /** The declared feature groups. Adding one is a deliberate act — a new area of the bundle. */
    public const GROUPS = [
        'core', 'comparators', 'api', 'ui', 'export', 'security', 'extensibility', 'platform',
    ];
}
