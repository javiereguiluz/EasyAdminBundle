<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\CacheKey;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Decides whether a CRUD controller is exposed to MCP clients by combining the
 * dashboard exposure mode, the controller PHP attributes and the Crud methods.
 *
 * @experimental
 */
final class McpExposureResolver
{
    /** @var array<class-string, array{exposed: bool, readOnly: bool, alias: string|null}>|null */
    private ?array $attributeExposures = null;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly AdminRouteGeneratorInterface $adminRouteGenerator,
    ) {
    }

    /**
     * @param class-string $crudControllerFqcn
     *
     * @return McpCrudExposure|null the exposure of the CRUD controller, or null if it's not exposed
     */
    public function resolve(McpExposureMode $mode, string $crudControllerFqcn, CrudDto $crudDto): ?McpCrudExposure
    {
        // the Crud methods win over the attributes because they can depend on the user or the environment
        $exposure = $crudDto->getMcpExposure() ?? $this->getAttributeExposure($crudControllerFqcn);

        if (null === $exposure) {
            return McpExposureMode::All === $mode ? McpCrudExposure::exposed() : null;
        }

        return $exposure->exposed ? $exposure : null;
    }

    /**
     * @param class-string $crudControllerFqcn
     */
    private function getAttributeExposure(string $crudControllerFqcn): ?McpCrudExposure
    {
        if (null === $this->attributeExposures) {
            $cacheItem = $this->cache->getItem(CacheKey::CRUD_FQCN_TO_MCP_EXPOSURE);
            // a missing cache item would make #[ExcludeFromMcp] stop working, so the cache is generated again
            if (!$cacheItem->isHit()) {
                $this->adminRouteGenerator->generateAll();
                $cacheItem = $this->cache->getItem(CacheKey::CRUD_FQCN_TO_MCP_EXPOSURE);
            }

            if (!$cacheItem->isHit() || !\is_array($exposures = $cacheItem->get())) {
                throw new \LogicException('The MCP exposure of the CRUD controllers is not available in the EasyAdmin cache. Clear the cache of the application.');
            }

            $this->attributeExposures = $exposures;
        }

        $attributeExposure = $this->attributeExposures[$crudControllerFqcn] ?? null;
        if (!\is_array($attributeExposure)) {
            return null;
        }

        if (true !== ($attributeExposure['exposed'] ?? null)) {
            return McpCrudExposure::excluded();
        }

        $alias = $attributeExposure['alias'] ?? null;

        return McpCrudExposure::exposed(true === ($attributeExposure['readOnly'] ?? null), \is_string($alias) ? $alias : null);
    }
}
