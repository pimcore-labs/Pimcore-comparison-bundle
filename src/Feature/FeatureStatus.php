<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Feature;

/**
 * The declared maturity of a feature. The *effective* status is this, capped by the evidence:
 * `beta` needs a passing PHPUnit test; `stable` also needs a passing Playwright test (unless
 * backend-only), zero open gaps, and every dependency ≥ `stable`.
 */
enum FeatureStatus: string
{
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in-progress';
    case BETA = 'beta';
    case STABLE = 'stable';
    case DEPRECATED = 'deprecated';

    /** Ordering for the `dependsOn` maturity gate (T-FEAT-003). Deprecated sits with planned. */
    public function rank(): int
    {
        return match ($this) {
            self::PLANNED, self::DEPRECATED => 0,
            self::IN_PROGRESS => 1,
            self::BETA => 2,
            self::STABLE => 3,
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }
}
