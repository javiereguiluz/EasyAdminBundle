<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

#[AdminDashboard(routePath: '/selected_mcp_admin', routeName: 'selected_mcp_admin')]
class SelectedMcpDashboardController extends AbstractDashboardController
{
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->exposeSelectedCrudsToMcp();
    }
}
