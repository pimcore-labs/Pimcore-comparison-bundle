<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Tests\Unit;

use Pimcore\Bundle\ComparisonBundle\Comparator\ClassificationStoreComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparatorRegistry;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparisonContext;
use Pimcore\Bundle\ComparisonBundle\Comparison\Normalizer;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Concrete;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit coverage of the container ClassificationStoreComparator (FR-CMP-011): Classificationstore
 * has no core diff-preview override, so the comparator walks the container's group → key → value (per
 * language) map itself, producing one leaf FieldDiff per (group, key, language) triple grouped under a
 * per-group container row.
 *
 * No Pimcore kernel required. The real container ({@see \Pimcore\Model\DataObject\Classificationstore})
 * needs a class-store config service to enumerate keys, so the comparator drives its walk off the
 * container's own `getItems()` map; a lightweight duck-typed double exposing getItems()/getActiveGroups()
 * pre-supplies that nested `[groupId => [keyId => [language => value]]]` structure.
 */
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\CoversFeature('comparators.classification-store')]
final class ClassificationStoreComparatorTest extends TestCase
{
    private function context(): ComparisonContext
    {
        return new ComparisonContext(
            $this->createStub(Concrete::class),
            $this->createStub(Concrete::class),
            ['en', 'de'],
            new Normalizer([]),
            new ComparatorRegistry([]),
        );
    }

    private function fieldDefinition(): Classificationstore
    {
        $cs = new Classificationstore();
        $cs->setName('attributes');
        $cs->setTitle('Attributes');

        return $cs;
    }

    /**
     * A duck-typed stand-in for a Classificationstore container. getItems() returns the pre-supplied
     * nested map; getActiveGroups() is derived from its top-level group ids.
     *
     * @param array<int, array<int, array<string, mixed>>> $items [groupId => [keyId => [language => value]]]
     */
    private function container(array $items): object
    {
        return new class($items) {
            /** @param array<int, array<int, array<string, mixed>>> $items */
            public function __construct(private array $items)
            {
            }

            /** @return array<int, array<int, array<string, mixed>>> */
            public function getItems(): array
            {
                return $this->items;
            }

            /** @return array<int, bool> */
            public function getActiveGroups(): array
            {
                $active = [];
                foreach (array_keys($this->items) as $groupId) {
                    $active[(int) $groupId] = true;
                }

                return $active;
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

    public function testSupportsClassificationstoreOnly(): void
    {
        $cmp = new ClassificationStoreComparator();

        self::assertTrue($cmp->supports($this->fieldDefinition()));

        $input = new Input();
        $input->setName('sku');
        self::assertFalse($cmp->supports($input));
    }

    public function testIdenticalMapsAreEqual(): void
    {
        $map = [
            10 => [100 => ['en' => 'Cotton', 'de' => 'Baumwolle']],
            20 => [200 => ['en' => 'Red']],
        ];

        $result = (new ClassificationStoreComparator())->compare(
            $this->container($map),
            $this->container($map),
            $this->fieldDefinition(),
            $this->context(),
        );

        self::assertSame(DiffStatus::EQUAL, $result->status);
        self::assertSame('classificationstore', $result->fieldtype);
        self::assertNull($result->leftDisplay);
        self::assertNull($result->rightDisplay);
        self::assertCount(2, $result->children);

        $groups = $this->byName($result->children);
        self::assertArrayHasKey('attributes.10', $groups);
        self::assertArrayHasKey('attributes.20', $groups);
        self::assertSame('classificationstoreGroup', $groups['attributes.10']->fieldtype);
        self::assertSame(DiffStatus::EQUAL, $groups['attributes.10']->status);
        self::assertSame(10, $groups['attributes.10']->meta['group']);

        // Group 10 carries two per-language leaves, both equal.
        $leaves = $this->byName($groups['attributes.10']->children);
        self::assertArrayHasKey('attributes.10.100.en', $leaves);
        self::assertArrayHasKey('attributes.10.100.de', $leaves);
        self::assertSame('classificationstoreKey', $leaves['attributes.10.100.en']->fieldtype);
        self::assertSame(DiffStatus::EQUAL, $leaves['attributes.10.100.en']->status);
        self::assertSame('Cotton', $leaves['attributes.10.100.en']->leftDisplay);
        self::assertSame('Cotton', $leaves['attributes.10.100.en']->rightDisplay);
        self::assertSame(
            ['group' => 10, 'key' => 100, 'language' => 'en'],
            $leaves['attributes.10.100.en']->meta,
        );
    }

    public function testChangedKeyValueMarksParentAndLeafChanged(): void
    {
        $leftMap = [10 => [100 => ['en' => 'Cotton', 'de' => 'Baumwolle']]];
        $rightMap = [10 => [100 => ['en' => 'Linen', 'de' => 'Baumwolle']]]; // en changed, de equal

        $result = (new ClassificationStoreComparator())->compare(
            $this->container($leftMap),
            $this->container($rightMap),
            $this->fieldDefinition(),
            $this->context(),
        );

        self::assertSame(DiffStatus::CHANGED, $result->status);

        $groups = $this->byName($result->children);
        self::assertSame(DiffStatus::CHANGED, $groups['attributes.10']->status);

        $leaves = $this->byName($groups['attributes.10']->children);
        self::assertSame(DiffStatus::CHANGED, $leaves['attributes.10.100.en']->status);
        self::assertSame(DiffStatus::EQUAL, $leaves['attributes.10.100.de']->status);

        self::assertSame('Cotton', $leaves['attributes.10.100.en']->leftDisplay);
        self::assertSame('Linen', $leaves['attributes.10.100.en']->rightDisplay);

        self::assertSame('en', $leaves['attributes.10.100.en']->meta['language']);
        self::assertSame(100, $leaves['attributes.10.100.en']->meta['key']);
        self::assertSame(10, $leaves['attributes.10.100.en']->meta['group']);
        self::assertStringContainsString('[en]', $leaves['attributes.10.100.en']->label);
    }

    public function testKeyPresentOnlyOnLeftIsOnlyLeft(): void
    {
        $leftMap = [10 => [100 => ['en' => 'Cotton', 'de' => 'Baumwolle']]];
        $rightMap = [10 => [100 => ['de' => 'Baumwolle']]]; // en missing on the right side

        $result = (new ClassificationStoreComparator())->compare(
            $this->container($leftMap),
            $this->container($rightMap),
            $this->fieldDefinition(),
            $this->context(),
        );

        self::assertSame(DiffStatus::CHANGED, $result->status);

        $groups = $this->byName($result->children);
        self::assertSame(DiffStatus::CHANGED, $groups['attributes.10']->status);

        $leaves = $this->byName($groups['attributes.10']->children);
        self::assertSame(
            DiffStatus::ONLY_LEFT,
            $leaves['attributes.10.100.en']->status,
            'a key/language present only on the left is only-left',
        );
        self::assertSame(DiffStatus::EQUAL, $leaves['attributes.10.100.de']->status);
        self::assertSame('Cotton', $leaves['attributes.10.100.en']->leftDisplay);
        self::assertNull($leaves['attributes.10.100.en']->rightDisplay);
        self::assertSame('en', $leaves['attributes.10.100.en']->meta['language']);
    }

    public function testBothNullIsEqualWithNoChildren(): void
    {
        $result = (new ClassificationStoreComparator())->compare(
            null,
            null,
            $this->fieldDefinition(),
            $this->context(),
        );

        self::assertSame(DiffStatus::EQUAL, $result->status);
        self::assertSame([], $result->children);
    }
}
