<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception\McpAccessDeniedException;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception\McpInvalidArgumentException;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception\McpRecordNotFoundException;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Read\CollectionReader;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Field\InternalCodeField;

class CollectionReaderTest extends AbstractMcpTestCase
{
    public function testListCollectionsOnlyIncludesTheOnesTheUserCanAccess(): void
    {
        $this->logIn('alice');
        $collections = array_column($this->getReader()->listCollections()['collections'], null, 'id');

        $this->assertArrayHasKey('catalog', $collections);
        // denied by #[IsGranted], a BeforeCrudActionEvent listener and an action permission
        $this->assertArrayNotHasKey('admin_products', $collections);
        $this->assertArrayNotHasKey('blocked_products', $collections);
        $this->assertArrayNotHasKey('permission_products', $collections);

        $this->assertSame('Catalog products', $collections['catalog']['label']);
        $this->assertFalse($collections['catalog']['read_only']);
        // only the actions that can run over MCP are listed
        $this->assertSame(['index', 'detail'], $collections['catalog']['actions']);
        // #[IsGranted('ROLE_ADMIN')] on the detail() method
        $this->assertSame(['index'], $collections['detail_protected_products']['actions']);
        // exposed with Crud::exposeToMcp(readOnly: true, alias: 'method_products')
        $this->assertTrue($collections['method_products']['read_only']);
        // denied by an access_control rule of the backend
        $this->assertArrayNotHasKey('backend_restricted_products', $collections);

        $this->logIn('admin', ['ROLE_ADMIN']);
        $collections = array_column($this->getReader()->listCollections()['collections'], null, 'id');
        $this->assertArrayHasKey('admin_products', $collections);
        $this->assertArrayHasKey('permission_products', $collections);
        $this->assertArrayNotHasKey('blocked_products', $collections);
    }

    public function testDescribeCollection(): void
    {
        $this->logIn('alice');
        $description = $this->getReader()->describe('catalog');

        $indexFields = array_column($description['fields']['index'], 'schema', 'name');
        $this->assertSame(['id', 'name', 'priceInCents', 'active', 'createdAt', 'status', 'cardNumber', 'organization', 'tags'], array_keys($indexFields));
        $this->assertSame(['type' => 'string', 'title' => 'Name', 'description' => 'The public name', 'x-sortable' => true, 'x-required' => true], $indexFields['name']);
        $this->assertSame(['draft', 'published'], $indexFields['status']['enum']);
        $this->assertSame('date-time', $indexFields['createdAt']['format']);
        $this->assertSame('formatted value', $indexFields['cardNumber']['description']);
        $this->assertSame('object', $indexFields['tags']['type']);

        // hidden by permission: not described, not filterable
        $this->assertArrayNotHasKey('secretNote', $indexFields);
        $this->assertNotContains('secretNote', array_column($description['filters'], 'name'));
        $this->assertSame(['name', 'priceInCents', 'active', 'status', 'organization'], array_column($description['filters'], 'name'));

        $this->assertContains(['name' => 'internalCode', 'page' => 'index', 'field_type' => InternalCodeField::class], $description['unsupported_fields']);
        $this->assertArrayHasKey('description', array_column($description['fields']['detail'], 'schema', 'name'));
        $this->assertSame(['id' => 'asc'], $description['default_sort']);
        // limited by easy_admin.mcp.limits.max_page_size
        $this->assertSame(50, $description['page_size']);
        $this->assertTrue($description['searchable']);
        $this->assertContains('name', $description['sortable_fields']);
        $this->assertNotContains('tags', $description['sortable_fields']);
        // masked and unsupported fields can't be used to sort because it would reveal their values
        $this->assertNotContains('cardNumber', $description['sortable_fields']);
        $this->assertNotContains('internalCode', $description['sortable_fields']);
    }

    public function testCollectionExtensionsAddActionsAndDescriptions(): void
    {
        $this->logIn('alice');
        $collections = array_column($this->getReader()->listCollections()['collections'], null, 'id');
        // the actions returned by the extension are merged without duplicates
        $this->assertSame(['index', 'detail', 'export'], $collections['tag']['actions']);
        $this->assertSame(['index', 'detail'], $collections['catalog']['actions']);

        $description = $this->getReader()->describe('tag');
        $this->assertSame(['index', 'detail', 'export'], $description['actions']);
        $this->assertSame(['sample_record_id' => '1'], $description['x_test_extension']);
        $this->assertArrayNotHasKey('x_test_extension', $this->getReader()->describe('catalog'));
    }

    public function testDescribeShowsHiddenFieldsToUsersWhoCanSeeThem(): void
    {
        $this->logIn('admin', ['ROLE_ADMIN']);
        $description = $this->getReader()->describe('catalog');

        $this->assertContains('secretNote', array_column($description['fields']['index'], 'name'));
        $this->assertContains('secretNote', array_column($description['filters'], 'name'));
    }

    public function testUnknownCollection(): void
    {
        $this->logIn('alice');

        $this->expectException(McpInvalidArgumentException::class);
        $this->expectExceptionMessage('The "unmarked_product" collection doesn\'t exist.');

        $this->getReader()->describe('unmarked_product');
    }

    public function testListRecords(): void
    {
        $this->logIn('alice');
        $result = $this->getReader()->listRecords('catalog');

        $this->assertSame(4, $result['total']);
        $this->assertFalse($result['has_more']);
        $this->assertSame(CollectionReader::UNTRUSTED_DATA_NOTICE, $result['notice']);

        $record = $result['untrusted_data']['records'][0];
        $this->assertSame((string) $this->productIds['Product A1'], $record['id']);
        $this->assertSame('Product A1', $record['fields']['name']);
        $this->assertSame(['amount' => '10.50', 'currency' => 'EUR'], $record['fields']['priceInCents']);
        $this->assertTrue($record['fields']['active']);
        $this->assertSame('2026-01-01T10:30:00+00:00', $record['fields']['createdAt']);
        $this->assertSame('published', $record['fields']['status']);
        // formatValue() masks the value and the raw value is never sent
        $this->assertSame('**** 1234', $record['fields']['cardNumber']);
        $this->assertStringNotContainsString('4111', json_encode($result, \JSON_THROW_ON_ERROR));
        // fields hidden by permission and unsupported fields are not included
        $this->assertArrayNotHasKey('secretNote', $record['fields']);
        $this->assertArrayNotHasKey('internalCode', $record['fields']);
        $this->assertStringNotContainsString('Secret A1', json_encode($result, \JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('CODE-A1', json_encode($result, \JSON_THROW_ON_ERROR));
        // hidden on the index page
        $this->assertArrayNotHasKey('description', $record['fields']);
        $this->assertSame(['count' => 2, 'items' => [['id' => '1', 'label' => 'Tag 1'], ['id' => '2', 'label' => 'Tag 2']]], $record['fields']['tags']);
    }

    public function testAssociationLabelsOnlyIncludeRecordsTheUserCanAccess(): void
    {
        $this->logIn('alice');
        $records = array_column($this->getReader()->listRecords('catalog')['untrusted_data']['records'], 'fields', 'id');

        $productA1 = $records[(string) $this->productIds['Product A1']];
        $productB1 = $records[(string) $this->productIds['Product B1']];

        $this->assertSame('Organization A', $productA1['organization']['label']);
        // "organizations" only lets alice access her own organization
        $this->assertArrayHasKey('id', $productB1['organization']);
        $this->assertArrayNotHasKey('label', $productB1['organization']);
        $this->assertStringNotContainsString('Organization B', json_encode($records, \JSON_THROW_ON_ERROR));
    }

    public function testIsolationBetweenTenants(): void
    {
        $this->logIn('alice');
        $result = $this->getReader()->listRecords('tenant_products');
        $this->assertSame(2, $result['total']);
        $this->assertSame([(string) $this->productIds['Product A1'], (string) $this->productIds['Product A2']], array_column($result['untrusted_data']['records'], 'id'));

        // filtering, searching or sorting can't reach the records of other tenants
        $this->assertSame(0, $this->getReader()->listRecords('tenant_products', query: 'Product B1')['total']);

        $this->expectException(McpRecordNotFoundException::class);
        $this->getReader()->getRecord('tenant_products', (string) $this->productIds['Product B1']);
    }

    public function testSearch(): void
    {
        $this->logIn('alice');
        $result = $this->getReader()->listRecords('catalog', query: 'A2');

        $this->assertSame(1, $result['total']);
        $this->assertSame('Product A2', $result['untrusted_data']['records'][0]['fields']['name']);
    }

    /**
     * @testWith ["4111"]
     *           ["CODE-A1"]
     */
    public function testSearchNeverMatchesMaskedOrUnsupportedFields(string $query): void
    {
        $this->logIn('alice');

        $this->assertSame(0, $this->getReader()->listRecords('catalog', query: $query)['total']);
    }

    public function testSearchQueryLengthIsLimited(): void
    {
        $this->logIn('alice');

        $this->expectException(McpInvalidArgumentException::class);
        $this->expectExceptionMessage('The search query can\'t be longer than 200 characters.');

        $this->getReader()->listRecords('catalog', query: str_repeat('a', 201));
    }

    public function testDeniedCollectionsAreReportedAsMissing(): void
    {
        $this->logIn('alice');

        $this->expectException(McpInvalidArgumentException::class);
        $this->expectExceptionMessage('The "admin_products" collection doesn\'t exist.');

        $this->getReader()->listRecords('admin_products');
    }

    public function testHasMore(): void
    {
        $this->logIn('alice');
        $result = $this->getReader()->listRecords('catalog', pageSize: 3);

        $this->assertTrue($result['has_more']);
        $this->assertCount(3, $result['untrusted_data']['records']);
    }

    public function testResponseSizeIsLimited(): void
    {
        $this->logIn('alice');
        $reader = new CollectionReader(...$this->getReaderArguments(['max_page_size' => 50, 'max_response_bytes' => 1024]));

        $this->expectException(McpInvalidArgumentException::class);
        $this->expectExceptionMessage('The response is too large');

        $reader->listRecords('catalog');
    }

    public function testSearchNeverMatchesFieldsHiddenByPermissions(): void
    {
        $this->logIn('alice');
        $this->assertSame(0, $this->getReader()->listRecords('catalog', query: 'Secret A1')['total']);

        $this->logIn('admin', ['ROLE_ADMIN']);
        $this->assertSame(1, $this->getReader()->listRecords('catalog', query: 'Secret A1')['total']);
    }

    public function testFilters(): void
    {
        $this->logIn('alice');

        $result = $this->getReader()->listRecords('catalog', filters: ['status' => ['comparison' => '=', 'value' => 'draft']]);
        $this->assertSame(['Product A2', 'Product B2'], array_map(static fn (array $record): string => $record['fields']['name'], $result['untrusted_data']['records']));

        $result = $this->getReader()->listRecords('catalog', filters: ['active' => ['value' => true], 'name' => ['comparison' => 'like', 'value' => 'B']]);
        $this->assertSame(['Product B1'], array_map(static fn (array $record): string => $record['fields']['name'], $result['untrusted_data']['records']));

        // the value uses the same unit as the money field (euros), not the stored value (cents)
        $result = $this->getReader()->listRecords('catalog', filters: ['priceInCents' => ['comparison' => '>', 'value' => 20]]);
        $this->assertSame(2, $result['total']);
    }

    public function testFilterOnFieldHiddenByPermissionIsRejected(): void
    {
        $this->logIn('alice');

        $this->expectException(McpInvalidArgumentException::class);
        $this->expectExceptionMessage('The "secretNote" filter doesn\'t exist.');

        $this->getReader()->listRecords('catalog', filters: ['secretNote' => ['comparison' => 'like', 'value' => 'Secret']]);
    }

    public function testInvalidFilterValueIsReportedInsteadOfIgnored(): void
    {
        $this->logIn('alice');

        $this->expectException(McpInvalidArgumentException::class);
        $this->expectExceptionMessage('The value of the "status" filter is not valid.');

        $this->getReader()->listRecords('catalog', filters: ['status' => ['comparison' => '=', 'value' => 'archived']]);
    }

    public function testSort(): void
    {
        $this->logIn('alice');
        $result = $this->getReader()->listRecords('catalog', sort: ['name' => 'desc']);

        $this->assertSame(['Product B2', 'Product B1', 'Product A2', 'Product A1'], array_map(static fn (array $record): string => $record['fields']['name'], $result['untrusted_data']['records']));
    }

    /**
     * @testWith ["secretNote"]
     *           ["cardNumber"]
     *           ["tags"]
     *           ["unknown"]
     */
    public function testSortByNotSortableOrHiddenFieldIsRejected(string $property): void
    {
        $this->logIn('alice');

        $this->expectException(McpInvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The records can\'t be sorted by "%s".', $property));

        $this->getReader()->listRecords('catalog', sort: [$property => 'asc']);
    }

    public function testPagination(): void
    {
        $this->logIn('alice');
        $result = $this->getReader()->listRecords('catalog', page: 2, pageSize: 3);

        $this->assertSame(2, $result['page']);
        $this->assertSame(3, $result['page_size']);
        $this->assertFalse($result['has_more']);
        $this->assertCount(1, $result['untrusted_data']['records']);
    }

    public function testPageSizeIsLimited(): void
    {
        $this->logIn('alice');

        $this->assertSame(50, $this->getReader()->listRecords('catalog', pageSize: 1000)['page_size']);
    }

    public function testEntityPermissionHidesRecords(): void
    {
        $this->logIn('alice');
        $result = $this->getReader()->listRecords('entity_permission_products');

        $this->assertSame([], $result['untrusted_data']['records']);
        // the total is not included because it would count the records hidden from the user
        $this->assertNull($result['total']);
    }

    public function testGetRecord(): void
    {
        $this->logIn('alice');
        $result = $this->getReader()->getRecord('catalog', (string) $this->productIds['Product B2']);

        $record = $result['untrusted_data']['record'];
        $this->assertSame('Product B2', $record['fields']['name']);
        $this->assertSame('Description of product B2', $record['fields']['description']);
        $this->assertSame(['count' => 3, 'items' => [['id' => '1', 'label' => 'Tag 1'], ['id' => '2', 'label' => 'Tag 2'], ['id' => '3', 'label' => 'Tag 3']]], $record['fields']['tags']);
    }

    public function testGetRecordRespectsIsGrantedOnTheDetailAction(): void
    {
        $this->logIn('alice');

        $this->expectException(McpAccessDeniedException::class);
        $this->getReader()->getRecord('detail_protected_products', (string) $this->productIds['Product A1']);
    }

    private function getReader(): CollectionReader
    {
        return $this->getMcpService(CollectionReader::class);
    }

    /**
     * Returns the constructor arguments of the reader service, with other limits.
     *
     * @param array<string, int> $limits
     *
     * @return list<mixed>
     */
    private function getReaderArguments(array $limits): array
    {
        $reader = $this->getReader();
        $arguments = [];
        foreach ((new \ReflectionMethod(CollectionReader::class, '__construct'))->getParameters() as $parameter) {
            $arguments[] = 'limits' === $parameter->getName() ? $limits : (new \ReflectionProperty(CollectionReader::class, $parameter->getName()))->getValue($reader);
        }

        return $arguments;
    }
}
