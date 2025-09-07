<?php

namespace EasyCorp\Bundle\EasyAdminBundle\DependencyInjection;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\ActionsExtension;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ActionExtensionRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects all services tagged with 'ea.action_extension' and registers them
 * in the ActionExtensionRegistry service.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class CollectActionExtensionsPass implements CompilerPassInterface
{
    public const ACTION_EXTENSION_TAG = 'ea.action_extension';

    public function process(ContainerBuilder $container): void
    {
        $taggedServices = $container->findTaggedServiceIds(self::ACTION_EXTENSION_TAG);
        
        if (empty($taggedServices)) {
            return;
        }

        $extensions = [];
        foreach ($taggedServices as $serviceId => $tags) {
            $serviceDefinition = $container->getDefinition($serviceId);
            $serviceClass = $serviceDefinition->getClass();
            
            if (null === $serviceClass) {
                throw new \RuntimeException(sprintf('The service "%s" tagged with "%s" must define its class.', $serviceId, self::ACTION_EXTENSION_TAG));
            }
            
            if (!is_subclass_of($serviceClass, ActionsExtension::class)) {
                throw new \RuntimeException(sprintf('The service "%s" tagged with "%s" must implement "%s".', $serviceId, self::ACTION_EXTENSION_TAG, ActionsExtension::class));
            }
            
            $priority = $tags[0]['priority'] ?? 0;
            $extensions[] = [
                'service' => new Reference($serviceId),
                'priority' => $priority,
                'serviceId' => $serviceId,
            ];
        }

        usort($extensions, function ($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return strcmp($a['serviceId'], $b['serviceId']);
            }
            return $b['priority'] - $a['priority'];
        });

        $orderedExtensions = array_map(fn($extension) => $extension['service'], $extensions);

        $registryDefinition = new Definition(ActionExtensionRegistry::class);
        $registryDefinition->setArguments([$orderedExtensions]);
        $registryDefinition->setPublic(false);
        
        $container->setDefinition(ActionExtensionRegistry::class, $registryDefinition);
    }
}