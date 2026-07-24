<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle;

use Pimcore\Bundle\ComparisonBundle\Security\ComparisonPermissions;
use Pimcore\Extension\Bundle\Installer\AbstractInstaller;
use Pimcore\Model\User\Permission\Definition as PermissionDefinition;
use Psr\Log\LoggerInterface;

/**
 * The bundle is stateless (no tables, no persisted data). Install only registers the permission
 * catalogue. Uninstall deliberately leaves the permission definitions in place so role assignments
 * survive an uninstall/reinstall.
 */
class Installer extends AbstractInstaller
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    public function install(): void
    {
        $this->registerPermissions();
    }

    public function uninstall(): void
    {
        // Permission definitions are intentionally kept so a reinstall preserves role grants.
    }

    public function isInstalled(): bool
    {
        return PermissionDefinition::getByKey(ComparisonPermissions::COMPARISON) !== null;
    }

    public function canBeInstalled(): bool
    {
        return !$this->isInstalled();
    }

    public function canBeUninstalled(): bool
    {
        return $this->isInstalled();
    }

    private function registerPermissions(): void
    {
        foreach (ComparisonPermissions::all() as $key) {
            if (PermissionDefinition::getByKey($key) !== null) {
                continue;
            }

            try {
                $definition = PermissionDefinition::create($key);
                $definition->setCategory(ComparisonPermissions::CATEGORY);
                $definition->save();
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf('[Comparison] Could not register permission "%s": %s', $key, $e->getMessage()));
            }
        }
    }
}
