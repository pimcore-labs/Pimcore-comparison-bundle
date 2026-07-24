<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Tests\Unit;

use Pimcore\Bundle\ComparisonBundle\Comparator\AssetFieldComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparatorRegistry;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparisonContext;
use Pimcore\Bundle\ComparisonBundle\Comparison\Normalizer;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\ClassDefinition\Data\Image;
use Pimcore\Model\DataObject\Concrete;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit coverage of the AssetFieldComparator (FR-CMP-013): identity-based (id / path) diffing of
 * image / hotspot-image / gallery fields into equal / changed / only-side, with per-side id+path
 * meta for the thumbnail preview. No Pimcore kernel required — assets are Asset stubs identified by
 * their id.
 */
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\CoversFeature('comparators.asset-field')]
final class AssetFieldComparatorTest extends TestCase
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

    private function field(string $name = 'heroImage', string $title = 'Hero Image'): Image
    {
        $fd = new Image();
        $fd->setName($name);
        $fd->setTitle($title);

        return $fd;
    }

    private function asset(int $id, ?string $path = null): Asset
    {
        $asset = $this->createStub(Asset::class);
        $asset->method('getId')->willReturn($id);
        if ($path !== null) {
            $asset->method('getFullPath')->willReturn($path);
        }

        return $asset;
    }

    public function testSupportsImageFieldtypes(): void
    {
        $cmp = new AssetFieldComparator();
        self::assertTrue($cmp->supports($this->field()));
    }

    public function testSameAssetBothSidesIsEqual(): void
    {
        $cmp = new AssetFieldComparator();
        $asset = $this->asset(42, '/product/hero.jpg');

        $diff = $cmp->compare($asset, $asset, $this->field(), $this->context());

        self::assertSame(DiffStatus::EQUAL, $diff->status);
        self::assertSame(42, $diff->meta['left'][0]['id']);
        self::assertSame(42, $diff->meta['right'][0]['id']);
        self::assertSame('/product/hero.jpg', $diff->meta['left'][0]['path']);
    }

    public function testDifferentAssetIdsAreChanged(): void
    {
        $cmp = new AssetFieldComparator();
        $left = $this->asset(1, '/a.jpg');
        $right = $this->asset(2, '/b.jpg');

        $diff = $cmp->compare($left, $right, $this->field(), $this->context());

        self::assertSame(DiffStatus::CHANGED, $diff->status);
        self::assertSame(1, $diff->meta['left'][0]['id']);
        self::assertSame(2, $diff->meta['right'][0]['id']);
        self::assertSame('/a.jpg', $diff->meta['left'][0]['path']);
        self::assertSame('/b.jpg', $diff->meta['right'][0]['path']);
        self::assertSame('/a.jpg', $diff->leftDisplay);
        self::assertSame('/b.jpg', $diff->rightDisplay);
    }

    public function testAssetVsNullIsOnlyLeft(): void
    {
        $cmp = new AssetFieldComparator();

        $diff = $cmp->compare($this->asset(7), null, $this->field(), $this->context());

        self::assertSame(DiffStatus::ONLY_LEFT, $diff->status);
        self::assertSame(7, $diff->meta['left'][0]['id']);
        self::assertSame([], $diff->meta['right']);
    }

    public function testNullVsAssetIsOnlyRight(): void
    {
        $cmp = new AssetFieldComparator();

        $diff = $cmp->compare(null, $this->asset(9), $this->field(), $this->context());

        self::assertSame(DiffStatus::ONLY_RIGHT, $diff->status);
        self::assertSame([], $diff->meta['left']);
        self::assertSame(9, $diff->meta['right'][0]['id']);
    }

    public function testBothNullIsEqual(): void
    {
        $cmp = new AssetFieldComparator();

        $diff = $cmp->compare(null, null, $this->field(), $this->context());

        self::assertSame(DiffStatus::EQUAL, $diff->status);
        self::assertSame([], $diff->meta['left']);
        self::assertSame([], $diff->meta['right']);
        self::assertNull($diff->leftDisplay);
        self::assertNull($diff->rightDisplay);
    }
}
