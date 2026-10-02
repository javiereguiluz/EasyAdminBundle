<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\CustomizationApp\Controller\Dashboard;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\IconFamily;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\CustomizationApp\Controller\DemoEntityCrudController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard controller for testing Assets::useIconFamily().
 */
#[AdminDashboard(routePath: '/customization_icon_family_admin', routeName: 'customization_icon_family_admin')]
class IconFamilyTestDashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator
            ->setController(DemoEntityCrudController::class)
            ->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Icon Family Test');
    }

    public function configureAssets(): Assets
    {
        return parent::configureAssets()
            ->useIconFamily(IconFamily::Tabler);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkTo(DemoEntityCrudController::class, 'Demo', 'user');
        yield MenuItem::linkTo(DemoEntityCrudController::class, 'Demo with prefixed icon', 'tabler:user');
    }
}
