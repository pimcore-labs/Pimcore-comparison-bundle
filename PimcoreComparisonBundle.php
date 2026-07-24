<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle;

use Pimcore\Bundle\ComparisonBundle\DependencyInjection\PimcoreComparisonExtension;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Installer\InstallerInterface;

/**
 * PimcoreComparisonBundle — a Studio capability that compares two data objects of the same class
 * side by side as a type-aware, filterable diff table. Read-only in v1, stateless, permission-safe.
 *
 * @see _specs/base-idea.md for the concept and _specs/requirements.md for the requirement ids.
 */
class PimcoreComparisonBundle extends AbstractPimcoreBundle
{
    public function getInstaller(): InstallerInterface
    {
        return $this->container->get(Installer::class);
    }

    public function getContainerExtension(): PimcoreComparisonExtension
    {
        return new PimcoreComparisonExtension();
    }
}
