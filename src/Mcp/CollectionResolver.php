<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;

/**
 * Finds the dashboard that exposes CRUD controllers to MCP clients and
 * the collections (CRUD controllers) it exposes.
 *
 * @experimental
 */
final class CollectionResolver
{
    /**
     * @param iterable<DashboardControllerInterface> $dashboardControllers
     * @param iterable<CrudControllerInterface>      $crudControllers
     */
    public function __construct(
        private readonly iterable $dashboardControllers,
        private readonly iterable $crudControllers,
        private readonly AdminRouteGeneratorInterface $adminRouteGenerator,
        private readonly McpExposureResolver $exposureResolver,
    ) {
    }

    /**
     * @return array{DashboardControllerInterface, McpExposureMode}|null null when no dashboard exposes CRUD controllers to MCP
     */
    public function findMcpDashboard(): ?array
    {
        $mcpDashboard = null;
        foreach ($this->dashboardControllers as $dashboardController) {
            $mode = $dashboardController->configureDashboard()->getAsDto()->getMcpExposureMode();
            if (null === $mode) {
                continue;
            }

            if (null !== $mcpDashboard) {
                throw new \LogicException(sprintf('Only one dashboard can expose CRUD controllers to MCP, but both "%s" and "%s" call "exposeSelectedCrudsToMcp()" or "exposeAllCrudsToMcp()". Remove those calls from one of them.', $mcpDashboard[0]::class, $dashboardController::class));
            }

            $mcpDashboard = [$dashboardController, $mode];
        }

        return $mcpDashboard;
    }

    /**
     * @return array<string, McpCollection> the collections indexed by their id
     */
    public function getCollections(): array
    {
        if (null === $mcpDashboard = $this->findMcpDashboard()) {
            return [];
        }

        [$dashboardController, $mode] = $mcpDashboard;

        $collections = [];
        foreach ($this->crudControllers as $crudController) {
            // CRUD controllers excluded from the dashboard (e.g. via #[AdminDashboard(allowedControllers: ...)]) don't have routes in it
            if (null === $this->adminRouteGenerator->findRouteName($dashboardController::class, $crudController::class, Action::INDEX)) {
                continue;
            }

            $crudDto = $crudController->configureCrud($dashboardController->configureCrud())->getAsDto();
            if (null === $exposure = $this->exposureResolver->resolve($mode, $crudController::class, $crudDto)) {
                continue;
            }

            $id = $exposure->alias ?? self::getDefaultCollectionId($crudController::class);
            if (isset($collections[$id])) {
                throw new \LogicException(sprintf('The "%s" and "%s" CRUD controllers use the same MCP collection id "%s". Set a different alias in one of them with "exposeToMcp(alias: ...)" or "#[ExposeToMcp(alias: ...)]".', $collections[$id]->crudControllerFqcn, $crudController::class, $id));
            }

            $collections[$id] = new McpCollection($id, $crudController::class, $crudController::getEntityFqcn(), $exposure->readOnly);
        }

        return $collections;
    }

    /**
     * Transforms 'App\Controller\Admin\FooBarCrudController' into 'foo_bar'.
     *
     * @param class-string<CrudControllerInterface> $crudControllerFqcn
     */
    public static function getDefaultCollectionId(string $crudControllerFqcn): string
    {
        $shortName = preg_replace('/(?:Crud)?Controller$/', '', (new \ReflectionClass($crudControllerFqcn))->getShortName());

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
    }
}
