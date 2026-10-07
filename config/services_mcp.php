<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\DependencyInjection\Compiler\McpServerPass;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpCallContext;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Read\CollectionReader;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Security\McpGrantResolver;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Server\McpToolRunner;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Server\ReadTools;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

// services that depend on mcp/sdk; they're only loaded when symfony/mcp-bundle is enabled
return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(McpToolRunner::class)
            ->arg(0, service(McpCallContext::class))
            ->arg(1, service(McpGrantResolver::class))
            ->arg(2, new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->arg(3, new Reference('easyadmin.mcp.rate_limiter', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->arg(4, param(McpServerPass::TOOL_SCOPES_PARAMETER))
            ->arg(5, param('easyadmin.mcp.limits.time_limit'))

        ->set(ReadTools::class)
            ->arg(0, service(McpToolRunner::class))
            ->arg(1, service(CollectionReader::class))
            ->tag('mcp.tool', ['method' => 'listCollections'])
            ->tag('mcp.tool', ['method' => 'describeCollection'])
            ->tag('mcp.tool', ['method' => 'listRecords'])
            ->tag('mcp.tool', ['method' => 'getRecord'])
    ;
};
