<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Feature;

use Pimcore\Bundle\ComparisonBundle\Feature\Attribute\AsFeature;
use Pimcore\Bundle\ComparisonBundle\Feature\Coverage\CoverageReport;

/**
 * The compiled feature registry. Declarations come from `#[AsFeature]` scanned into the container at
 * build time; state is *computed* here by capping each declared status with the test evidence and the
 * dependency graph. Nothing about test state is authored.
 *
 * @internal
 */
#[AsFeature(
    id: 'platform.feature-registry',
    group: 'platform',
    name: 'Feature registry discipline',
    description: 'Declaration-is-authored / state-is-computed registry that gates status on test evidence.',
    status: FeatureStatus::BETA,
    specRefs: ['T-ARCH-002', 'T-FEAT-001', 'T-FEAT-003', 'T-FEAT-006'],
    since: '2026-07-24',
    backendOnly: true,
)]
final class FeatureRegistry
{
    /** @var array<string,array<string,mixed>> id => declaration */
    private array $byId = [];

    /** @param list<array<string,mixed>> $declarations from the compiler pass */
    public function __construct(array $declarations = [])
    {
        foreach ($declarations as $d) {
            $this->byId[$d['id']] = $d;
        }
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        return $this->byId;
    }

    /** @return string[] every spec-requirement id claimed by some feature (T-FEAT-006) */
    public function claimedSpecRefs(): array
    {
        $refs = [];
        foreach ($this->byId as $d) {
            foreach ($d['specRefs'] as $r) {
                $refs[$r] = true;
            }
        }

        return array_keys($refs);
    }

    /**
     * Resolve every feature against the evidence: declared status, the *effective* status (declared
     * capped by evidence + deps), whether it overclaims, and the evidence counts.
     *
     * @return array<string,array{decl:array<string,mixed>,declared:FeatureStatus,effective:FeatureStatus,overclaim:bool,unknown:bool,phpunit:array{passed:int,failed:int},playwright:array{passed:int,failed:int}}>
     */
    public function resolve(CoverageReport $coverage): array
    {
        $effective = [];
        foreach ($this->byId as $id => $d) {
            $declared = FeatureStatus::from($d['status']);
            $effective[$id] = $this->supportedIgnoringDeps($declared, $d, $coverage);
        }

        // Dependency fixpoint: no feature may be more mature than what it stands on (T-FEAT-003).
        do {
            $changed = false;
            foreach ($this->byId as $id => $d) {
                if ($effective[$id] === FeatureStatus::STABLE) {
                    foreach ($d['dependsOn'] as $depId) {
                        $depStatus = $effective[$depId] ?? FeatureStatus::PLANNED;
                        if ($depStatus !== FeatureStatus::STABLE) {
                            $effective[$id] = FeatureStatus::BETA;
                            $changed = true;
                            break;
                        }
                    }
                }
            }
        } while ($changed);

        $rows = [];
        foreach ($this->byId as $id => $d) {
            $declared = FeatureStatus::from($d['status']);
            $rows[$id] = [
                'decl' => $d,
                'declared' => $declared,
                'effective' => $effective[$id],
                'overclaim' => $declared->rank() > $effective[$id]->rank(),
                'unknown' => !$coverage->present && \in_array($declared, [FeatureStatus::BETA, FeatureStatus::STABLE], true),
                'phpunit' => $coverage->phpunitFor($id),
                'playwright' => $coverage->playwrightFor($id),
            ];
        }

        return $rows;
    }

    /** The highest status the evidence supports for one feature, ignoring its dependencies. */
    private function supportedIgnoringDeps(FeatureStatus $declared, array $decl, CoverageReport $coverage): FeatureStatus
    {
        if (\in_array($declared, [FeatureStatus::PLANNED, FeatureStatus::IN_PROGRESS, FeatureStatus::DEPRECATED], true)) {
            return $declared;
        }

        $phpunitOk = $coverage->hasPassingPhpunit($decl['id']);

        if ($declared === FeatureStatus::BETA) {
            return $phpunitOk ? FeatureStatus::BETA : FeatureStatus::IN_PROGRESS;
        }

        // STABLE
        $playwrightOk = $decl['backendOnly'] || $coverage->hasPassingPlaywright($decl['id']);
        $gapsOk = $decl['openGaps'] === [];
        if ($phpunitOk && $playwrightOk && $gapsOk) {
            return FeatureStatus::STABLE;
        }

        return $phpunitOk ? FeatureStatus::BETA : FeatureStatus::IN_PROGRESS;
    }

    /**
     * Build-blocking violations (T-FEAT-001 overclaim, T-FEAT-003 dep maturity, missing deps,
     * deprecated without replacement). Each names the FEATURE, not the assertion.
     *
     * @return string[]
     */
    public function violations(CoverageReport $coverage): array
    {
        $rows = $this->resolve($coverage);
        $out = [];
        foreach ($rows as $id => $row) {
            /** @var array<string,mixed> $d */
            $d = $row['decl'];
            if ($row['overclaim']) {
                $reason = $row['unknown'] ? 'no coverage report ingested (unknown, not green)' : $this->overclaimReason($d, $coverage);
                $out[] = sprintf('%s (%s): claims %s but evidence supports only %s — %s',
                    $id, $d['declaredIn'] ?? '?', $row['declared']->value, $row['effective']->value, $reason);
            }
            foreach ($d['dependsOn'] as $depId) {
                if (!isset($this->byId[$depId])) {
                    $out[] = sprintf('%s: dependsOn unknown feature "%s"', $id, $depId);
                }
            }
            if ($row['declared'] === FeatureStatus::DEPRECATED && $d['openGaps'] === []) {
                $out[] = sprintf('%s: deprecated without a replacement stated in openGaps', $id);
            }
        }

        return $out;
    }

    private function overclaimReason(array $d, CoverageReport $coverage): string
    {
        $reasons = [];
        if ($coverage->hasFailingPhpunit($d['id'])) {
            $reasons[] = sprintf('%d failing PHPUnit test(s)', $coverage->phpunitFor($d['id'])['failed']);
        } elseif (!$coverage->hasPassingPhpunit($d['id'])) {
            $reasons[] = 'no passing PHPUnit test';
        }
        if ($d['status'] === FeatureStatus::STABLE->value) {
            if (!$d['backendOnly'] && !$coverage->hasPassingPlaywright($d['id'])) {
                $reasons[] = 'no passing Playwright test';
            }
            if ($d['openGaps'] !== []) {
                $reasons[] = sprintf('%d open gap(s)', count($d['openGaps']));
            }
        }

        return $reasons === [] ? 'a dependency is not stable' : implode('; ', $reasons);
    }
}
