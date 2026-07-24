<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Diff;

/**
 * The per-row diff status (base-idea §5.3). `changed`/`equal`/`only-left`/`only-right` are the
 * common cases; `reordered` is relation ordering noise; `not-comparable` is a value that errored
 * (e.g. a calculated field); `hidden` is a field the user may not see (permission-masked, no value).
 */
enum DiffStatus: string
{
    case EQUAL = 'equal';
    case CHANGED = 'changed';
    case ONLY_LEFT = 'only-left';
    case ONLY_RIGHT = 'only-right';
    case REORDERED = 'reordered';
    case NOT_COMPARABLE = 'not-comparable';
    case HIDDEN = 'hidden';

    public function isDifference(): bool
    {
        return $this !== self::EQUAL && $this !== self::HIDDEN;
    }

    /** Swap sides: only-left ⇄ only-right; everything else is side-symmetric. */
    public function swapped(): self
    {
        return match ($this) {
            self::ONLY_LEFT => self::ONLY_RIGHT,
            self::ONLY_RIGHT => self::ONLY_LEFT,
            default => $this,
        };
    }
}
