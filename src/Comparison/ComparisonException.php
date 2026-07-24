<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparison;

/**
 * Raised when a comparison cannot proceed: an object is missing, is not Concrete, or the two objects
 * are of different classes (C-1). The controller maps this to a 4xx ProblemDetails response.
 */
final class ComparisonException extends \RuntimeException
{
    public static function notFound(int $id): self
    {
        return new self(sprintf('Data object %d was not found.', $id));
    }

    public static function notConcrete(int $id): self
    {
        return new self(sprintf('Data object %d is not a concrete object and cannot be compared.', $id));
    }

    public static function classMismatch(string $left, string $right): self
    {
        return new self(sprintf('Objects are of different classes (%s vs %s); only same-class comparison is supported.', $left, $right));
    }

    public static function sameObject(int $id): self
    {
        return new self(sprintf('Cannot compare object %d with itself.', $id));
    }
}
