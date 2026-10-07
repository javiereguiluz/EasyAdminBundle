<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

#[AdminDashboard(routePath: '/all_mcp_admin', routeName: 'all_mcp_admin')]
class AllMcpDashboardController extends AbstractDashboardController
{
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->exposeAllCrudsToMcp();
    }
}
