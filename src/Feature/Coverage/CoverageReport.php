<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Feature\Coverage;

/**
 * The ONLY thing the registry trusts about test state: a build artefact of derived evidence —
 * per-feature PHPUnit and Playwright pass/fail counts. If it is absent (a local checkout), every
 * feature reports *unknown*, never *green*: "we have not run the tests" must never render as "the
 * tests pass".
 *
 * @internal
 */
final class CoverageReport
{
    /**
     * @param array<string,array{passed:int,failed:int}> $phpunit    featureId => counts
     * @param array<string,array{passed:int,failed:int}> $playwright featureId => counts
     * @param bool                                        $present    false when no artefact was ingested
     */
    public function __construct(
        private readonly array $phpunit = [],
        private readonly array $playwright = [],
        public readonly bool $present = false,
    ) {
    }

    public static function unknown(): self
    {
        return new self([], [], false);
    }

    /** @param array{phpunit?:array<string,array{passed:int,failed:int}>,playwright?:array<string,array{passed:int,failed:int}>} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['phpunit'] ?? [], $data['playwright'] ?? [], true);
    }

    /** @return array{passed:int,failed:int} */
    public function phpunitFor(string $featureId): array
    {
        return $this->phpunit[$featureId] ?? ['passed' => 0, 'failed' => 0];
    }

    /** @return array{passed:int,failed:int} */
    public function playwrightFor(string $featureId): array
    {
        return $this->playwright[$featureId] ?? ['passed' => 0, 'failed' => 0];
    }

    public function hasPassingPhpunit(string $featureId): bool
    {
        $c = $this->phpunitFor($featureId);

        return $c['passed'] > 0 && $c['failed'] === 0;
    }

    public function hasFailingPhpunit(string $featureId): bool
    {
        return $this->phpunitFor($featureId)['failed'] > 0;
    }

    public function hasPassingPlaywright(string $featureId): bool
    {
        $c = $this->playwrightFor($featureId);

        return $c['passed'] > 0 && $c['failed'] === 0;
    }

    public function hasFailingPlaywright(string $featureId): bool
    {
        return $this->playwrightFor($featureId)['failed'] > 0;
    }
}
