<?php

namespace EasyCorp\Bundle\EasyAdminBundle\DependencyInjection\Compiler;

use EasyCorp\Bundle\EasyAdminBundle\Mcp\Attribute\McpScope;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Security\McpGrantResolver;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Server\McpToolScopeHandler;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects the OAuth scope declared with #[McpScope] by each MCP tool and adds
 * McpToolScopeHandler to every MCP server of the application.
 *
 * @internal
 */
final class McpServerPass implements CompilerPassInterface
{
    public const TOOL_SCOPES_PARAMETER = 'easyadmin.mcp.tool_scopes';

    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(McpTool::class)) {
            return;
        }

        $toolScopes = [];
        foreach ($container->findTaggedServiceIds('mcp.tool') as $serviceId => $tags) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($serviceId)->getClass() ?? $serviceId);
            if (!\is_string($class) || null === $reflectionClass = $container->getReflectionClass($class, false)) {
                continue;
            }

            foreach ($tags as $tag) {
                $methodName = $tag['method'] ?? '__invoke';
                if (!$reflectionClass->hasMethod($methodName)) {
                    continue;
                }

                $method = $reflectionClass->getMethod($methodName);
                $scopeAttribute = ($method->getAttributes(McpScope::class)[0] ?? $reflectionClass->getAttributes(McpScope::class)[0] ?? null)?->newInstance();
                $toolAttribute = ($method->getAttributes(McpTool::class)[0] ?? null)?->newInstance();
                if (null === $scopeAttribute || null === $toolAttribute) {
                    continue;
                }

                $toolScopes[$toolAttribute->name ?? $methodName] = $scopeAttribute->getOAuthScope();
            }
        }

        $container->setParameter(self::TOOL_SCOPES_PARAMETER, $toolScopes);

        foreach ($container->getDefinitions() as $serviceId => $definition) {
            if (1 !== preg_match('/^mcp\.server\.([a-zA-Z0-9_-]+)\.builder$/', $serviceId, $matches)) {
                continue;
            }

            $handlerId = sprintf('easyadmin.mcp.tool_scope_handler.%s', $matches[1]);
            $container->setDefinition($handlerId, (new Definition(McpToolScopeHandler::class))
                ->setArguments([
                    new Reference(sprintf('mcp.server.%s.registry', $matches[1])),
                    new Reference(McpGrantResolver::class),
                    $toolScopes,
                ]));

            // custom handlers run before the built-in ones of the SDK
            $definition->addMethodCall('addRequestHandler', [new Reference($handlerId)]);
        }
    }
}
