<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\ExposeToMcp;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity\Product;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Restricted with #[IsGranted] on the controller class.
 *
 * @extends AbstractCrudController<Product>
 */
#[ExposeToMcp(alias: 'admin_products')]
#[IsGranted('ROLE_ADMIN')]
class AdminProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }
}
