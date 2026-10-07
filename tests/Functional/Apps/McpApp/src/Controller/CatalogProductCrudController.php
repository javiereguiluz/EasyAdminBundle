<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\ExposeToMcp;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Field\InternalCodeField;

/**
 * Not restricted by organization, and using all kinds of fields and filters.
 *
 * @extends AbstractCrudController<Product>
 */
#[ExposeToMcp(alias: 'catalog')]
class CatalogProductCrudController extends AbstractCrudController
{
    public const STATUSES = ['Draft' => 'draft', 'Published' => 'published'];

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Catalog products')
            ->setDefaultSort(['id' => 'ASC'])
            ->setPaginatorPageSize(100);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('name')->setHelp('The <b>public</b> name');
        yield TextareaField::new('description')->hideOnIndex();
        yield MoneyField::new('priceInCents', 'Price')->setCurrency('EUR');
        yield BooleanField::new('active')->renderAsSwitch(false);
        yield DateTimeField::new('createdAt');
        yield ChoiceField::new('status')->setChoices(self::STATUSES);
        yield TextField::new('secretNote')->setPermission('ROLE_ADMIN');
        yield TextField::new('cardNumber')->formatValue(static fn (?string $value): ?string => null === $value ? null : '**** '.substr($value, -4));
        yield InternalCodeField::new('internalCode');
        yield AssociationField::new('organization');
        yield AssociationField::new('tags');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name'))
            ->add(NumericFilter::new('priceInCents'))
            ->add(BooleanFilter::new('active'))
            ->add(ChoiceFilter::new('status')->setChoices(self::STATUSES))
            ->add(TextFilter::new('secretNote'))
            ->add(EntityFilter::new('organization'));
    }
}
