<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Feature\DependencyInjection;

use Pimcore\Bundle\ComparisonBundle\Feature\FeatureRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Compiles every `#[AsFeature]` declaration (autoconfigured onto the `comparison.feature` tag) into
 * the {@see FeatureRegistry} at build time. Runtime cost is zero, and a duplicate feature id is a
 * container-compilation error.
 *
 * @internal
 */
final class FeatureRegistryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(FeatureRegistry::class)) {
            return;
        }

        $declarations = [];
        foreach ($container->findTaggedServiceIds('comparison.feature') as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            foreach ($tags as $tag) {
                $id = (string) ($tag['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                if (isset($declarations[$id])) {
                    throw new LogicException(sprintf(
                        'Duplicate feature id "%s": declared on %s and %s. Feature ids must be unique.',
                        $id, $declarations[$id]['declaredIn'], $class
                    ));
                }
                $declarations[$id] = [
                    'id' => $id,
                    'group' => (string) ($tag['group'] ?? ''),
                    'name' => (string) ($tag['name'] ?? ''),
                    'description' => (string) ($tag['description'] ?? ''),
                    'status' => (string) ($tag['status'] ?? 'planned'),
                    'openGaps' => $this->decode($tag['openGaps'] ?? '[]'),
                    'specRefs' => $this->decode($tag['specRefs'] ?? '[]'),
                    'dependsOn' => $this->decode($tag['dependsOn'] ?? '[]'),
                    'since' => (string) ($tag['since'] ?? ''),
                    'backendOnly' => (bool) ($tag['backendOnly'] ?? false),
                    'declaredIn' => $class,
                ];
            }
        }

        $container->getDefinition(FeatureRegistry::class)
            ->setArgument(0, array_values($declarations));
    }

    /** @return string[] */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }
}
