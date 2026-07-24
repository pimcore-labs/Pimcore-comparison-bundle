<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Word-level inline text diff for long-text fieldtypes (FR-CMP-005). For `wysiwyg` each side is first
 * reduced to a plain-text stream (FR-CMP-006, sanitized HTML) so markup churn does not read as a
 * content change. Sits above {@see ScalarComparator} (priority 30 > 10) so it wins for text fields.
 */
#[AutoconfigureTag('pimcore.comparison.field_comparator', ['priority' => 30])]
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\AsFeature(id: 'comparators.text-diff', group: 'comparators', name: 'Text / WYSIWYG diff comparator', description: 'Word-level inline diff for textual fields; WYSIWYG diffed on sanitized HTML.', status: \Pimcore\Bundle\ComparisonBundle\Feature\FeatureStatus::BETA, specRefs: ['FR-CMP-005', 'FR-CMP-006'], dependsOn: ['core.comparator-registry'], since: '2026-07-24', backendOnly: true)]
final class TextDiffComparator extends AbstractFieldComparator
{
    private const TEXT_FIELDTYPES = ['textarea', 'wysiwyg'];

    public function supports(Data $fieldDefinition): bool
    {
        return in_array($fieldDefinition->getFieldtype(), self::TEXT_FIELDTYPES, true);
    }

    public function compare(
        mixed $leftValue,
        mixed $rightValue,
        Data $fieldDefinition,
        ComparisonContext $context,
    ): FieldDiff {
        $isWysiwyg = $fieldDefinition->getFieldtype() === 'wysiwyg';

        $left = $this->plainText($leftValue, $isWysiwyg);
        $right = $this->plainText($rightValue, $isWysiwyg);

        // Equality is a trim-compare of the sanitized streams: for wysiwyg this makes two values
        // that differ only in markup/whitespace compare equal.
        $equal = trim((string) $left) === trim((string) $right);
        $status = $this->statusFor($left, $right, $equal);

        $inlineDiff = $status === DiffStatus::CHANGED
            ? $this->wordDiff((string) $left, (string) $right)
            : null;

        return $this->diff($fieldDefinition, $status, $left, $right, $inlineDiff);
    }

    /**
     * Reduce a raw field value to the display/diff stream: for `wysiwyg` sanitize the HTML, for
     * `textarea` use the raw string. Null passes through so {@see statusFor} can detect only-* cases.
     */
    private function plainText(mixed $value, bool $isWysiwyg): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = (string) $value;

        return $isWysiwyg ? $this->sanitize($string) : $string;
    }

    /**
     * v1 "sanitized HTML": strip tags then collapse every run of whitespace to a single space.
     *
     * NOTE: this is a deliberately minimal reduction; a full platform HTML sanitizer (entity
     * decoding, block-level newline preservation, allow-listed inline markup) is a later upgrade.
     */
    private function sanitize(string $html): string
    {
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Self-contained O(n*m) LCS word diff. Tokens keep their whitespace separators (PREG_SPLIT_DELIM_CAPTURE)
     * so the joined `text` is faithful to the input. 'delete' = token only in left, 'insert' = token only
     * in right, 'equal' = common.
     *
     * @return list<array{op: 'equal'|'insert'|'delete', text: string}>
     */
    private function wordDiff(string $left, string $right): array
    {
        $a = $this->tokenize($left);
        $b = $this->tokenize($right);
        $n = count($a);
        $m = count($b);

        // dp[i][j] = length of the LCS of a[i..] and b[j..].
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = $a[$i] === $b[$j]
                    ? $dp[$i + 1][$j + 1] + 1
                    : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = ['op' => 'equal', 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $ops[] = ['op' => 'delete', 'text' => $a[$i]];
                $i++;
            } else {
                $ops[] = ['op' => 'insert', 'text' => $b[$j]];
                $j++;
            }
        }
        while ($i < $n) {
            $ops[] = ['op' => 'delete', 'text' => $a[$i]];
            $i++;
        }
        while ($j < $m) {
            $ops[] = ['op' => 'insert', 'text' => $b[$j]];
            $j++;
        }

        return $ops;
    }

    /**
     * Split into words while KEEPING the whitespace separators, dropping only the empty fragments that
     * preg_split emits at boundaries (they carry no text and would just be equal-noise).
     *
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $tokens = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) {
            return $text === '' ? [] : [$text];
        }

        return array_values(array_filter($tokens, static fn (string $t): bool => $t !== ''));
    }
}
