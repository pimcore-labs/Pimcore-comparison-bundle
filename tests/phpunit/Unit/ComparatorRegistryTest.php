<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Tests\Unit;

use Pimcore\Bundle\ComparisonBundle\Comparator\AssetFieldComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\ClassificationStoreComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparatorRegistry;
use Pimcore\Bundle\ComparisonBundle\Comparator\FallbackComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\FieldCollectionComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\LocalizedFieldsComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\ObjectBrickComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\RelationComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\ScalarComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\TextDiffComparator;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Locks the resolution contract (FR-CMP-003): with the comparators in priority order, resolve()
 * returns the right comparator per fieldtype and the catch-all FallbackComparator never shadows a
 * more specific one.
 */
final class ComparatorRegistryTest extends TestCase
{
    private function registry(): ComparatorRegistry
    {
        // Same order the AutowireIterator yields at runtime: priority descending.
        return new ComparatorRegistry([
            new LocalizedFieldsComparator(),      // 40
            new FieldCollectionComparator(),      // 40
            new ObjectBrickComparator(),          // 40
            new ClassificationStoreComparator(),  // 40
            new AssetFieldComparator(),           // 35
            new TextDiffComparator(),             // 30
            new RelationComparator(),             // 30
            new ScalarComparator(),               // 10
            new FallbackComparator(),             // -100 (supports everything)
        ]);
    }

    private function field(string $class): Data
    {
        // resolve() dispatches on getFieldtype() only, so the field name is irrelevant here
        // (and some types — e.g. Localizedfields — reject any name but their own).
        /** @var Data $def */
        $def = new $class();

        return $def;
    }

    /**
     * @return array<string, array{class-string, class-string}>
     */
    public static function fieldtypeResolutionProvider(): array
    {
        return [
            'input → scalar' => [Data\Input::class, ScalarComparator::class],
            'numeric → scalar' => [Data\Numeric::class, ScalarComparator::class],
            'checkbox → scalar' => [Data\Checkbox::class, ScalarComparator::class],
            'textarea → text-diff' => [Data\Textarea::class, TextDiffComparator::class],
            'wysiwyg → text-diff' => [Data\Wysiwyg::class, TextDiffComparator::class],
            'manyToManyObjectRelation → relation' => [Data\ManyToManyObjectRelation::class, RelationComparator::class],
            'manyToOneRelation → relation' => [Data\ManyToOneRelation::class, RelationComparator::class],
            'localizedfields → localized' => [Data\Localizedfields::class, LocalizedFieldsComparator::class],
            'fieldcollections → field-collection' => [Data\Fieldcollections::class, FieldCollectionComparator::class],
            'objectbricks → object-brick' => [Data\Objectbricks::class, ObjectBrickComparator::class],
            'classificationstore → classification-store' => [Data\Classificationstore::class, ClassificationStoreComparator::class],
            'image → asset-field' => [Data\Image::class, AssetFieldComparator::class],
            'imageGallery → asset-field' => [Data\ImageGallery::class, AssetFieldComparator::class],
        ];
    }

    /**
     * @param class-string $fieldClass
     * @param class-string $expectedComparator
     */
    #[DataProvider('fieldtypeResolutionProvider')]
    public function testEachFieldtypeResolvesToItsComparator(string $fieldClass, string $expectedComparator): void
    {
        $resolved = $this->registry()->resolve($this->field($fieldClass));
        self::assertNotNull($resolved);
        self::assertInstanceOf($expectedComparator, $resolved);
    }

    public function testUnknownFieldtypeFallsBackToFallbackComparator(): void
    {
        // Geopoint is handled by no specific comparator, so only the catch-all matches.
        $resolved = $this->registry()->resolve($this->field(Data\Geopoint::class));
        self::assertInstanceOf(FallbackComparator::class, $resolved);
    }

    public function testFallbackAloneSupportsEverythingButNeverWinsWhenSpecificExists(): void
    {
        self::assertInstanceOf(FallbackComparator::class, (new ComparatorRegistry([new FallbackComparator()]))->resolve($this->field(Data\Input::class)));
        // With the full registry, input still routes to the scalar comparator, not the fallback.
        self::assertInstanceOf(ScalarComparator::class, $this->registry()->resolve($this->field(Data\Input::class)));
    }
}
