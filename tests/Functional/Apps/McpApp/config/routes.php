<?php

use Symfony\AI\McpBundle\McpBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes) {
    $routes->import('../src/Controller/', 'attribute');
    $routes->import('.', 'easyadmin.routes');

    if (class_exists(McpBundle::class)) {
        $routes->import('.', 'mcp');
    }
};
