<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Router;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Controllers;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ControllersDto;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class AdminRouteGenerator implements AdminRouteGeneratorInterface
{
    // the order in which routes are defined here is important because routes
    // are added to the application in the same order and e.g. the path of the
    // 'detail' route collides with the 'new' route and must be defined after it
    private const ROUTES = [
        'index' => [
            'path' => '/',
            'methods' => ['GET'],
        ],
        'new' => [
            'path' => '/new',
            'methods' => ['GET', 'POST'],
        ],
        'batchDelete' => [
            'path' => '/batchDelete',
            'methods' => ['POST'],
        ],
        'autocomplete' => [
            'path' => '/autocomplete',
            'methods' => ['GET'],
        ],
        'edit' => [
            'path' => '/{entityId}/edit',
            'methods' => ['GET', 'POST', 'PATCH'],
        ],
        'delete' => [
            'path' => '/{entityId}/delete',
            'methods' => ['POST'],
        ],
        'detail' => [
            'path' => '/{entityId}',
            'methods' => ['GET'],
        ],
    ];

    public function __construct(
        private iterable $dashboardControllers,
        private iterable $crudControllers,
    ) {
    }

    public function generateAll(): RouteCollection
    {
        $collection = new RouteCollection();
        $addedRouteNames = [];
        foreach ($this->dashboardControllers as $dashboardController) {
            $dashboardFqcn = $dashboardController::class;
            /** @var ControllersDto $controllersDto */
            $controllersDto = method_exists($dashboardController, 'configureControllers') ? $dashboardController::configureControllers()->getAsDto() : Controllers::new()->getAsDto();
            $allowCrudControllersByDefault = $controllersDto->allowByDefault();
            $allowedCrudControllers = $controllersDto->getAllowedControllers();
            $disallowedCrudControllers = $controllersDto->getDisallowedControllers();

            foreach ($this->crudControllers as $crudController) {
                $crudControllerFqcn = $crudController::class;

                if ($allowCrudControllersByDefault && \in_array($crudControllerFqcn, $disallowedCrudControllers, true)) {
                    continue;
                }

                if (!$allowCrudControllersByDefault && !\in_array($crudControllerFqcn, $allowedCrudControllers, true)) {
                    continue;
                }

                foreach (self::ROUTES as $actionName => $actionConfig) {
                    $crudActionRouteName = $this->getRouteName($dashboardFqcn, $crudControllerFqcn, $actionName);
                    $crudActionPath = $this->getRoutePath($dashboardFqcn, $crudControllerFqcn, $actionName);

                    $defaults = [
                        '_controller' => $crudControllerFqcn.'::'.$actionName,
                    ];
                    $options = [
                        EA::ROUTE_CREATED_BY_EASYADMIN => true,
                        EA::DASHBOARD_CONTROLLER_FQCN => $dashboardFqcn,
                        EA::CRUD_CONTROLLER_FQCN => $crudControllerFqcn,
                        EA::CRUD_ACTION => $actionName,
                    ];

                    $route = new Route($crudActionPath, $defaults, [], $options, '', [], self::ROUTES[$actionName]['methods']);

                    if (\in_array($crudActionRouteName, $addedRouteNames, true)) {
                        throw new \RuntimeException(sprintf('When using pretty URLs, all CRUD controllers must have unique PHP class names to generate unique route names. However, your application has at least two controllers with the FQCN "%s", generating the route "%s". Even if both CRUD controllers are in different namespaces, they cannot have the same class name. Rename one of these controllers to resolve the issue.', $crudControllerFqcn, $crudActionRouteName));
                    }

                    $collection->add($crudActionRouteName, $route);
                    $addedRouteNames[] = $crudActionRouteName;
                }
            }
        }

        return $collection;
    }

    public function getRouteName(string $dashboardFqcn, string $crudControllerFqcn, string $action): ?string
    {
        // EasyAdmin routes are only available for built-in CRUD actions
        if (!\in_array($action, Crud::ACTION_NAMES, true)) {
            return null;
        }

        $dashboardRouteConfiguration = $this->getDashboardsRouteConfiguration();
        $dashboardRouteName = $dashboardRouteConfiguration[$dashboardFqcn]['route_name'];
        $crudControllerRouteName = $this->getCrudControllerName($crudControllerFqcn);

        return sprintf('%s_%s_%s', $dashboardRouteName, $crudControllerRouteName, $action);
    }

    public function getRoutePath(string $dashboardFqcn, string $crudControllerFqcn, string $action): ?string
    {
        // EasyAdmin routes are only available for built-in CRUD actions
        if (!\in_array($action, Crud::ACTION_NAMES, true)) {
            return null;
        }

        $dashboardRouteConfiguration = $this->getDashboardsRouteConfiguration();
        $dashboardRoutePath = $dashboardRouteConfiguration[$dashboardFqcn]['route_path'];
        $crudControllerRoutePath = $this->getCrudControllerPath($crudControllerFqcn);

        return sprintf('%s/%s/%s', $dashboardRoutePath, $crudControllerRoutePath, ltrim(self::ROUTES[$action]['path'], '/'));
    }

    private function getCrudControllerName(string $crudControllerFqcn): string
    {
        $reflectionClass = new \ReflectionClass($crudControllerFqcn);
        $attributes = $reflectionClass->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() === AdminCrud::class && null !== $routeName = $attribute->getArguments()['routeName']) {
                return trim($routeName, '_');
            }
        }

        return trim($this->getCrudControllerShortName($crudControllerFqcn), '_');
    }

    private function getCrudControllerPath(string $crudControllerFqcn): string
    {
        $reflectionClass = new \ReflectionClass($crudControllerFqcn);
        $attributes = $reflectionClass->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() === AdminCrud::class && null !== $path = $attribute->getArguments()['path']) {
                return trim($path, '/');
            }
        }

        return trim($this->getCrudControllerShortName($crudControllerFqcn), '/');
    }

    private function getCrudControllerShortName(string $crudControllerFqcn): string
    {
        // transforms 'App\Controller\Admin\FooBarBazCrudController' into 'foo_bar_baz'
        $shortName = str_replace(['CrudController', 'Controller'], '', (new \ReflectionClass($crudControllerFqcn))->getShortName());
        $shortName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));

        return $shortName;
    }

    private function getDashboardsRouteConfiguration(): array
    {
        $config = [];

        foreach ($this->dashboardControllers as $dashboardController) {
            $reflectionClass = new \ReflectionClass($dashboardController);
            $indexMethod = $reflectionClass->getMethod('index');
            $routeAttributeFqcn = class_exists(\Symfony\Component\Routing\Attribute\Route::class) ? \Symfony\Component\Routing\Attribute\Route::class : \Symfony\Component\Routing\Annotation\Route::class;
            $attributes = $indexMethod->getAttributes($routeAttributeFqcn);

            if ([] === $attributes) {
                throw new \RuntimeException(sprintf('When using pretty URLs, the "%s" EasyAdmin dashboard controller must define its route configuration (route name, path) using a #[Route] attribute applied to its "index()" method.', $reflectionClass->getName()));
            }

            if (\count($attributes) > 1) {
                throw new \RuntimeException(sprintf('When using pretty URLs, the "%s" EasyAdmin dashboard controller must define only one #[Route] attribute applied on its "index()" method.', $reflectionClass->getName()));
            }

            $routeAttribute = $attributes[0]->newInstance();
            $config[$reflectionClass->getName()] = [
                'route_name' => $routeAttribute->getName(),
                'route_path' => rtrim($routeAttribute->getPath(), '/'),
            ];
        }

        return $config;
    }
}
