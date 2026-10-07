<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\ExposeToMcp;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity\Product;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Restricted with #[IsGranted] on the detail() action method only.
 *
 * @extends AbstractCrudController<Product>
 */
#[ExposeToMcp(alias: 'detail_protected_products')]
class DetailProtectedProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    #[IsGranted('ROLE_ADMIN')]
    public function detail(AdminContext $context): KeyValueStore|Response
    {
        return parent::detail($context);
    }
}
