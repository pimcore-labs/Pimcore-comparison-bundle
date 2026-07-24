<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Tests\Unit;

use Pimcore\Bundle\ComparisonBundle\Comparator\ComparatorRegistry;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparisonContext;
use Pimcore\Bundle\ComparisonBundle\Comparator\FieldCollectionComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\ScalarComparator;
use Pimcore\Bundle\ComparisonBundle\Comparison\Normalizer;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data\Fieldcollections;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Concrete;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit coverage of the container FieldCollectionComparator (FR-CMP-009): it pairs collection
 * items BY INDEX and emits one container child FieldDiff ("Item #i") per index, carrying the index
 * (and the v1 matched-by-index caveat) in meta and surfacing a one-sided index as only-left /
 * only-right.
 *
 * No Pimcore kernel required. Per-item field definitions come from
 * {@see \Pimcore\Model\DataObject\Fieldcollection\Definition::getByKey()}, which needs a real
 * definition file that is absent here — so the comparator's try/catch yields no child field defs and
 * items diff at the structural (item) level with no per-field children. That is exactly what these
 * tests exercise: item counting, index metadata, and one-sided-index status. The real container and
 * items ({@see \Pimcore\Model\DataObject\Fieldcollection} / its `AbstractData`) are duck-typed via
 * getItems()/getType()/getValueForFieldName(), so lightweight doubles stand in.
 */
final class FieldCollectionComparatorTest extends TestCase
{
    private function context(ComparatorRegistry $registry): ComparisonContext
    {
        return new ComparisonContext(
            $this->createStub(Concrete::class),
            $this->createStub(Concrete::class),
            [],
            new Normalizer([]),
            $registry,
        );
    }

    private function fieldDefinition(): Fieldcollections
    {
        $fc = new Fieldcollections();
        $fc->setName('specs');

        return $fc;
    }

    /**
     * A duck-typed stand-in for a Fieldcollection container: getItems() returns the item doubles.
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
     * A duck-typed stand-in for a Fieldcollection item (AbstractData): typed and field-addressable.
     *
     * @param array<string, mixed> $values
     */
    private function item(string $type, array $values = []): object
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

    public function testSupportsFieldcollectionsOnly(): void
    {
        $cmp = new FieldCollectionComparator();

        self::assertTrue($cmp->supports($this->fieldDefinition()));

        $input = new Input();
        $input->setName('sku');
        self::assertFalse($cmp->supports($input));
    }

    public function testEqualItemCountsProduceOneContainerChildPerItem(): void
    {
        $registry = new ComparatorRegistry([new ScalarComparator()]);
        $ctx = $this->context($registry);
        $fd = $this->fieldDefinition();

        $left = $this->container([
            $this->item('spec', ['label' => 'Weight', 'value' => '2.1kg']),
            $this->item('spec', ['label' => 'Colour', 'value' => 'Red']),
        ]);
        $right = $this->container([
            $this->item('spec', ['label' => 'Weight', 'value' => '2.1kg']),
            $this->item('spec', ['label' => 'Colour', 'value' => 'Blue']),
        ]);

        $result = (new FieldCollectionComparator())->compare($left, $right, $fd, $ctx);

        self::assertSame('fieldcollections', $result->fieldtype);
        self::assertNull($result->leftDisplay);
        self::assertNull($result->rightDisplay);
        self::assertCount(2, $result->children);

        $rows = $this->byName($result->children);
        self::assertArrayHasKey('specs.0', $rows);
        self::assertArrayHasKey('specs.1', $rows);

        // Each item is a container row carrying its index and the v1 caveat.
        foreach ([0 => 'specs.0', 1 => 'specs.1'] as $index => $name) {
            self::assertSame('fieldcollectionItem', $rows[$name]->fieldtype);
            self::assertSame('Item #' . $index, $rows[$name]->label);
            self::assertSame($index, $rows[$name]->meta['index']);
            self::assertSame('matched by index (v1)', $rows[$name]->meta['note']);
        }
    }

    public function testFewerItemsOnRightSurfaceAsOnlyLeft(): void
    {
        $registry = new ComparatorRegistry([new ScalarComparator()]);
        $ctx = $this->context($registry);
        $fd = $this->fieldDefinition();

        $left = $this->container([
            $this->item('spec', ['label' => 'Weight']),
            $this->item('spec', ['label' => 'Colour']),
        ]);
        $right = $this->container([
            $this->item('spec', ['label' => 'Weight']),
        ]);

        $result = (new FieldCollectionComparator())->compare($left, $right, $fd, $ctx);

        self::assertSame(DiffStatus::CHANGED, $result->status, 'a one-sided item makes the container changed');
        self::assertCount(2, $result->children);

        $rows = $this->byName($result->children);
        self::assertSame(DiffStatus::ONLY_LEFT, $rows['specs.1']->status, 'the extra left item is only-left');
        self::assertSame(1, $rows['specs.1']->meta['index']);
    }

    public function testMoreItemsOnRightSurfaceAsOnlyRight(): void
    {
        $registry = new ComparatorRegistry([new ScalarComparator()]);
        $ctx = $this->context($registry);
        $fd = $this->fieldDefinition();

        $left = $this->container([
            $this->item('spec', ['label' => 'Weight']),
        ]);
        $right = $this->container([
            $this->item('spec', ['label' => 'Weight']),
            $this->item('spec', ['label' => 'Colour']),
        ]);

        $result = (new FieldCollectionComparator())->compare($left, $right, $fd, $ctx);

        self::assertSame(DiffStatus::CHANGED, $result->status);
        $rows = $this->byName($result->children);
        self::assertSame(DiffStatus::ONLY_RIGHT, $rows['specs.1']->status, 'the extra right item is only-right');
    }

    public function testBothNullIsEqualWithNoChildren(): void
    {
        $registry = new ComparatorRegistry([new ScalarComparator()]);
        $ctx = $this->context($registry);
        $fd = $this->fieldDefinition();

        $result = (new FieldCollectionComparator())->compare(null, null, $fd, $ctx);

        self::assertSame(DiffStatus::EQUAL, $result->status);
        self::assertSame([], $result->children);
    }
}
