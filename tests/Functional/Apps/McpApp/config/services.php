<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Mcp\CollectionResolver;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpCallContext;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpContextFactory;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Read\CollectionReader;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\RecordLoader;

return static function (ContainerConfigurator $container) {
    $container->parameters()->set('locale', 'en');

    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
    ;

    $services->load('EasyCorp\\Bundle\\EasyAdminBundle\\Tests\\Functional\\Apps\\McpApp\\', '../src/*')
        ->exclude('../{Entity,Field,Kernel.php}');

    $services->load('EasyCorp\\Bundle\\EasyAdminBundle\\Tests\\Functional\\Apps\\McpApp\\Controller\\', '../src/Controller/')
        ->tag('controller.service_arguments');

    // these services are private, so they're made public to get them in the tests
    foreach ([CollectionResolver::class, CollectionReader::class, McpCallContext::class, McpContextFactory::class, RecordLoader::class] as $serviceId) {
        $services->alias('test.'.$serviceId, $serviceId)->public();
    }
};
