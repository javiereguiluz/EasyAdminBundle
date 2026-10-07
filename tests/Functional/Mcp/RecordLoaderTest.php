<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception\McpAccessDeniedException;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception\McpRecordNotFoundException;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\RecordLoader;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity\Product;

class RecordLoaderTest extends AbstractMcpTestCase
{
    public function testLoadRecord(): void
    {
        $this->logIn('alice');

        $record = $this->getRecordLoader()->load($this->getCollection('product'), $this->productIds['Product B1']);

        $this->assertInstanceOf(Product::class, $record);
        $this->assertSame('Product B1', $record->getName());
    }

    public function testIdGivenAsString(): void
    {
        $this->logIn('alice');

        $record = $this->getRecordLoader()->load($this->getCollection('product'), (string) $this->productIds['Product A2']);

        $this->assertSame('Product A2', $record->getName());
    }

    public function testMissingRecord(): void
    {
        $this->logIn('alice');

        $this->expectException(McpRecordNotFoundException::class);
        $this->expectExceptionMessage('The record "999999" doesn\'t exist or you can\'t access it.');

        $this->getRecordLoader()->load($this->getCollection('product'), 999999);
    }

    public function testIndexQueryBuilderRestrictionsApplyToRecordsLoadedById(): void
    {
        $this->logIn('alice');
        $collection = $this->getCollection('tenant_products');

        $this->assertSame('Product A1', $this->getRecordLoader()->load($collection, $this->productIds['Product A1'])->getName());

        $this->expectException(McpRecordNotFoundException::class);
        $this->getRecordLoader()->load($collection, $this->productIds['Product B1']);
    }

    public function testEachTenantOnlyLoadsItsOwnRecords(): void
    {
        $this->logIn('bob');
        $collection = $this->getCollection('tenant_products');

        $this->assertSame('Product B2', $this->getRecordLoader()->load($collection, $this->productIds['Product B2'])->getName());

        $this->expectException(McpRecordNotFoundException::class);
        $this->getRecordLoader()->load($collection, $this->productIds['Product A2']);
    }

    public function testEntityPermissionHidesTheRecord(): void
    {
        $this->logIn('admin', ['ROLE_ADMIN']);
        $this->assertSame('Product A1', $this->getRecordLoader()->load($this->getCollection('entity_permission_products'), $this->productIds['Product A1'])->getName());

        $this->logIn('alice');
        $this->expectException(McpRecordNotFoundException::class);
        $this->getRecordLoader()->load($this->getCollection('entity_permission_products'), $this->productIds['Product A1']);
    }

    public function testUserMustBeAllowedToRunTheIndexAction(): void
    {
        $this->logIn('alice');

        $this->expectException(McpAccessDeniedException::class);
        $this->getRecordLoader()->load($this->getCollection('permission_products'), $this->productIds['Product A1']);
    }

    public function testLoadMany(): void
    {
        $this->logIn('alice');

        $records = $this->getRecordLoader()->loadMany($this->getCollection('tenant_products'), [$this->productIds['Product A2'], $this->productIds['Product A1']]);

        $this->assertSame(['Product A2', 'Product A1'], array_map(static fn (Product $product): string => $product->getName(), $records));
    }

    public function testLoadManyFailsIfAnyRecordIsNotAccessible(): void
    {
        $this->logIn('alice');

        $this->expectException(McpRecordNotFoundException::class);
        $this->getRecordLoader()->loadMany($this->getCollection('tenant_products'), [$this->productIds['Product A1'], $this->productIds['Product B1']]);
    }

    private function getRecordLoader(): RecordLoader
    {
        return $this->getMcpService(RecordLoader::class);
    }
}
