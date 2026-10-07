<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

#[AdminDashboard(routePath: '/plain_admin', routeName: 'plain_admin')]
class PlainDashboardController extends AbstractDashboardController
{
}
