<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle;

use Pimcore\Bundle\ComparisonBundle\DependencyInjection\PimcoreComparisonExtension;
use Pimcore\Bundle\ComparisonBundle\Feature\Attribute\AsFeature;
use Pimcore\Bundle\ComparisonBundle\Feature\DependencyInjection\FeatureRegistryPass;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Installer\InstallerInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Feature registry: any service carrying #[AsFeature] is tagged so FeatureRegistryPass compiles
        // it into the registry at build time. Arrays are JSON-encoded because tag attributes are scalar.
        $container->registerAttributeForAutoconfiguration(
            AsFeature::class,
            static function (ChildDefinition $definition, AsFeature $attribute): void {
                $definition->addTag('comparison.feature', [
                    'id' => $attribute->id,
                    'group' => $attribute->group,
                    'name' => $attribute->name,
                    'description' => $attribute->description,
                    'status' => $attribute->status->value,
                    'openGaps' => json_encode($attribute->openGaps),
                    'specRefs' => json_encode($attribute->specRefs),
                    'dependsOn' => json_encode($attribute->dependsOn),
                    'since' => $attribute->since,
                    'backendOnly' => $attribute->backendOnly,
                ]);
            }
        );
        $container->addCompilerPass(new FeatureRegistryPass());
    }
}
