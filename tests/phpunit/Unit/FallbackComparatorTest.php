<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Tests\Unit;

use Pimcore\Bundle\ComparisonBundle\Comparator\ComparatorRegistry;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparisonContext;
use Pimcore\Bundle\ComparisonBundle\Comparator\FallbackComparator;
use Pimcore\Bundle\ComparisonBundle\Comparison\Normalizer;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\EqualComparisonInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Concrete;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit coverage of the last-resort {@see FallbackComparator} (FR-CMP-012): it supports every
 * field type, renders via getVersionPreview, and decides equality via EqualComparisonInterface when
 * the type provides it (falling back to the normalizer over the rendered strings otherwise, and on a
 * throwing isEqual). No Pimcore kernel required.
 */
final class FallbackComparatorTest extends TestCase
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

    private function field(string $name = 'custom', string $title = 'Custom'): Input
    {
        $input = new Input();
        $input->setName($name);
        $input->setTitle($title);

        return $input;
    }

    public function testSupportsEveryFieldDefinition(): void
    {
        $cmp = new FallbackComparator();
        self::assertTrue($cmp->supports($this->field()));
        self::assertTrue($cmp->supports($this->createStub(Data::class)));
    }

    public function testEqualScalarsProduceEqualStatus(): void
    {
        $cmp = new FallbackComparator();
        $diff = $cmp->compare('x', 'x', $this->field(), $this->context());

        self::assertSame(DiffStatus::EQUAL, $diff->status);
        self::assertSame('x', $diff->leftDisplay);
        self::assertSame('x', $diff->rightDisplay);
    }

    public function testChangedScalarsProduceChangedStatusWithDisplays(): void
    {
        $cmp = new FallbackComparator();
        $diff = $cmp->compare('x', 'y', $this->field(), $this->context());

        self::assertSame(DiffStatus::CHANGED, $diff->status);
        self::assertSame('x', $diff->leftDisplay);
        self::assertSame('y', $diff->rightDisplay);
    }

    public function testPresenceStatuses(): void
    {
        $cmp = new FallbackComparator();
        $ctx = $this->context();
        $fd = $this->field();

        self::assertSame(DiffStatus::ONLY_LEFT, $cmp->compare('x', null, $fd, $ctx)->status);
        self::assertSame(DiffStatus::ONLY_RIGHT, $cmp->compare(null, 'y', $fd, $ctx)->status);
        self::assertSame(DiffStatus::EQUAL, $cmp->compare(null, null, $fd, $ctx)->status);
    }

    public function testUsesEqualComparisonInterfaceEvenWhenDisplaysDiffer(): void
    {
        $cmp = new FallbackComparator();
        $fd = new AlwaysEqualFallbackField();
        $fd->setName('custom');

        $diff = $cmp->compare('x', 'y', $fd, $this->context());

        // isEqual() reports equal, so the differing rendered strings are still EQUAL.
        self::assertSame(DiffStatus::EQUAL, $diff->status);
        self::assertSame('x', $diff->leftDisplay);
        self::assertSame('y', $diff->rightDisplay);
    }

    public function testThrowingIsEqualFallsBackToRenderedStringComparison(): void
    {
        $cmp = new FallbackComparator();
        $fd = new ThrowingEqualFallbackField();
        $fd->setName('custom');
        $ctx = $this->context();

        // isEqual() throws, so equality is decided by the normalizer over the rendered strings.
        self::assertSame(DiffStatus::EQUAL, $cmp->compare('x', 'x', $fd, $ctx)->status);
        self::assertSame(DiffStatus::CHANGED, $cmp->compare('x', 'y', $fd, $ctx)->status);
    }
}

/**
 * Test fixture: a field type whose isEqual() always reports equal, to prove the fallback uses
 * EqualComparisonInterface rather than the rendered strings.
 */
final class AlwaysEqualFallbackField extends Input implements EqualComparisonInterface
{
    public function isEqual(mixed $oldValue, mixed $newValue): bool
    {
        return true;
    }
}

/**
 * Test fixture: a field type whose isEqual() throws, to prove the fallback catches the error and
 * compares the rendered strings via the normalizer instead.
 */
final class ThrowingEqualFallbackField extends Input implements EqualComparisonInterface
{
    public function isEqual(mixed $oldValue, mixed $newValue): bool
    {
        throw new \RuntimeException('cannot compare');
    }
}
