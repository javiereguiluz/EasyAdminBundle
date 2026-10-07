<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\ExposeToMcp;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity\Product;

/**
 * Restricted to ROLE_ADMIN by an access_control rule of the backend URLs.
 *
 * @extends AbstractCrudController<Product>
 */
#[ExposeToMcp(alias: 'backend_restricted_products')]
class BackendRestrictedProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }
}
