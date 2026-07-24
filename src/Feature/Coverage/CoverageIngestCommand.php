<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Feature\Coverage;

use Pimcore\Bundle\ComparisonBundle\Feature\Attribute\CoversFeature;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Ingests the test reports into the single coverage artefact the registry trusts. PHPUnit tests
 * declare what they cover with `#[CoversFeature]`; this reads those + the JUnit report to derive
 * per-feature pass/fail, and reads the Playwright JSON for `@feature:<id>`-tagged specs. Writes
 * `var/comparison-feature-coverage.json`.
 */
#[AsCommand(
    name: 'comparison:features:ingest',
    description: 'Ingest JUnit + Playwright reports into the feature coverage artefact.'
)]
final class CoverageIngestCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('junit', null, InputOption::VALUE_REQUIRED, 'Path to the PHPUnit JUnit XML')
            ->addOption('playwright', null, InputOption::VALUE_REQUIRED, 'Path to the Playwright JSON report')
            ->addOption('tests-dir', null, InputOption::VALUE_REQUIRED, 'Directory scanned for #[CoversFeature]', 'bundles/PimcoreComparisonBundle/tests')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output coverage.json path', 'var/comparison-feature-coverage.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        [$classFeature, $methodFeature] = $this->scanCoversFeature((string) $input->getOption('tests-dir'));
        $io->writeln(sprintf('Mapped %d class + %d method #[CoversFeature] declarations.', count($classFeature), count($methodFeature)));

        $phpunit = [];
        if (($junit = $input->getOption('junit')) && is_file((string) $junit)) {
            $phpunit = $this->parseJUnit((string) $junit, $classFeature, $methodFeature);
        }

        $playwright = [];
        if (($pw = $input->getOption('playwright')) && is_file((string) $pw)) {
            $playwright = $this->parsePlaywright((string) $pw);
        }

        $out = (string) $input->getOption('out');
        @mkdir(dirname($out), 0777, true);
        file_put_contents($out, (string) json_encode(['phpunit' => $phpunit, 'playwright' => $playwright], JSON_PRETTY_PRINT));

        $io->success(sprintf('Wrote %s — %d feature(s) with PHPUnit evidence, %d with Playwright.', $out, count($phpunit), count($playwright)));

        return Command::SUCCESS;
    }

    /**
     * @return array{0:array<string,string>,1:array<string,string>} [class=>id, "class::method"=>id]
     */
    private function scanCoversFeature(string $testsDir): array
    {
        $classFeature = [];
        $methodFeature = [];
        if (!is_dir($testsDir)) {
            return [$classFeature, $methodFeature];
        }

        foreach (Finder::create()->files()->in($testsDir)->name('*.php') as $file) {
            $before = get_declared_classes();
            try {
                require_once $file->getRealPath();
            } catch (\Throwable) {
                continue;
            }
            foreach (array_diff(get_declared_classes(), $before) as $class) {
                $ref = new \ReflectionClass($class);
                foreach ($ref->getAttributes(CoversFeature::class) as $attr) {
                    $classFeature[$class] = $attr->newInstance()->id;
                }
                foreach ($ref->getMethods() as $method) {
                    foreach ($method->getAttributes(CoversFeature::class) as $attr) {
                        $methodFeature[$class . '::' . $method->getName()] = $attr->newInstance()->id;
                    }
                }
            }
        }

        return [$classFeature, $methodFeature];
    }

    /**
     * @param array<string,string> $classFeature
     * @param array<string,string> $methodFeature
     *
     * @return array<string,array{passed:int,failed:int}>
     */
    private function parseJUnit(string $path, array $classFeature, array $methodFeature): array
    {
        $counts = [];
        $xml = @simplexml_load_file($path);
        if ($xml === false) {
            return $counts;
        }
        foreach ($xml->xpath('//testcase') ?: [] as $case) {
            $class = (string) ($case['class'] ?? $case['classname'] ?? '');
            $name = preg_replace('/ with data set .*$/', '', (string) ($case['name'] ?? '')) ?? '';
            $featureId = $methodFeature[$class . '::' . $name] ?? $classFeature[$class] ?? null;
            if ($featureId === null) {
                continue;
            }
            $counts[$featureId] ??= ['passed' => 0, 'failed' => 0];
            $failed = isset($case->failure) || isset($case->error);
            $counts[$featureId][$failed ? 'failed' : 'passed']++;
        }

        return $counts;
    }

    /** @return array<string,array{passed:int,failed:int}> */
    private function parsePlaywright(string $path): array
    {
        $counts = [];
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return $counts;
        }
        $walk = function ($node) use (&$walk, &$counts): void {
            if (!is_array($node)) {
                return;
            }
            if (isset($node['title']) && preg_match('/@feature:([\w.\-]+)/', (string) $node['title'], $m)) {
                $id = $m[1];
                $counts[$id] ??= ['passed' => 0, 'failed' => 0];
                $ok = ($node['ok'] ?? null) === true
                    || (isset($node['tests']) && $this->specPassed($node['tests']));
                $counts[$id][$ok ? 'passed' : 'failed']++;
            }
            foreach (['suites', 'specs', 'tests'] as $key) {
                foreach ($node[$key] ?? [] as $child) {
                    $walk($child);
                }
            }
        };
        $walk($data);

        return $counts;
    }

    private function specPassed(array $tests): bool
    {
        foreach ($tests as $t) {
            foreach ($t['results'] ?? [] as $r) {
                if (($r['status'] ?? '') !== 'passed' && ($r['status'] ?? '') !== 'expected') {
                    return false;
                }
            }
        }

        return true;
    }
}
