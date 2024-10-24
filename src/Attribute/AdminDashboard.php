<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Attribute;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AdminDashboard
{
    public function __construct(
        /** @var array<string, array{routeName: string, path: string}>|null */
        public ?array $routes = null,
    ) {
    }
}
