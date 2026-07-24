<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Command;

use Pimcore\Bundle\ComparisonBundle\Comparison\ComparisonException;
use Pimcore\Bundle\ComparisonBundle\Comparison\ComparisonService;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Bundle\ComparisonBundle\Export\DiffFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI front end to the comparison engine: diff two objects and print the result. Handy for dedup
 * pipelines and as a headless smoke of the full backend on real data.
 */
#[AsCommand(name: 'comparison:diff', description: 'Diff two data objects of the same class')]
final class ComparisonDiffCommand extends Command
{
    public function __construct(
        private readonly ComparisonService $comparisonService,
        private readonly DiffFilter $filter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('leftId', InputArgument::REQUIRED, 'Left object id')
            ->addArgument('rightId', InputArgument::REQUIRED, 'Right object id')
            ->addOption('locales', null, InputOption::VALUE_REQUIRED, 'Comma-separated locales for localized fields')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'all | differences | equal', 'differences');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locales = array_values(array_filter(array_map('trim', explode(',', (string) $input->getOption('locales')))));

        try {
            $result = $this->comparisonService->compareById(
                (int) $input->getArgument('leftId'),
                (int) $input->getArgument('rightId'),
                ['locales' => $locales],
            );
        } catch (ComparisonException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->title(sprintf('Comparing %s #%d ⇄ #%d', $result->className, $result->leftId, $result->rightId));

        $counts = $result->counts();
        $io->writeln(sprintf(
            '<info>%d of %d fields differ</info> — %d changed, %d only-left, %d only-right, %d reordered, %d equal, %d hidden, %d not-comparable',
            $result->differing(),
            $result->total(),
            $counts[DiffStatus::CHANGED->value],
            $counts[DiffStatus::ONLY_LEFT->value],
            $counts[DiffStatus::ONLY_RIGHT->value],
            $counts[DiffStatus::REORDERED->value],
            $counts[DiffStatus::EQUAL->value],
            $counts[DiffStatus::HIDDEN->value],
            $counts[DiffStatus::NOT_COMPARABLE->value],
        ));
        $io->newLine();

        $filtered = $this->filter->apply($result->fields, (string) $input->getOption('filter'));
        $rows = [];
        $this->rows($filtered, $rows);
        if ($rows === []) {
            $io->writeln('<comment>No rows match the current filter.</comment>');
        } else {
            $io->table(['Field', 'Left', 'Right', 'Status'], $rows);
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<FieldDiff>                                       $fields
     * @param list<array{string, string, string, string}>          $rows
     */
    private function rows(array $fields, array &$rows, string $prefix = ''): void
    {
        foreach ($fields as $field) {
            $label = $prefix === '' ? $field->label : $prefix . ' › ' . $field->label;
            if ($field->children !== []) {
                $this->rows($field->children, $rows, $label);

                continue;
            }
            $rows[] = [
                $label,
                $this->trunc($field->leftDisplay),
                $this->trunc($field->rightDisplay),
                $field->status->value,
            ];
        }
    }

    private function trunc(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        $s = is_scalar($value) ? (string) $value : (string) json_encode($value);

        return mb_strlen($s) > 48 ? mb_substr($s, 0, 45) . '…' : $s;
    }
}
