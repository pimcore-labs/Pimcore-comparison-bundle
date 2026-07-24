<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Tests\Unit;

use Pimcore\Bundle\ComparisonBundle\Feature\Attribute\CoversFeature;
use Pimcore\Bundle\ComparisonBundle\Feature\Coverage\CoverageReport;
use Pimcore\Bundle\ComparisonBundle\Feature\FeatureRegistry;
use Pimcore\Bundle\ComparisonBundle\Feature\FeatureStatus;
use PHPUnit\Framework\TestCase;

/**
 * The declaration-is-authored / state-is-computed core (T-FEAT-001/003/006): the registry caps a
 * declared status by the evidence and the dependency graph, and flags overclaims. Pure logic.
 */
#[CoversFeature('platform.feature-registry')]
final class FeatureRegistryTest extends TestCase
{
    /**
     * @param array<string,mixed> $overrides
     *
     * @return array<string,mixed>
     */
    private function decl(string $id, string $status, array $overrides = []): array
    {
        return array_merge([
            'id' => $id, 'group' => 'core', 'name' => $id, 'description' => '',
            'status' => $status, 'openGaps' => [], 'specRefs' => [], 'dependsOn' => [],
            'since' => '2026-07-24', 'backendOnly' => true, 'declaredIn' => 'X',
        ], $overrides);
    }

    public function testPlannedFeatureCarriesNoEvidenceObligation(): void
    {
        $reg = new FeatureRegistry([$this->decl('a', 'planned')]);
        $row = $reg->resolve(CoverageReport::unknown())['a'];
        self::assertSame(FeatureStatus::PLANNED, $row['effective']);
        self::assertFalse($row['overclaim']);
    }

    public function testBetaNeedsPassingPhpunit(): void
    {
        $withEvidence = CoverageReport::fromArray(['phpunit' => ['a' => ['passed' => 3, 'failed' => 0]]]);
        $reg = new FeatureRegistry([$this->decl('a', 'beta')]);
        self::assertSame(FeatureStatus::BETA, $reg->resolve($withEvidence)['a']['effective']);

        // No coverage ingested → unknown, capped to in-progress, and it's an overclaim.
        $row = $reg->resolve(CoverageReport::unknown())['a'];
        self::assertSame(FeatureStatus::IN_PROGRESS, $row['effective']);
        self::assertTrue($row['overclaim']);
        self::assertTrue($row['unknown']);
    }

    public function testFailingPhpunitBlocksBeta(): void
    {
        $failing = CoverageReport::fromArray(['phpunit' => ['a' => ['passed' => 2, 'failed' => 1]]]);
        $reg = new FeatureRegistry([$this->decl('a', 'beta')]);
        $row = $reg->resolve($failing)['a'];
        self::assertSame(FeatureStatus::IN_PROGRESS, $row['effective']);
        self::assertTrue($row['overclaim']);
    }

    public function testStableNeedsPhpunitPlaywrightAndNoGaps(): void
    {
        // backendOnly stable with passing phpunit and no gaps → stable.
        $cov = CoverageReport::fromArray(['phpunit' => ['a' => ['passed' => 1, 'failed' => 0]]]);
        $reg = new FeatureRegistry([$this->decl('a', 'stable', ['backendOnly' => true])]);
        self::assertSame(FeatureStatus::STABLE, $reg->resolve($cov)['a']['effective']);

        // An open gap blocks stable (demoted to beta).
        $reg2 = new FeatureRegistry([$this->decl('a', 'stable', ['backendOnly' => true, 'openGaps' => ['x']])]);
        self::assertSame(FeatureStatus::BETA, $reg2->resolve($cov)['a']['effective']);

        // Not backendOnly and no playwright → demoted to beta.
        $reg3 = new FeatureRegistry([$this->decl('a', 'stable', ['backendOnly' => false])]);
        self::assertSame(FeatureStatus::BETA, $reg3->resolve($cov)['a']['effective']);
    }

    public function testDependencyFixpointDemotesStableStandingOnNonStable(): void
    {
        $cov = CoverageReport::fromArray(['phpunit' => [
            'a' => ['passed' => 1, 'failed' => 0],
            'b' => ['passed' => 1, 'failed' => 0],
        ]]);
        $reg = new FeatureRegistry([
            $this->decl('b', 'beta', ['backendOnly' => true]),                      // stays beta
            $this->decl('a', 'stable', ['backendOnly' => true, 'dependsOn' => ['b']]), // stable on beta → demoted
        ]);
        $rows = $reg->resolve($cov);
        self::assertSame(FeatureStatus::BETA, $rows['b']['effective']);
        self::assertSame(FeatureStatus::BETA, $rows['a']['effective'], 'no feature outranks what it stands on');
    }

    public function testClaimedSpecRefsAndViolations(): void
    {
        $reg = new FeatureRegistry([
            $this->decl('a', 'beta', ['specRefs' => ['FR-CMP-001', 'C-1'], 'dependsOn' => ['ghost']]),
        ]);
        self::assertEqualsCanonicalizing(['FR-CMP-001', 'C-1'], $reg->claimedSpecRefs());

        $violations = $reg->violations(CoverageReport::unknown());
        self::assertNotEmpty($violations);
        self::assertStringContainsString('dependsOn unknown feature "ghost"', implode("\n", $violations));
    }
}
