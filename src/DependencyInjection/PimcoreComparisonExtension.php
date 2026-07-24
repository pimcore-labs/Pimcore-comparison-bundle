<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class PimcoreComparisonExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('pimcore_comparison.normalization', $config['normalization']);
        $container->setParameter('pimcore_comparison.default_filter', $config['default_filter']);
        $container->setParameter('pimcore_comparison.export.formats', $config['export']['formats']);
        $container->setParameter('pimcore_comparison.excluded_fields', $config['excluded_fields']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'pimcore_comparison';
    }
}
