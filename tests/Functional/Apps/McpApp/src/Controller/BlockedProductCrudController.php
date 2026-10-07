<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\ExposeToMcp;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity\Product;

/**
 * Every action is stopped by BlockedProductListener.
 *
 * @extends AbstractCrudController<Product>
 */
#[ExposeToMcp(alias: 'blocked_products')]
class BlockedProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }
}
