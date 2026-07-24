<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Tests\Unit;

use Pimcore\Bundle\ComparisonBundle\Comparator\ComparatorRegistry;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparisonContext;
use Pimcore\Bundle\ComparisonBundle\Comparator\ScalarComparator;
use Pimcore\Bundle\ComparisonBundle\Comparison\DiffResult;
use Pimcore\Bundle\ComparisonBundle\Comparison\Normalizer;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Concrete;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit coverage of the P2 comparison engine: Normalizer (FR-CMP-014), ScalarComparator
 * (FR-CMP-004), the FieldDiff/DiffStatus model (FR-CMP-015/016) and DiffResult summary (FR-CMP-018).
 * No Pimcore kernel required.
 */
final class ComparisonEngineTest extends TestCase
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

    private function field(string $name = 'sku', string $title = 'SKU'): Input
    {
        $input = new Input();
        $input->setName($name);
        $input->setTitle($title);

        return $input;
    }

    // ---- Normalizer (FR-CMP-014) ----

    public function testNumericNormalizationTreatsEquivalentNumbersEqual(): void
    {
        $n = new Normalizer();
        self::assertTrue($n->scalarEquals('13.4', '13.40'), '13.4 == 13.40');
        self::assertTrue($n->scalarEquals(13.4, '13.40'));
        self::assertFalse($n->scalarEquals('13.4', '13.5'));
    }

    public function testNumericEpsilonTolerance(): void
    {
        self::assertFalse((new Normalizer(['numeric_epsilon' => 0.0]))->scalarEquals(1.0, 1.001));
        self::assertTrue((new Normalizer(['numeric_epsilon' => 0.01]))->scalarEquals(1.0, 1.001));
    }

    public function testTrimAndEmptyNullCoercion(): void
    {
        $n = new Normalizer(['trim' => true, 'empty_string_equals_null' => true]);
        self::assertTrue($n->scalarEquals(' abc ', 'abc'));
        self::assertTrue($n->scalarEquals('', null));
        self::assertTrue($n->scalarEquals('   ', null));
        self::assertFalse($n->scalarEquals('a', null));
    }

    public function testDatesComparedInUtc(): void
    {
        $n = new Normalizer();
        $a = new \DateTime('2025-03-01T00:00:00+00:00');
        $b = new \DateTime('2025-03-01T02:00:00+02:00'); // same instant
        self::assertTrue($n->scalarEquals($a, $b));
        self::assertFalse($n->scalarEquals($a, new \DateTime('2025-03-02T00:00:00+00:00')));
    }

    // ---- ScalarComparator (FR-CMP-004) ----

    public function testScalarComparatorSupportsScalarTypesOnly(): void
    {
        $cmp = new ScalarComparator();
        self::assertTrue($cmp->supports($this->field()));
    }

    public function testScalarEqualChangedOnlyLeftOnlyRight(): void
    {
        $cmp = new ScalarComparator();
        $ctx = $this->context();
        $fd = $this->field();

        self::assertSame(DiffStatus::EQUAL, $cmp->compare('A', 'A', $fd, $ctx)->status);

        $changed = $cmp->compare('A', 'B', $fd, $ctx);
        self::assertSame(DiffStatus::CHANGED, $changed->status);
        self::assertSame('A', $changed->leftDisplay);
        self::assertSame('B', $changed->rightDisplay);

        self::assertSame(DiffStatus::ONLY_LEFT, $cmp->compare('A', null, $fd, $ctx)->status);
        self::assertSame(DiffStatus::ONLY_RIGHT, $cmp->compare(null, 'B', $fd, $ctx)->status);
        self::assertSame(DiffStatus::EQUAL, $cmp->compare(null, '', $fd, $ctx)->status);
    }

    // ---- FieldDiff / DiffStatus model (FR-CMP-015/016) ----

    public function testSwapMirrorsSidesAndStatus(): void
    {
        $diff = new FieldDiff('sku', 'SKU', 'input', DiffStatus::ONLY_LEFT, 'L', null);
        $swapped = $diff->swapped();
        self::assertSame(DiffStatus::ONLY_RIGHT, $swapped->status);
        self::assertSame('L', $swapped->rightDisplay);
        self::assertNull($swapped->leftDisplay);
    }

    public function testInlineDiffSwapFlipsInsertDelete(): void
    {
        $diff = new FieldDiff('d', 'D', 'wysiwyg', DiffStatus::CHANGED, null, null, [
            ['op' => 'equal', 'text' => 'a'],
            ['op' => 'delete', 'text' => 'b'],
            ['op' => 'insert', 'text' => 'c'],
        ]);
        $tokens = $diff->swapped()->inlineDiff;
        self::assertSame('equal', $tokens[0]['op']);
        self::assertSame('insert', $tokens[1]['op']);
        self::assertSame('delete', $tokens[2]['op']);
    }

    // ---- DiffResult summary (FR-CMP-018) ----

    public function testDiffResultCountsAndDiffering(): void
    {
        $result = new DiffResult(1, 2, 'Product', [
            new FieldDiff('a', 'A', 'input', DiffStatus::EQUAL),
            new FieldDiff('b', 'B', 'input', DiffStatus::CHANGED),
            new FieldDiff('c', 'C', 'input', DiffStatus::ONLY_LEFT),
            new FieldDiff('d', 'D', 'input', DiffStatus::HIDDEN),
        ]);

        self::assertSame(4, $result->total());
        self::assertSame(2, $result->differing()); // changed + only-left
        $counts = $result->counts();
        self::assertSame(1, $counts['equal']);
        self::assertSame(1, $counts['changed']);
        self::assertSame(1, $counts['hidden']);
    }

    public function testDiffResultCountsRecurseIntoChildren(): void
    {
        $container = new FieldDiff('loc', 'Localized', 'localizedfields', DiffStatus::CHANGED, null, null, null, [
            new FieldDiff('name.en', 'Name [en]', 'input', DiffStatus::CHANGED),
            new FieldDiff('name.de', 'Name [de]', 'input', DiffStatus::EQUAL),
        ]);
        $result = new DiffResult(1, 2, 'Product', [$container]);

        self::assertSame(2, $result->total(), 'children counted, not the container');
        self::assertSame(1, $result->differing());
    }
}
