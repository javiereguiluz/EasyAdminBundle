<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Mcp;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpCallContext;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Kernel;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Calls the MCP endpoint over HTTP, as MCP clients do, using the stateless
 * protocol version (2026-07-28) of the MCP specification.
 */
class McpEndpointTest extends WebTestCase
{
    use McpTestDataTrait;

    private KernelBrowser $client;
    /** @var array<string, int> */
    private array $productIds;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        if (!class_exists(McpBundle::class)) {
            $this->markTestSkipped('This test requires symfony/mcp-bundle.');
        }

        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->client->getContainer()->get('router')->getRouteCollection();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->productIds = self::createTestData($entityManager);
    }

    public function testRequestWithoutTokenIsRejected(): void
    {
        $this->client->request('POST', '/admin/mcp', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        $this->assertResponseStatusCodeSame(401);
        $this->assertStringContainsString('resource_metadata=', (string) $this->client->getResponse()->headers->get('WWW-Authenticate'));
    }

    public function testToolsAreListedOnlyWithTheReadScope(): void
    {
        $tools = array_column($this->call('alice:mcp:read', 'tools/list')['result']['tools'], null, 'name');
        $this->assertSame(['describe_collection', 'get_record', 'list_collections', 'list_records'], $this->sorted(array_keys($tools)));
        $this->assertTrue($tools['list_records']['annotations']['readOnlyHint']);
        $this->assertFalse($tools['list_records']['annotations']['destructiveHint']);

        // unknown scopes (e.g. "mcp:write" without EasyAdmin Pro) are ignored instead of failing
        $tools = $this->call('alice:mcp:read mcp:write', 'tools/list')['result']['tools'];
        $this->assertCount(4, $tools);

        $this->assertSame([], $this->call('alice:other', 'tools/list')['result']['tools']);
    }

    public function testCallWithoutTheReadScopeIsRefused(): void
    {
        $response = $this->call('alice:other', 'tools/call', 'list_collections');

        $this->assertTrue($response['result']['isError']);
        $this->assertSame('The "list_collections" tool requires an access token with the "mcp:read" scope.', $response['result']['content'][0]['text']);
    }

    public function testListRecords(): void
    {
        $response = $this->call('alice:mcp:read', 'tools/call', 'list_records', ['collection' => 'tenant_products', 'sort' => ['name' => 'desc']]);

        $this->assertFalse($response['result']['isError']);
        $records = $response['result']['structuredContent']['untrusted_data']['records'];
        $this->assertSame([(string) $this->productIds['Product A2'], (string) $this->productIds['Product A1']], array_column($records, 'id'));
    }

    public function testListRecordsWithFiltersSearchAndPagination(): void
    {
        // the arguments are validated by the MCP SDK against the input schema of the tool
        $response = $this->call('alice:mcp:read', 'tools/call', 'list_records', [
            'collection' => 'catalog',
            'query' => 'Product',
            'filters' => ['status' => ['comparison' => '=', 'value' => 'draft'], 'active' => ['value' => false]],
            'sort' => ['name' => 'asc'],
            'page' => 1,
            'pageSize' => 1,
        ]);

        $this->assertFalse($response['result']['isError'], json_encode($response, \JSON_THROW_ON_ERROR));
        $result = $response['result']['structuredContent'];
        $this->assertSame(2, $result['total']);
        $this->assertTrue($result['has_more']);
        $this->assertSame('Product A2', $result['untrusted_data']['records'][0]['fields']['name']);
    }

    public function testErrorsAreReturnedAsToolErrors(): void
    {
        $response = $this->call('alice:mcp:read', 'tools/call', 'get_record', ['collection' => 'tenant_products', 'id' => (string) $this->productIds['Product B1']]);

        $this->assertTrue($response['result']['isError']);
        $this->assertStringContainsString('doesn\'t exist or you can\'t access it', $response['result']['content'][0]['text']);

        $response = $this->call('alice:mcp:read', 'tools/call', 'describe_collection', ['collection' => 'unmarked_product']);
        $this->assertTrue($response['result']['isError']);
        $this->assertStringContainsString('The "unmarked_product" collection doesn\'t exist.', $response['result']['content'][0]['text']);
    }

    public function testCallContextIsResetAfterEachCall(): void
    {
        $this->call('alice:mcp:read', 'tools/call', 'list_collections');

        $this->assertFalse($this->client->getContainer()->get('test.'.McpCallContext::class)->isActive());
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function call(string $token, string $method, ?string $toolName = null, array $arguments = []): array
    {
        $meta = [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientInfo' => ['name' => 'test-client', 'version' => '1.0'],
            'io.modelcontextprotocol/clientCapabilities' => new \stdClass(),
        ];
        $params = ['_meta' => $meta];
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
            'HTTP_MCP_METHOD' => $method,
        ];
        if (null !== $toolName) {
            $params += ['name' => $toolName, 'arguments' => [] === $arguments ? new \stdClass() : $arguments];
            $headers['HTTP_MCP_NAME'] = $toolName;
        }

        $this->client->request('POST', '/admin/mcp', server: $headers, content: json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], \JSON_THROW_ON_ERROR));
        $content = (string) $this->client->getResponse()->getContent();

        $this->assertResponseIsSuccessful($content);

        return json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
