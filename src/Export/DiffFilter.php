<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Export;

use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;

/**
 * Server-side filtering of a diff tree by mode (all / differences / equal) and a free-text field
 * filter. Export uses this so the exported file matches exactly what the filtered view shows
 * (FR-CMP-017, and the "exports the current filtered view" contract in T-SEC-006).
 *
 * A container row is kept when any of its descendants is kept, so structure is preserved.
 */
final class DiffFilter
{
    public const MODE_ALL = 'all';
    public const MODE_DIFFERENCES = 'differences';
    public const MODE_EQUAL = 'equal';

    /**
     * @param list<FieldDiff> $fields
     *
     * @return list<FieldDiff>
     */
    public function apply(array $fields, string $mode = self::MODE_ALL, string $query = ''): array
    {
        $query = trim(mb_strtolower($query));
        $out = [];
        foreach ($fields as $field) {
            $kept = $this->filterNode($field, $mode, $query, false);
            if ($kept !== null) {
                $out[] = $kept;
            }
        }

        return $out;
    }

    private function filterNode(FieldDiff $node, string $mode, string $query, bool $ancestorMatchesQuery): ?FieldDiff
    {
        $selfMatchesQuery = $ancestorMatchesQuery
            || $query === ''
            || str_contains(mb_strtolower($node->label), $query)
            || str_contains(mb_strtolower($node->name), $query);

        if ($node->children !== []) {
            $keptChildren = [];
            foreach ($node->children as $child) {
                $kept = $this->filterNode($child, $mode, $query, $selfMatchesQuery);
                if ($kept !== null) {
                    $keptChildren[] = $kept;
                }
            }
            // Keep a container only if it retains children after filtering.
            return $keptChildren === [] ? null : $node->withChildren($keptChildren);
        }

        // Leaf: apply the mode + query gates.
        if (!$selfMatchesQuery) {
            return null;
        }
        if (!$this->matchesMode($node->status, $mode)) {
            return null;
        }

        return $node;
    }

    private function matchesMode(DiffStatus $status, string $mode): bool
    {
        return match ($mode) {
            self::MODE_DIFFERENCES => $status->isDifference(),
            self::MODE_EQUAL => $status === DiffStatus::EQUAL,
            default => true,
        };
    }
}
