<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Tests\Unit;

use Pimcore\Bundle\ComparisonBundle\Comparator\ComparatorRegistry;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparisonContext;
use Pimcore\Bundle\ComparisonBundle\Comparator\ObjectBrickComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\ScalarComparator;
use Pimcore\Bundle\ComparisonBundle\Comparison\Normalizer;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;
use Pimcore\Model\DataObject\Concrete;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit coverage of the container ObjectBrickComparator (FR-CMP-010): it groups a container's
 * bricks BY TYPE and emits one container child FieldDiff per brick type, with presence surfacing as
 * only-left / only-right.
 *
 * No Pimcore kernel required. The real Objectbrick container / brick-data objects need a live class
 * definition (absent here), and {@see \Pimcore\Model\DataObject\Objectbrick\Definition::getByKey}
 * therefore yields no inner field definitions — so the assertions are made at the BRICK level using
 * lightweight duck-typed doubles: a container exposing getItems() over brick doubles exposing
 * getType().
 */
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\CoversFeature('comparators.object-brick')]
final class ObjectBrickComparatorTest extends TestCase
{
    private function context(ComparatorRegistry $registry): ComparisonContext
    {
        return new ComparisonContext(
            $this->createStub(Concrete::class),
            $this->createStub(Concrete::class),
            ['en', 'de'],
            new Normalizer([]),
            $registry,
        );
    }

    private function objectBricks(): Objectbricks
    {
        $ob = new Objectbricks();
        $ob->setName('bricks');
        $ob->setTitle('Bricks');

        return $ob;
    }

    /**
     * A duck-typed stand-in for an Objectbrick container: getItems() returns the given brick doubles.
     *
     * @param list<object> $items
     */
    private function container(array $items): object
    {
        return new class($items) {
            /** @param list<object> $items */
            public function __construct(private array $items)
            {
            }

            /** @return list<object> */
            public function getItems(): array
            {
                return $this->items;
            }
        };
    }

    /**
     * A duck-typed stand-in for a brick-data object: getType() reports the brick type; a couple of
     * field values are exposed via getValueForFieldName() (unused until a real definition provides
     * inner field defs, but kept realistic).
     *
     * @param array<string, mixed> $values
     */
    private function brick(string $type, array $values = []): object
    {
        return new class($type, $values) {
            /** @param array<string, mixed> $values */
            public function __construct(private string $type, private array $values)
            {
            }

            public function getType(): string
            {
                return $this->type;
            }

            public function getValueForFieldName(string $name): mixed
            {
                return $this->values[$name] ?? null;
            }
        };
    }

    /** @param list<FieldDiff> $children */
    private function byName(array $children): array
    {
        $out = [];
        foreach ($children as $child) {
            $out[$child->name] = $child;
        }

        return $out;
    }

    public function testSupportsObjectbricksOnly(): void
    {
        $cmp = new ObjectBrickComparator();

        self::assertTrue($cmp->supports($this->objectBricks()));

        $input = new Input();
        $input->setName('sku');
        self::assertFalse($cmp->supports($input));
    }

    public function testBothSidesShareBrickTypeEmitsOneEqualBrickChild(): void
    {
        $registry = new ComparatorRegistry([new ScalarComparator()]);
        $ctx = $this->context($registry);
        $fd = $this->objectBricks();

        $left = $this->container([$this->brick('seo', ['metaTitle' => 'Trail Pro'])]);
        $right = $this->container([$this->brick('seo', ['metaTitle' => 'Trail Pro'])]);

        $result = (new ObjectBrickComparator())->compare($left, $right, $fd, $ctx);

        // No live definition -> no inner field diffs -> both bricks present -> equal container.
        self::assertSame(DiffStatus::EQUAL, $result->status);
        self::assertSame('objectbricks', $result->fieldtype);
        self::assertNull($result->leftDisplay);
        self::assertNull($result->rightDisplay);
        self::assertCount(1, $result->children);

        $rows = $this->byName($result->children);
        self::assertArrayHasKey('bricks.seo', $rows);

        $seo = $rows['bricks.seo'];
        self::assertSame('objectbrick', $seo->fieldtype);
        self::assertSame(DiffStatus::EQUAL, $seo->status);
        self::assertSame('seo', $seo->meta['type']);
        self::assertSame('seo', $seo->label);
        self::assertSame([], $seo->children);
    }

    public function testBrickPresentOnlyOnLeftIsOnlyLeft(): void
    {
        $registry = new ComparatorRegistry([new ScalarComparator()]);
        $ctx = $this->context($registry);
        $fd = $this->objectBricks();

        $left = $this->container([$this->brick('seo', ['metaTitle' => 'Trail Pro'])]);
        $right = $this->container([]); // no bricks on the right

        $result = (new ObjectBrickComparator())->compare($left, $right, $fd, $ctx);

        self::assertSame(DiffStatus::CHANGED, $result->status);
        self::assertCount(1, $result->children);

        $rows = $this->byName($result->children);
        self::assertArrayHasKey('bricks.seo', $rows);
        self::assertSame(DiffStatus::ONLY_LEFT, $rows['bricks.seo']->status);
        self::assertSame('seo', $rows['bricks.seo']->meta['type']);
    }

    public function testBrickPresentOnlyOnRightIsOnlyRight(): void
    {
        $registry = new ComparatorRegistry([new ScalarComparator()]);
        $ctx = $this->context($registry);
        $fd = $this->objectBricks();

        $left = $this->container([]);
        $right = $this->container([$this->brick('seo')]);

        $result = (new ObjectBrickComparator())->compare($left, $right, $fd, $ctx);

        self::assertSame(DiffStatus::CHANGED, $result->status);
        $rows = $this->byName($result->children);
        self::assertArrayHasKey('bricks.seo', $rows);
        self::assertSame(DiffStatus::ONLY_RIGHT, $rows['bricks.seo']->status);
        self::assertSame('seo', $rows['bricks.seo']->meta['type']);
    }

    public function testBothNullIsEqualWithNoChildren(): void
    {
        $registry = new ComparatorRegistry([new ScalarComparator()]);
        $ctx = $this->context($registry);
        $fd = $this->objectBricks();

        $result = (new ObjectBrickComparator())->compare(null, null, $fd, $ctx);

        self::assertSame(DiffStatus::EQUAL, $result->status);
        self::assertSame([], $result->children);
    }
}
