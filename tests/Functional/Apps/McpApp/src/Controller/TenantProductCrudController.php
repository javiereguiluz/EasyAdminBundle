<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Controller;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\ExposeToMcp;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity\Product;

/**
 * Multi-tenant CRUD: users only see the products of their own organization.
 *
 * @extends AbstractCrudController<Product>
 */
#[ExposeToMcp(alias: 'tenant_products')]
class TenantProductCrudController extends AbstractCrudController
{
    public const USER_ORGANIZATIONS = [
        'alice' => 'Organization A',
        'bob' => 'Organization B',
    ];

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $organizationName = self::USER_ORGANIZATIONS[$this->getUser()?->getUserIdentifier()] ?? null;

        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->join('entity.organization', 'tenant_organization')
            ->andWhere('tenant_organization.name = :tenant_organization_name')
            ->setParameter('tenant_organization_name', $organizationName ?? '');
    }
}
