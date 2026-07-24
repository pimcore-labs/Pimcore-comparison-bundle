# Extending the diff engine — the Comparator SPI

Every field is diffed by a **comparator**. The bundle ships comparators for all core fieldtypes; you
add or override behaviour by registering your own. This is the primary extension point (base-idea §6.2).

## How resolution works

`ComparatorRegistry` receives all comparators **priority-first** (the `priority` tag attribute, highest
wins) and returns the **first** one whose `supports()` returns true for a field. The `FallbackComparator`
sits at priority `-100` and supports everything, so resolution never fails. To override a core comparator
for a fieldtype, register yours at a **higher priority**.

Built-in priorities:

| Priority | Comparators |
|---:|---|
| 40 | Localized, FieldCollection, ObjectBrick, ClassificationStore (containers) |
| 35 | AssetField (image / hotspotimage / imageGallery) |
| 30 | TextDiff (textarea / wysiwyg), Relation |
| 10 | Scalar (input / numeric / select / checkbox / date / …) |
| −100 | Fallback (getVersionPreview) |

## The interface

```php
namespace Pimcore\Bundle\ComparisonBundle\Comparator;

interface FieldComparatorInterface
{
    public function supports(\Pimcore\Model\DataObject\ClassDefinition\Data $fieldDefinition): bool;

    public function compare(
        mixed $leftValue,
        mixed $rightValue,
        \Pimcore\Model\DataObject\ClassDefinition\Data $fieldDefinition,
        ComparisonContext $context,
    ): \Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
}
```

Extend `AbstractFieldComparator` for helpers: `label()`, `render()` (reuses the fielddefinition's own
`getVersionPreview()`), `isEmpty()`, `statusFor()` (the equal / changed / only-left / only-right decision),
and `diff()` (builds a `FieldDiff`).

`FieldDiff` fields: `name`, `label`, `fieldtype`, `status` (a `DiffStatus`), `leftDisplay`, `rightDisplay`,
`inlineDiff` (token list for inline text diff), `children` (nested rows for containers), `meta` (free-form,
e.g. relation chip lists, language codes).

`DiffStatus`: `equal`, `changed`, `only-left`, `only-right`, `reordered`, `not-comparable`, `hidden`.

## A complete custom-fieldtype example

Say your project has a custom `geopoint`-like fieldtype whose value is an object with `getLatitude()` /
`getLongitude()`, and you want a distance-aware diff: equal when the two points are within a tolerance,
otherwise changed, showing the coordinates and the distance in the meta.

```php
<?php

declare(strict_types=1);

namespace App\Comparison;

use Pimcore\Bundle\ComparisonBundle\Comparator\AbstractFieldComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparisonContext;
use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Distance-aware diff for the custom `geopoint` fieldtype. Registered at priority 50 so it wins over
 * the core Scalar/Fallback comparators for this type.
 */
#[AutoconfigureTag('pimcore.comparison.field_comparator', ['priority' => 50])]
final class GeopointComparator extends AbstractFieldComparator
{
    private const TOLERANCE_METERS = 5.0;

    public function supports(Data $fieldDefinition): bool
    {
        return $fieldDefinition->getFieldtype() === 'geopoint';
    }

    public function compare(mixed $leftValue, mixed $rightValue, Data $fieldDefinition, ComparisonContext $context): FieldDiff
    {
        $leftEmpty = $this->isEmpty($leftValue);
        $rightEmpty = $this->isEmpty($rightValue);

        if ($leftEmpty && $rightEmpty) {
            return $this->diff($fieldDefinition, DiffStatus::EQUAL, null, null);
        }
        if ($leftEmpty) {
            return $this->diff($fieldDefinition, DiffStatus::ONLY_RIGHT, null, $this->format($rightValue));
        }
        if ($rightEmpty) {
            return $this->diff($fieldDefinition, DiffStatus::ONLY_LEFT, $this->format($leftValue), null);
        }

        $distance = $this->haversine($leftValue, $rightValue);
        $status = $distance <= self::TOLERANCE_METERS ? DiffStatus::EQUAL : DiffStatus::CHANGED;

        return $this->diff(
            $fieldDefinition,
            $status,
            $this->format($leftValue),
            $this->format($rightValue),
            null,
            [],
            ['distanceMeters' => round($distance, 1)],
        );
    }

    private function format(object $p): string
    {
        return sprintf('%.5f, %.5f', $p->getLatitude(), $p->getLongitude());
    }

    private function haversine(object $a, object $b): float
    {
        $r = 6_371_000.0;
        $dLat = deg2rad($b->getLatitude() - $a->getLatitude());
        $dLon = deg2rad($b->getLongitude() - $a->getLongitude());
        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($a->getLatitude())) * cos(deg2rad($b->getLatitude())) * sin($dLon / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($h)));
    }
}
```

That's it — no service configuration beyond the attribute. Because the bundle registers the tag via
`#[AutoconfigureTag]` on the class, autowiring picks it up. Drop the class under `App\Comparison\` (or any
autoconfigured namespace) and it participates immediately; the higher priority makes it win for `geopoint`.

### Recursing into container values

Container comparators (localized, field-collection, brick, classification store) resolve a **child**
comparator per inner field via the registry on the context:

```php
$childComparator = $context->registry->resolve($childFieldDefinition);
$childDiff = $childComparator->compare($childLeft, $childRight, $childFieldDefinition, $context);
// collect $childDiff into the parent FieldDiff's children
```

`ComparisonContext` also carries `left`/`right` objects (for relation/asset resolution), the enabled
`locales`, and the shared `Normalizer` (`$context->normalizer->scalarEquals($a, $b)`).

## Events

- `PreComparisonEvent` — mutate options or veto before the walk (roadmap).
- `PostComparisonEvent` — mutate the result, e.g. inject a similarity score for dedup tooling (roadmap).

## Testing your comparator

Mirror the bundle's unit tests (`tests/phpunit/Unit/*ComparatorTest.php`): build a `ComparisonContext`
with stubbed objects, a real `Normalizer`, and a `ComparatorRegistry`, then assert `status`,
`leftDisplay`/`rightDisplay`, `inlineDiff`, `children`, and `meta` for representative value pairs.
