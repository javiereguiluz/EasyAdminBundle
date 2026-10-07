<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\ExposeToMcp;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\AdminRouteApp\Entity\Product;

#[ExposeToMcp(readOnly: true, alias: 'invoices')]
class InvoiceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }
}
