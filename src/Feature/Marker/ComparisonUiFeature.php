<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Feature\Marker;

use Pimcore\Bundle\ComparisonBundle\Feature\Attribute\AsFeature;
use Pimcore\Bundle\ComparisonBundle\Feature\FeatureStatus;

/**
 * Declaration carrier for the Studio UI feature. The UI itself is a Module-Federation React remote
 * with no single PHP service to hang `#[AsFeature]` on, so this empty marker holds the declaration.
 * `in-progress` until the frontend is covered by a passing Playwright spec (`@feature:ui.comparison-view`).
 */
#[AsFeature(
    id: 'ui.comparison-view',
    group: 'ui',
    name: 'Comparison view + entry points',
    description: 'Studio SDK plugin: grid "Compare objects" action, Compare-with dialog, deep link, diff table + filters.',
    status: FeatureStatus::IN_PROGRESS,
    openGaps: ['Verified loading in Studio + Playwright shell-wiring check; full UI-interaction E2E is future work'],
    specRefs: ['FR-CMP-017', 'FR-CMP-018', 'FR-CMP-019', 'FR-CMP-029', 'FR-CMP-030', 'FR-CMP-031', 'FR-CMP-032'],
    dependsOn: ['api.rest'],
    since: '2026-07-24',
    backendOnly: false,
)]
final class ComparisonUiFeature
{
}
