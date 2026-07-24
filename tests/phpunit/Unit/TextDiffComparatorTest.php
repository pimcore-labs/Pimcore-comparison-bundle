<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Tests\Unit;

use Pimcore\Bundle\ComparisonBundle\Comparator\ComparatorRegistry;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparisonContext;
use Pimcore\Bundle\ComparisonBundle\Comparator\TextDiffComparator;
use Pimcore\Bundle\ComparisonBundle\Comparison\Normalizer;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Model\DataObject\ClassDefinition\Data\Textarea;
use Pimcore\Model\DataObject\ClassDefinition\Data\Wysiwyg;
use Pimcore\Model\DataObject\Concrete;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit coverage of {@see TextDiffComparator}: word-level inline text diff (FR-CMP-005) and
 * WYSIWYG sanitized-HTML reduction (FR-CMP-006). No Pimcore kernel required.
 */
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\CoversFeature('comparators.text-diff')]
final class TextDiffComparatorTest extends TestCase
{
    private function context(): ComparisonContext
    {
        return new ComparisonContext(
            $this->createStub(Concrete::class),
            $this->createStub(Concrete::class),
            [],
            new Normalizer(['trim' => true, 'numeric_epsilon' => 0.0, 'empty_string_equals_null' => true]),
            new ComparatorRegistry([]),
        );
    }

    private function textarea(string $name = 'description', string $title = 'Description'): Textarea
    {
        $fd = new Textarea();
        $fd->setName($name);
        $fd->setTitle($title);

        return $fd;
    }

    private function wysiwyg(string $name = 'body', string $title = 'Body'): Wysiwyg
    {
        $fd = new Wysiwyg();
        $fd->setName($name);
        $fd->setTitle($title);

        return $fd;
    }

    /**
     * @param list<array{op: string, text: string}> $tokens
     * @param 'equal'|'insert'|'delete'             $op
     * @return list<string>
     */
    private function textsForOp(array $tokens, string $op): array
    {
        return array_values(array_map(
            static fn (array $t): string => $t['text'],
            array_filter($tokens, static fn (array $t): bool => $t['op'] === $op),
        ));
    }

    public function testSupportsOnlyTextFieldtypes(): void
    {
        $cmp = new TextDiffComparator();
        self::assertTrue($cmp->supports($this->textarea()), 'supports textarea');
        self::assertTrue($cmp->supports($this->wysiwyg()), 'supports wysiwyg');
        self::assertFalse(
            $cmp->supports(new \Pimcore\Model\DataObject\ClassDefinition\Data\Input()),
            'does not support scalar input',
        );
    }

    public function testEqualTextareaStringsAreEqualWithNoInlineDiff(): void
    {
        $cmp = new TextDiffComparator();
        $diff = $cmp->compare('The quick brown fox', 'The quick brown fox', $this->textarea(), $this->context());

        self::assertSame(DiffStatus::EQUAL, $diff->status);
        self::assertNull($diff->inlineDiff, 'no inline diff for equal values');
    }

    public function testChangedSentenceProducesWordLevelInlineDiff(): void
    {
        $cmp = new TextDiffComparator();
        $diff = $cmp->compare(
            'The quick brown fox jumps',
            'The slow brown fox leaps',
            $this->textarea(),
            $this->context(),
        );

        self::assertSame(DiffStatus::CHANGED, $diff->status);
        self::assertIsArray($diff->inlineDiff);

        $deletes = $this->textsForOp($diff->inlineDiff, 'delete');
        $inserts = $this->textsForOp($diff->inlineDiff, 'insert');
        $equals = $this->textsForOp($diff->inlineDiff, 'equal');

        // at least one delete and one insert token
        self::assertContains('quick', $deletes);
        self::assertContains('jumps', $deletes);
        self::assertContains('slow', $inserts);
        self::assertContains('leaps', $inserts);

        // the common tokens are preserved as equal
        self::assertContains('The', $equals);
        self::assertContains('brown', $equals);
        self::assertContains('fox', $equals);

        // re-joining every token faithfully reproduces the two sides
        $rebuiltLeft = implode('', array_map(
            static fn (array $t): string => $t['text'],
            array_filter($diff->inlineDiff, static fn (array $t): bool => $t['op'] !== 'insert'),
        ));
        $rebuiltRight = implode('', array_map(
            static fn (array $t): string => $t['text'],
            array_filter($diff->inlineDiff, static fn (array $t): bool => $t['op'] !== 'delete'),
        ));
        self::assertSame('The quick brown fox jumps', $rebuiltLeft);
        self::assertSame('The slow brown fox leaps', $rebuiltRight);
    }

    public function testOnlyLeftWhenRightIsNull(): void
    {
        $cmp = new TextDiffComparator();
        $diff = $cmp->compare('Some content', null, $this->textarea(), $this->context());

        self::assertSame(DiffStatus::ONLY_LEFT, $diff->status);
        self::assertSame('Some content', $diff->leftDisplay);
        self::assertNull($diff->rightDisplay);
        self::assertNull($diff->inlineDiff, 'only-* cases carry no inline diff');
    }

    public function testWysiwygDifferingOnlyInMarkupIsEqual(): void
    {
        $cmp = new TextDiffComparator();
        $diff = $cmp->compare(
            '<p>Hello <strong>world</strong></p>',
            "<div>Hello\n   <em>world</em></div>",
            $this->wysiwyg(),
            $this->context(),
        );

        self::assertSame(DiffStatus::EQUAL, $diff->status, 'sanitize() reduces both sides to identical plain text');
        self::assertSame('Hello world', $diff->leftDisplay, 'display is the sanitized stream');
        self::assertSame('Hello world', $diff->rightDisplay);
        self::assertNull($diff->inlineDiff);
    }

    public function testWysiwygRealContentChangeDiffsSanitizedText(): void
    {
        $cmp = new TextDiffComparator();
        $diff = $cmp->compare(
            '<p>Hello <strong>world</strong></p>',
            '<p>Hello <strong>there</strong></p>',
            $this->wysiwyg(),
            $this->context(),
        );

        self::assertSame(DiffStatus::CHANGED, $diff->status);
        self::assertIsArray($diff->inlineDiff);
        self::assertContains('world', $this->textsForOp($diff->inlineDiff, 'delete'));
        self::assertContains('there', $this->textsForOp($diff->inlineDiff, 'insert'));
        self::assertContains('Hello', $this->textsForOp($diff->inlineDiff, 'equal'));
    }
}
