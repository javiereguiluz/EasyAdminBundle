<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Router;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
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
            'name' => 'index',
            'methods' => ['GET'],
        ],
        'new' => [
            'path' => '/new',
            'name' => 'new',
            'methods' => ['GET', 'POST'],
        ],
        'batchDelete' => [
            'path' => '/batchDelete',
            'name' => 'batchDelete',
            'methods' => ['POST'],
        ],
        'autocomplete' => [
            'path' => '/autocomplete',
            'name' => 'autocomplete',
            'methods' => ['GET'],
        ],
        'edit' => [
            'path' => '/{entityId}/edit',
            'name' => 'edit',
            'methods' => ['GET', 'POST', 'PATCH'],
        ],
        'delete' => [
            'path' => '/{entityId}/delete',
            'name' => 'delete',
            'methods' => ['POST'],
        ],
        'detail' => [
            'path' => '/{entityId}',
            'name' => 'detail',
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




                foreach (array_keys(self::ROUTES) as $actionName) {
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

        $defaultRouteConfig = $this->getDefaultRouteConfig($dashboardFqcn, $action);

        $actionsCustomRouteConfig = $this->getActionsCustomConfig($crudControllerFqcn);
        $actionRouteName = $actionsCustomRouteConfig[$action]['routeName'] ?? $defaultRouteConfig['routeName'] ?? $action;

        return sprintf('%s_%s_%s', $dashboardRouteName, $crudControllerRouteName, $actionRouteName);
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

        $defaultRouteConfig = $this->getDefaultRouteConfig($dashboardFqcn, $action);

        $actionsCustomConfig = $this->getActionsCustomConfig($crudControllerFqcn);
        $actionPath = $actionsCustomConfig[$action]['path'] ?? $defaultRouteConfig['path'];

        return sprintf('%s/%s/%s', $dashboardRoutePath, $crudControllerRoutePath, ltrim($actionPath, '/'));
    }

    private function getDefaultRouteConfig(string $dashboardFqcn, string $action): array
    {
        $reflectionClass = new \ReflectionClass($dashboardFqcn);
        $attributes = $reflectionClass->getAttributes();
        $customRouteConfig = [];
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === AdminDashboard::class) {
                $dashboardAttribute = $attribute->newInstance();
                if (isset($dashboardAttribute->routes[$action])) {
                    $customRouteConfig = $dashboardAttribute->routes[$action];

                    if (\count(array_diff(array_keys($customRouteConfig), ['path', 'routeName'])) > 0) {
                        throw new \RuntimeException(sprintf('In the #[AdminDashboard] attribute of the "%s" dashboard controller, the route configuration for the "%s" action defines some unsupported keys. You can only define these keys: "path" and "routeName".', $dashboardFqcn, $action));
                    }

                    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $customRouteConfig['routeName'])) {
                        throw new \RuntimeException(sprintf('In the #[AdminDashboard] attribute of the "%s" dashboard controller, the route name "%s" for the "%s" action is not valid. It can only contain letter, numbers, dashes, and underscores.', $dashboardFqcn, $customRouteConfig['routeName'], $action));
                    }

                    if (\in_array($action, ['edit', 'detail', 'delete'], true) && false === strpos($customRouteConfig['path'], '{entityId}')) {
                        throw new \RuntimeException(sprintf('In the #[AdminDashboard] attribute of the "%s" dashboard controller, the path for the "%s" action must contain the "{entityId}" placeholder.', $action, $dashboardFqcn));
                    }

                    break;
                }
            }
        }

        return array_merge(self::ROUTES[$action], $customRouteConfig);
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

    private function getActionsCustomConfig(string $crudControllerFqcn): array
    {
        $reflectionClass = new \ReflectionClass($crudControllerFqcn);
        $methods = $reflectionClass->getMethods();
        $actionsCustomConfig = [];
        foreach ($methods as $method) {
            $attributes = $method->getAttributes();
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === AdminAction::class) {
                    $actionsCustomConfig[$method->getName()] = [
                        'path' => trim($attribute->getArguments()['path'], '/'),
                        'routeName' => trim($attribute->getArguments()['routeName'], '_'),
                    ];
                }
            }
        }

        return $actionsCustomConfig;
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
