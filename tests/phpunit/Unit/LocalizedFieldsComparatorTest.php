<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Tests\Unit;

use Pimcore\Bundle\ComparisonBundle\Comparator\ComparatorRegistry;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparisonContext;
use Pimcore\Bundle\ComparisonBundle\Comparator\LocalizedFieldsComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\ScalarComparator;
use Pimcore\Bundle\ComparisonBundle\Comparison\Normalizer;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\Concrete;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit coverage of the container LocalizedFieldsComparator (FR-CMP-008): it recurses into a real
 * ScalarComparator once per locale, producing one child FieldDiff per (field, locale), and a
 * translation gap on one side surfaces as only-left / only-right through the child comparator.
 *
 * No Pimcore kernel required. The real localized-value container ({@see \Pimcore\Model\DataObject\Localizedfield})
 * is `final` and cannot be stubbed, so a lightweight duck-typed double exposing getLocalizedValue() is
 * used — the comparator reads containers via that accessor.
 */
final class LocalizedFieldsComparatorTest extends TestCase
{
    /** @param list<string> $locales */
    private function context(array $locales, ComparatorRegistry $registry): ComparisonContext
    {
        return new ComparisonContext(
            $this->createStub(Concrete::class),
            $this->createStub(Concrete::class),
            $locales,
            new Normalizer([]),
            $registry,
        );
    }

    private function localizedFields(): Localizedfields
    {
        $input = new Input();
        $input->setName('name');
        $input->setTitle('Name');

        $lf = new Localizedfields();
        $lf->setName('localizedfields');
        $lf->setChildren([$input]);

        return $lf;
    }

    /**
     * A duck-typed stand-in for a Localizedfield container: getLocalizedValue() returns the value for
     * the requested language (field name ignored — the fixture has a single field).
     *
     * @param array<string, mixed> $byLocale
     */
    private function container(array $byLocale): object
    {
        return new class($byLocale) {
            /** @param array<string, mixed> $byLocale */
            public function __construct(private array $byLocale)
            {
            }

            public function getLocalizedValue(string $name, ?string $language = null): mixed
            {
                return $this->byLocale[$language] ?? null;
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

    public function testSupportsLocalizedfieldsOnly(): void
    {
        $cmp = new LocalizedFieldsComparator();

        self::assertTrue($cmp->supports($this->localizedFields()));

        $input = new Input();
        $input->setName('sku');
        self::assertFalse($cmp->supports($input));
    }

    public function testComparesEachLocaleAsItsOwnChildRow(): void
    {
        $registry = new ComparatorRegistry([new ScalarComparator()]);
        $ctx = $this->context(['en', 'de'], $registry);
        $lf = $this->localizedFields();

        $leftLf = $this->container(['en' => 'Trail Pro', 'de' => 'Trail Pro DE']);
        $rightLf = $this->container(['en' => 'Trail Pro 29', 'de' => 'Trail Pro DE']); // en changed, de equal

        $result = (new LocalizedFieldsComparator())->compare($leftLf, $rightLf, $lf, $ctx);

        // Parent aggregates: at least one locale changed -> container changed.
        self::assertSame(DiffStatus::CHANGED, $result->status);
        self::assertSame('localizedfields', $result->fieldtype);
        self::assertNull($result->leftDisplay);
        self::assertNull($result->rightDisplay);
        self::assertCount(2, $result->children);

        $rows = $this->byName($result->children);
        self::assertArrayHasKey('name.en', $rows);
        self::assertArrayHasKey('name.de', $rows);

        self::assertSame(DiffStatus::CHANGED, $rows['name.en']->status);
        self::assertSame(DiffStatus::EQUAL, $rows['name.de']->status);

        self::assertSame('Trail Pro', $rows['name.en']->leftDisplay);
        self::assertSame('Trail Pro 29', $rows['name.en']->rightDisplay);

        // Locale recorded in meta and carried in the label.
        self::assertSame('en', $rows['name.en']->meta['locale']);
        self::assertSame('en', $rows['name.en']->meta['language']);
        self::assertSame('de', $rows['name.de']->meta['locale']);
        self::assertStringContainsString('[en]', $rows['name.en']->label);
        self::assertStringContainsString('[de]', $rows['name.de']->label);
    }

    public function testTranslationGapSurfacesAsOnlyLeft(): void
    {
        $registry = new ComparatorRegistry([new ScalarComparator()]);
        $ctx = $this->context(['en', 'de'], $registry);
        $lf = $this->localizedFields();

        $leftLf = $this->container(['en' => 'Trail Pro', 'de' => 'Trail Pro DE']);
        $rightLf = $this->container(['de' => 'Trail Pro DE']); // en missing on the right side

        $result = (new LocalizedFieldsComparator())->compare($leftLf, $rightLf, $lf, $ctx);
        $rows = $this->byName($result->children);

        self::assertSame(DiffStatus::CHANGED, $result->status);
        self::assertSame(DiffStatus::ONLY_LEFT, $rows['name.en']->status, 'missing right translation is only-left');
        self::assertSame(DiffStatus::EQUAL, $rows['name.de']->status);
        self::assertSame('en', $rows['name.en']->meta['locale']);
    }

    public function testContainerWithNoInnerFieldsIsEqualWithNoChildren(): void
    {
        $registry = new ComparatorRegistry([new ScalarComparator()]);
        $ctx = $this->context(['en', 'de'], $registry);

        $lf = new Localizedfields();
        $lf->setName('localizedfields');
        $lf->setChildren([]); // no inner field definitions

        $result = (new LocalizedFieldsComparator())->compare(null, null, $lf, $ctx);

        self::assertSame(DiffStatus::EQUAL, $result->status);
        self::assertSame([], $result->children);
    }
}
