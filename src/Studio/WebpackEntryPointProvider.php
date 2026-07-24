<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Studio;

use Pimcore\Bundle\StudioUiBundle\Webpack\WebpackEntryPointProviderInterface;

/**
 * Registers the built Comparison Studio UI plugin (the module-federation remote produced by
 * `assets/` via rsbuild) with the host Studio shell. The build writes a per-build UUID directory
 * under public/build/<uuid>/ containing entrypoints.json (+ the injected `exposeRemote` entry that
 * exposes the remote container). Mirrors the DataSpine provider verbatim.
 */
final class WebpackEntryPointProvider implements WebpackEntryPointProviderInterface
{
    public function getEntryPointsJsonLocations(): array
    {
        return glob(__DIR__ . '/../../public/build/*/entrypoints.json') ?: [];
    }

    public function getEntryPoints(): array
    {
        return ['exposeRemote'];
    }

    public function getOptionalEntryPoints(): array
    {
        return [];
    }
}
