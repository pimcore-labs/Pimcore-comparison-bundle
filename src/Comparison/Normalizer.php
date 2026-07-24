<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparison;

/**
 * False-positive avoidance (base-idea §6.2). Decides scalar equality with configurable
 * trim-insensitivity, a numeric epsilon, empty-string-vs-null coercion, and UTC date comparison.
 * Injected via the `$normalizationConfig` bind so projects tune it per deployment.
 */
final class Normalizer
{
    private bool $trim;
    private float $numericEpsilon;
    private bool $emptyStringEqualsNull;

    /**
     * @param array{trim?: bool, numeric_epsilon?: float|int, empty_string_equals_null?: bool} $normalizationConfig
     */
    public function __construct(array $normalizationConfig = [])
    {
        $this->trim = (bool) ($normalizationConfig['trim'] ?? true);
        $this->numericEpsilon = (float) ($normalizationConfig['numeric_epsilon'] ?? 0.0);
        $this->emptyStringEqualsNull = (bool) ($normalizationConfig['empty_string_equals_null'] ?? true);
    }

    /**
     * Scalar equality for the diff engine. Handles null/empty coercion, numeric tolerance,
     * trim-insensitive strings, and DateTime-in-UTC. Non-scalar values fall back to loose `==`.
     */
    public function scalarEquals(mixed $left, mixed $right): bool
    {
        $left = $this->coerceEmpty($left);
        $right = $this->coerceEmpty($right);

        if ($left === null || $right === null) {
            return $left === $right;
        }

        if ($left instanceof \DateTimeInterface || $right instanceof \DateTimeInterface) {
            return $this->dateEquals($left, $right);
        }

        if ($this->isNumeric($left) && $this->isNumeric($right)) {
            return abs(((float) $left) - ((float) $right)) <= $this->numericEpsilon;
        }

        if (is_string($left) && is_string($right)) {
            return $this->normalizeString($left) === $this->normalizeString($right);
        }

        if (is_bool($left) || is_bool($right)) {
            return (bool) $left === (bool) $right;
        }

        return $left == $right; // loose: covers int-vs-numeric-string etc.
    }

    private function coerceEmpty(mixed $value): mixed
    {
        if (!$this->emptyStringEqualsNull) {
            return $value;
        }

        if ($value === '' || (is_string($value) && $this->trim && trim($value) === '')) {
            return null;
        }

        return $value;
    }

    private function normalizeString(string $value): string
    {
        return $this->trim ? trim($value) : $value;
    }

    private function isNumeric(mixed $value): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)));
    }

    private function dateEquals(mixed $left, mixed $right): bool
    {
        $l = $this->toDateTime($left);
        $r = $this->toDateTime($right);
        if ($l === null || $r === null) {
            return $l === $r;
        }

        return $l->getTimestamp() === $r->getTimestamp();
    }

    private function toDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)->setTimezone(new \DateTimeZone('UTC'));
        }
        if (is_int($value)) {
            return (new \DateTimeImmutable('@' . $value))->setTimezone(new \DateTimeZone('UTC'));
        }
        if (is_string($value) && $value !== '') {
            try {
                return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'));
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
