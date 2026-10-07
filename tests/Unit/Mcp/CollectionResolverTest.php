<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\CollectionResolver;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpCollection;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpExposureMode;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpExposureResolver;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminRouteGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\AdminRouteApp\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\AllMcpDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\BothMcpAttributesCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\CategoryCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\CustomerCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\InvoiceAliasCollisionCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\InvoiceCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\OrderCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\PlainDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\ProductCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\RestrictedMcpDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\SelectedMcpDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\SupplierCrudController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class CollectionResolverTest extends TestCase
{
    public function testNoDashboardExposesCruds(): void
    {
        $resolver = $this->createResolver([new PlainDashboardController()], [new ProductCrudController()]);

        $this->assertNull($resolver->findMcpDashboard());
        $this->assertSame([], $resolver->getCollections());
    }

    public function testFindMcpDashboard(): void
    {
        $dashboard = new SelectedMcpDashboardController();
        $resolver = $this->createResolver([new PlainDashboardController(), $dashboard], [new ProductCrudController()]);

        $this->assertSame([$dashboard, McpExposureMode::Selected], $resolver->findMcpDashboard());
    }

    public function testOnlyOneDashboardCanExposeCruds(): void
    {
        $resolver = $this->createResolver([new SelectedMcpDashboardController(), new AllMcpDashboardController()], [new ProductCrudController()]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('Only one dashboard can expose CRUD controllers to MCP, but both "%s" and "%s" call', SelectedMcpDashboardController::class, AllMcpDashboardController::class));

        $resolver->findMcpDashboard();
    }

    public function testExposeSelectedCruds(): void
    {
        $resolver = $this->createResolver([new SelectedMcpDashboardController()], $this->getCrudControllers());

        $this->assertEquals([
            'category' => new McpCollection('category', CategoryCrudController::class, Product::class, false),
            'invoices' => new McpCollection('invoices', InvoiceCrudController::class, Product::class, true),
            'orders' => new McpCollection('orders', OrderCrudController::class, Product::class, false),
        ], $resolver->getCollections());
    }

    public function testExposeAllCruds(): void
    {
        $resolver = $this->createResolver([new AllMcpDashboardController()], $this->getCrudControllers());

        $this->assertEquals([
            'product' => new McpCollection('product', ProductCrudController::class, Product::class, false),
            'category' => new McpCollection('category', CategoryCrudController::class, Product::class, false),
            'invoices' => new McpCollection('invoices', InvoiceCrudController::class, Product::class, true),
            'orders' => new McpCollection('orders', OrderCrudController::class, Product::class, false),
        ], $resolver->getCollections());
    }

    public function testControllersNotAllowedInTheDashboardAreNeverExposed(): void
    {
        $resolver = $this->createResolver([new RestrictedMcpDashboardController()], $this->getCrudControllers());

        $this->assertSame(['product'], array_keys($resolver->getCollections()));
    }

    public function testCollectionIdCollision(): void
    {
        $resolver = $this->createResolver([new SelectedMcpDashboardController()], [new InvoiceCrudController(), new InvoiceAliasCollisionCrudController()]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The "%s" and "%s" CRUD controllers use the same MCP collection id "invoices".', InvoiceCrudController::class, InvoiceAliasCollisionCrudController::class));

        $resolver->getCollections();
    }

    public function testCrudControllerCannotUseBothAttributes(): void
    {
        $routeGenerator = new AdminRouteGenerator([new SelectedMcpDashboardController()], [new BothMcpAttributesCrudController()], new ArrayAdapter());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The "%s" CRUD controller cannot use both the #[ExposeToMcp] and #[ExcludeFromMcp] attributes.', BothMcpAttributesCrudController::class));

        $routeGenerator->generateAll();
    }

    public function testDefaultCollectionId(): void
    {
        $this->assertSame('product', CollectionResolver::getDefaultCollectionId(ProductCrudController::class));
        $this->assertSame('invoice_alias_collision', CollectionResolver::getDefaultCollectionId(InvoiceAliasCollisionCrudController::class));
    }

    /**
     * @return list<CrudControllerInterface>
     */
    private function getCrudControllers(): array
    {
        return [
            new ProductCrudController(),
            new CategoryCrudController(),
            new InvoiceCrudController(),
            new CustomerCrudController(),
            new OrderCrudController(),
            new SupplierCrudController(),
        ];
    }

    /**
     * @param list<DashboardControllerInterface> $dashboardControllers
     * @param list<CrudControllerInterface>      $crudControllers
     */
    private function createResolver(array $dashboardControllers, array $crudControllers): CollectionResolver
    {
        $cache = new ArrayAdapter();
        $routeGenerator = new AdminRouteGenerator($dashboardControllers, $crudControllers, $cache);
        $routeGenerator->generateAll();

        return new CollectionResolver($dashboardControllers, $crudControllers, $routeGenerator, new McpExposureResolver($cache, $routeGenerator));
    }
}
