<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparison;

use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;

/**
 * The full result of comparing two objects: the ordered field diffs plus rolled-up counts for the
 * summary bar. Stateless and JSON-serializable — this IS the API payload.
 */
final class DiffResult implements \JsonSerializable
{
    /**
     * @param list<FieldDiff> $fields
     */
    public function __construct(
        public readonly int $leftId,
        public readonly int $rightId,
        public readonly string $className,
        public readonly array $fields,
    ) {
    }

    /** @return array<string, int> status.value => count (deep, includes children) */
    public function counts(): array
    {
        $counts = array_fill_keys(array_map(static fn (DiffStatus $s): string => $s->value, DiffStatus::cases()), 0);
        $walk = static function (array $fields) use (&$walk, &$counts): void {
            foreach ($fields as $f) {
                /** @var FieldDiff $f */
                if ($f->children === []) {
                    ++$counts[$f->status->value];
                } else {
                    $walk($f->children);
                }
            }
        };
        $walk($this->fields);

        return $counts;
    }

    public function differing(): int
    {
        $counts = $this->counts();

        return $counts[DiffStatus::CHANGED->value]
            + $counts[DiffStatus::ONLY_LEFT->value]
            + $counts[DiffStatus::ONLY_RIGHT->value]
            + $counts[DiffStatus::REORDERED->value];
    }

    public function total(): int
    {
        return array_sum($this->counts());
    }

    public function jsonSerialize(): array
    {
        return [
            'leftId' => $this->leftId,
            'rightId' => $this->rightId,
            'className' => $this->className,
            'fields' => $this->fields,
            'summary' => [
                'total' => $this->total(),
                'differing' => $this->differing(),
                'counts' => $this->counts(),
            ],
        ];
    }
}
