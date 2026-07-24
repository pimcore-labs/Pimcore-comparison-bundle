<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Tests\Unit;

use Pimcore\Bundle\ComparisonBundle\Comparator\ComparatorRegistry;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparisonContext;
use Pimcore\Bundle\ComparisonBundle\Comparator\RelationComparator;
use Pimcore\Bundle\ComparisonBundle\Comparison\Normalizer;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit coverage of the RelationComparator (FR-CMP-007): set/order classification of related
 * elements into equal / changed / reordered / only-side, with add/remove/kept counts. No Pimcore
 * kernel required — elements are ElementInterface stubs identified by their id.
 */
final class RelationComparatorTest extends TestCase
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

    private function field(string $name = 'related', string $title = 'Related'): ManyToManyObjectRelation
    {
        $fd = new ManyToManyObjectRelation();
        $fd->setName($name);
        $fd->setTitle($title);

        return $fd;
    }

    private function element(int $id): ElementInterface
    {
        $element = $this->createStub(ElementInterface::class);
        $element->method('getId')->willReturn($id);

        return $element;
    }

    public function testSupportsRelationFieldtypes(): void
    {
        $cmp = new RelationComparator();
        self::assertTrue($cmp->supports($this->field()));
    }

    public function testIdenticalListsAreEqual(): void
    {
        $cmp = new RelationComparator();
        $a = $this->element(1);
        $b = $this->element(2);

        $diff = $cmp->compare([$a, $b], [$a, $b], $this->field(), $this->context());

        self::assertSame(DiffStatus::EQUAL, $diff->status);
        self::assertFalse($diff->meta['reordered']);
        self::assertSame(2, $diff->meta['counts']['kept']);
        self::assertSame(0, $diff->meta['counts']['added']);
        self::assertSame(0, $diff->meta['counts']['removed']);
    }

    public function testAddedElementIsChanged(): void
    {
        $cmp = new RelationComparator();
        $a = $this->element(1);
        $b = $this->element(2);
        $c = $this->element(3);

        $diff = $cmp->compare([$a, $b], [$a, $b, $c], $this->field(), $this->context());

        self::assertSame(DiffStatus::CHANGED, $diff->status);
        self::assertSame(1, $diff->meta['counts']['added']);
        self::assertSame(2, $diff->meta['counts']['kept']);
        self::assertSame(0, $diff->meta['counts']['removed']);
    }

    public function testRemovedElementIsChanged(): void
    {
        $cmp = new RelationComparator();
        $a = $this->element(1);
        $b = $this->element(2);
        $c = $this->element(3);

        $diff = $cmp->compare([$a, $b, $c], [$a, $c], $this->field(), $this->context());

        self::assertSame(DiffStatus::CHANGED, $diff->status);
        self::assertSame(1, $diff->meta['counts']['removed']);
        self::assertSame(2, $diff->meta['counts']['kept']);
        self::assertSame(0, $diff->meta['counts']['added']);
    }

    public function testSameSetDifferentOrderIsReordered(): void
    {
        $cmp = new RelationComparator();
        $a = $this->element(1);
        $b = $this->element(2);

        $diff = $cmp->compare([$a, $b], [$b, $a], $this->field(), $this->context());

        self::assertSame(DiffStatus::REORDERED, $diff->status);
        self::assertTrue($diff->meta['reordered']);
        self::assertSame(2, $diff->meta['counts']['kept']);
        self::assertSame(0, $diff->meta['counts']['added']);
        self::assertSame(0, $diff->meta['counts']['removed']);
        foreach ($diff->meta['chips'] as $chip) {
            self::assertSame('moved', $chip['state']);
        }
    }

    public function testOnlyLeftWhenRightNull(): void
    {
        $cmp = new RelationComparator();

        $diff = $cmp->compare([$this->element(1)], null, $this->field(), $this->context());

        self::assertSame(DiffStatus::ONLY_LEFT, $diff->status);
    }

    public function testOnlyRightWhenLeftNull(): void
    {
        $cmp = new RelationComparator();

        $diff = $cmp->compare(null, [$this->element(1)], $this->field(), $this->context());

        self::assertSame(DiffStatus::ONLY_RIGHT, $diff->status);
    }

    public function testBothEmptyIsEqual(): void
    {
        $cmp = new RelationComparator();

        $diff = $cmp->compare(null, null, $this->field(), $this->context());

        self::assertSame(DiffStatus::EQUAL, $diff->status);
    }
}
