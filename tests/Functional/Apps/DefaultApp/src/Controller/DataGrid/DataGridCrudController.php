<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Controller\DataGrid;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Entity\Category;

/**
 * CrudController whose index page renders through the <twig:ea:DataGrid> component
 * (EasyAdmin mode) instead of the default index markup, to cover that mode end to
 * end with a real AdminContext (see templates/admin/datagrid/index.html.twig).
 *
 * With 30 categories and pageSize=5 the paginator footer always renders.
 *
 * @extends AbstractCrudController<Category>
 */
class DataGridCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(5)
            ->overrideTemplate('crud/index', 'admin/datagrid/index.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name')->setHtmlAttribute('data-test-cell', 'name'),
            TextField::new('slug')->setSortable(false),
        ];
    }
}
