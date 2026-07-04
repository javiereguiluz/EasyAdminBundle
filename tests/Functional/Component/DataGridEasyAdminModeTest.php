<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Component;

use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Controller\DashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Controller\DataGrid\DataGridCrudController;

/**
 * Rendering tests for the EasyAdmin mode of the <twig:ea:DataGrid> component,
 * exercised through a real CRUD index request (DataGridCrudController overrides the
 * index template to render everything with the component). This keeps the component's
 * EasyAdmin branch from drifting from the markup contract of the default index page
 * (searchable/sorted classes, data attributes, actions cell, paginator footer, ...).
 */
class DataGridEasyAdminModeTest extends AbstractCrudTestCase
{
    protected function getControllerFqcn(): string
    {
        return DataGridCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    public function testGridStructureAndDensity(): void
    {
        $crawler = $this->client->request('GET', $this->getCrudUrl('index'));

        static::assertResponseIsSuccessful();

        // EA mode opts into the legacy datagrid look on top of the ea-table baseline,
        // and forwards the size prop and the row-click trigger configured in the CRUD
        $table = $crawler->filter('table.ea-table.ea-table-sm.table.datagrid');
        self::assertCount(1, $table);
        self::assertNotEmpty($table->attr('data-default-action-trigger'));

        // 5 entity rows (pageSize=5), each addressable by its id
        self::assertCount(5, $crawler->filter('tbody tr[data-id]'));
    }

    public function testHeaderCellsMatchTheIndexContract(): void
    {
        $crawler = $this->client->request('GET', $this->getCrudUrl('index'));

        // sortable column renders a link; non-sortable renders a span
        self::assertCount(1, $crawler->filter('thead th[data-column="id"][scope="col"] a'));
        self::assertCount(1, $crawler->filter('thead th[data-column="slug"][scope="col"] span'));

        // with no explicit search fields configured, every field column is searchable
        self::assertCount(3, $crawler->filter('thead th.searchable'));
    }

    public function testBodyCellsMatchTheIndexContract(): void
    {
        $crawler = $this->client->request('GET', $this->getCrudUrl('index'));

        // cells emit the responsive/search-highlight hooks and the field's custom HTML attributes
        self::assertCount(5, $crawler->filter('tbody td[data-column="name"][data-label]'));
        self::assertCount(15, $crawler->filter('tbody td.searchable'));
        self::assertCount(5, $crawler->filter('tbody td[data-test-cell="name"]'));

        // the actions cell renders through the same partial as the index page
        self::assertCount(5, $crawler->filter('tbody td.actions'));
    }

    public function testSortedColumnIsMarked(): void
    {
        $crawler = $this->client->request('GET', $this->getCrudUrl('index', null, ['sort' => ['id' => 'DESC']]));

        self::assertCount(1, $crawler->filter('thead th.sorted[aria-sort="descending"][data-column="id"]'));
        self::assertCount(5, $crawler->filter('tbody td.sorted[data-column="id"]'));
    }

    public function testPaginatorFooterRenders(): void
    {
        $crawler = $this->client->request('GET', $this->getCrudUrl('index'));

        // 30 categories with pageSize=5 always paginate
        self::assertCount(1, $crawler->filter('.content-panel-footer .list-pagination'));
    }

    public function testEmptyStateRendersTheCustomMessage(): void
    {
        $crawler = $this->client->request('GET', $this->getCrudUrl('index', null, ['query' => 'zzz-no-results-zzz']));

        self::assertCount(1, $crawler->filter('table.datagrid-empty'));

        $emptyRow = $crawler->filter('tr.ea-table-empty-row');
        self::assertCount(1, $emptyRow);
        self::assertStringContainsString('No categories here', $emptyRow->text());
    }
}
