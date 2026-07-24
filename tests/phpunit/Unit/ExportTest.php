<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Tests\Unit;

use Pimcore\Bundle\ComparisonBundle\Comparison\DiffResult;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Bundle\ComparisonBundle\Export\DiffExporter;
use Pimcore\Bundle\ComparisonBundle\Export\DiffFilter;
use PHPUnit\Framework\TestCase;

/**
 * DiffFilter (FR-CMP-017) and DiffExporter (FR-CMP-021/022): server-side filtering by mode + query
 * and XLSX/JSON serialization of the (filtered) tree.
 */
final class ExportTest extends TestCase
{
    /** @return list<FieldDiff> */
    private function sample(): array
    {
        return [
            new FieldDiff('sku', 'SKU', 'input', DiffStatus::CHANGED, 'A', 'B'),
            new FieldDiff('name', 'Name', 'input', DiffStatus::EQUAL, 'X', 'X'),
            new FieldDiff('price', 'Price', 'numeric', DiffStatus::ONLY_LEFT, '9', null),
            new FieldDiff('loc', 'Localized', 'localizedfields', DiffStatus::CHANGED, null, null, null, [
                new FieldDiff('name.en', 'Name [en]', 'input', DiffStatus::CHANGED, 'Hi', 'Ho'),
                new FieldDiff('name.de', 'Name [de]', 'input', DiffStatus::EQUAL, 'Da', 'Da'),
            ]),
        ];
    }

    public function testDifferencesModeDropsEqualLeaves(): void
    {
        $out = (new DiffFilter())->apply($this->sample(), DiffFilter::MODE_DIFFERENCES);
        $names = array_map(static fn (FieldDiff $f) => $f->name, $out);
        self::assertContains('sku', $names);
        self::assertContains('price', $names);
        self::assertContains('loc', $names);           // container kept (a child differs)
        self::assertNotContains('name', $names);        // equal leaf dropped
        // the container retains only its differing child
        $loc = $out[array_search('loc', $names, true)];
        self::assertCount(1, $loc->children);
        self::assertSame('name.en', $loc->children[0]->name);
    }

    public function testEqualModeKeepsOnlyEqual(): void
    {
        $out = (new DiffFilter())->apply($this->sample(), DiffFilter::MODE_EQUAL);
        $names = array_map(static fn (FieldDiff $f) => $f->name, $out);
        self::assertSame(['name', 'loc'], $names); // 'name' equal leaf + container with the equal child
    }

    public function testFreeTextQueryNarrowsByLabel(): void
    {
        $out = (new DiffFilter())->apply($this->sample(), DiffFilter::MODE_ALL, 'price');
        self::assertCount(1, $out);
        self::assertSame('price', $out[0]->name);
    }

    public function testJsonExportRoundTrips(): void
    {
        $result = new DiffResult(1, 2, 'Product', $this->sample());
        $json = (new DiffExporter())->toJson($result, $result->fields);
        $decoded = json_decode($json, true);
        self::assertSame(1, $decoded['leftId']);
        self::assertSame('Product', $decoded['className']);
        self::assertSame(5, $decoded['summary']['total']); // sku,name,price + 2 localized children
        self::assertSame(3, $decoded['summary']['differing']); // sku(changed), price(only-left), name.en(changed)
        self::assertCount(4, $decoded['fields']);
    }

    public function testXlsxExportProducesValidWorkbook(): void
    {
        $result = new DiffResult(1, 2, 'Product', $this->sample());
        $bytes = (new DiffExporter())->toXlsx($result, $result->fields);
        self::assertNotSame('', $bytes);
        self::assertStringStartsWith("PK\x03\x04", $bytes, 'XLSX is a ZIP container');
    }
}
