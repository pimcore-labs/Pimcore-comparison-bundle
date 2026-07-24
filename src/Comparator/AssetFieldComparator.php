<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Comparator;

use Pimcore\Bundle\ComparisonBundle\Diff\DiffStatus;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Diffs asset-bearing fieldtypes — single image, hotspot image, and image gallery (FR-CMP-013) — by
 * asset IDENTITY (id / full path). Binary/perceptual hash comparison is deliberately out of scope
 * for v1 (P2). The value handed in may be a single Asset, a hotspot-image wrapper exposing
 * getImage(), an image gallery exposing getItems(), an array of any of these, or null.
 *
 * The ordered list of asset identities decides the verdict: both empty → EQUAL, one side empty →
 * ONLY_LEFT/ONLY_RIGHT, else identical key lists → EQUAL, otherwise CHANGED. Per-side id/path pairs
 * ride along in {@see FieldDiff::$meta} to drive the thumbnail preview + id/path check in the UI.
 * Ordering is not distinguished for images in v1 (no REORDERED).
 */
#[AutoconfigureTag('pimcore.comparison.field_comparator', ['priority' => 35])]
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\AsFeature(id: 'comparators.asset-field', group: 'comparators', name: 'Asset / image field comparator', description: 'Compares image / hotspotimage / imageGallery fields by asset id and path.', status: \Pimcore\Bundle\ComparisonBundle\Feature\FeatureStatus::BETA, openGaps: ['Binary hash comparison deferred to P2; v1 compares asset id/path'], specRefs: ['FR-CMP-013'], dependsOn: ['core.comparator-registry'], since: '2026-07-24', backendOnly: true)]
final class AssetFieldComparator extends AbstractFieldComparator
{
    private const ASSET_FIELDTYPES = ['image', 'hotspotimage', 'imageGallery'];

    public function supports(Data $fieldDefinition): bool
    {
        return in_array($fieldDefinition->getFieldtype(), self::ASSET_FIELDTYPES, true);
    }

    public function compare(
        mixed $leftValue,
        mixed $rightValue,
        Data $fieldDefinition,
        ComparisonContext $context,
    ): FieldDiff {
        $leftAssets = $this->toAssets($leftValue);
        $rightAssets = $this->toAssets($rightValue);

        $leftKeys = array_map(fn (object $a): string => $this->assetKey($a), $leftAssets);
        $rightKeys = array_map(fn (object $a): string => $this->assetKey($a), $rightAssets);

        $leftEmpty = $leftAssets === [];
        $rightEmpty = $rightAssets === [];

        if ($leftEmpty && $rightEmpty) {
            $status = DiffStatus::EQUAL;
        } elseif ($leftEmpty) {
            $status = DiffStatus::ONLY_RIGHT;
        } elseif ($rightEmpty) {
            $status = DiffStatus::ONLY_LEFT;
        } elseif ($leftKeys === $rightKeys) {
            $status = DiffStatus::EQUAL;
        } else {
            $status = DiffStatus::CHANGED;
        }

        $leftEntries = array_map(fn (object $a): array => $this->entry($a), $leftAssets);
        $rightEntries = array_map(fn (object $a): array => $this->entry($a), $rightAssets);

        $meta = [
            'left' => $leftEntries,
            'right' => $rightEntries,
        ];

        $leftDisplay = $this->display($leftEntries);
        $rightDisplay = $this->display($rightEntries);

        return $this->diff($fieldDefinition, $status, $leftDisplay, $rightDisplay, null, [], $meta);
    }

    /**
     * Normalize any asset-bearing value to an ordered list of asset-like objects. Accepts null, a
     * single asset (has getId), a hotspot-image wrapper (getImage), an image gallery (getItems, each
     * item exposing getImage()/getAsset()), or an array of any of these. Unresolvable / null entries
     * are skipped.
     *
     * @return list<object>
     */
    private function toAssets(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        // Image gallery: an ordered collection of hotspot-image items.
        if (is_object($value) && method_exists($value, 'getItems')) {
            $assets = [];
            foreach ((array) $value->getItems() as $item) {
                $asset = $this->unwrap($item);
                if ($asset !== null) {
                    $assets[] = $asset;
                }
            }

            return $assets;
        }

        if (is_array($value)) {
            $assets = [];
            foreach ($value as $entry) {
                $asset = $this->unwrap($entry);
                if ($asset !== null) {
                    $assets[] = $asset;
                }
            }

            return $assets;
        }

        $asset = $this->unwrap($value);

        return $asset !== null ? [$asset] : [];
    }

    /**
     * Resolve a raw entry to an asset-like object, unwrapping a hotspot-image wrapper (getImage) or a
     * gallery item (getImage/getAsset). A bare asset (has getId) is returned as-is.
     */
    private function unwrap(mixed $entry): ?object
    {
        if (!is_object($entry)) {
            return null;
        }

        if (method_exists($entry, 'getImage')) {
            $image = $entry->getImage();
            if (is_object($image)) {
                return $image;
            }

            return null;
        }
        if (method_exists($entry, 'getAsset')) {
            $asset = $entry->getAsset();
            if (is_object($asset)) {
                return $asset;
            }

            return null;
        }

        if (method_exists($entry, 'getId')) {
            return $entry;
        }

        return null;
    }

    /** A stable identity key: id, else full path / path, else spl_object_id. */
    private function assetKey(object $asset): string
    {
        if (method_exists($asset, 'getId')) {
            $id = $asset->getId();
            if ($id !== null) {
                return 'id:' . $id;
            }
        }
        if (method_exists($asset, 'getFullPath')) {
            $path = $asset->getFullPath();
            if (is_string($path) && $path !== '') {
                return 'path:' . $path;
            }
        }
        if (method_exists($asset, 'getPath')) {
            $path = $asset->getPath();
            if (is_string($path) && $path !== '') {
                return 'path:' . $path;
            }
        }

        return 'spl:' . spl_object_id($asset);
    }

    /**
     * The per-side meta entry for one asset: its id and full path (either may be null), driving the
     * thumbnail preview + id/path check in the UI.
     *
     * @return array{id: int|string|null, path: string|null}
     */
    private function entry(object $asset): array
    {
        $id = null;
        if (method_exists($asset, 'getId')) {
            $id = $asset->getId();
        }

        $path = null;
        if (method_exists($asset, 'getFullPath')) {
            $fullPath = $asset->getFullPath();
            if (is_string($fullPath) && $fullPath !== '') {
                $path = $fullPath;
            }
        }
        if ($path === null && method_exists($asset, 'getPath')) {
            $plainPath = $asset->getPath();
            if (is_string($plainPath) && $plainPath !== '') {
                $path = $plainPath;
            }
        }

        return ['id' => $id, 'path' => $path];
    }

    /**
     * Comma-joined paths (or `Asset #id`) for the export fallback.
     *
     * @param list<array{id: int|string|null, path: string|null}> $entries
     */
    private function display(array $entries): ?string
    {
        if ($entries === []) {
            return null;
        }

        $labels = array_map(static function (array $entry): string {
            if ($entry['path'] !== null) {
                return $entry['path'];
            }
            if ($entry['id'] !== null) {
                return 'Asset #' . $entry['id'];
            }

            return 'Asset';
        }, $entries);

        return implode(', ', $labels);
    }
}
