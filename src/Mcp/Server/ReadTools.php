<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Server;

use EasyCorp\Bundle\EasyAdminBundle\Mcp\Attribute\McpScope;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Read\CollectionReader;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * The MCP tools that read the data of the CRUD controllers exposed to MCP.
 *
 * @experimental
 */
final readonly class ReadTools
{
    public function __construct(
        private McpToolRunner $toolRunner,
        private CollectionReader $collectionReader,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'list_collections',
        title: 'List collections',
        description: 'Lists the collections (types of records) that you can access, with the actions that the user can run on each of them.',
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    #[McpScope(McpScope::READ)]
    public function listCollections(): array
    {
        return $this->toolRunner->run('list_collections', null, null, $this->collectionReader->listCollections(...));
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'describe_collection',
        title: 'Describe a collection',
        description: 'Always call this first for a collection. It returns its fields (with their JSON Schema), filters, sortable fields, default sort and page size. Fields marked as "x-required" are required in forms, but that is only a hint: the application can apply more rules.',
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    #[McpScope(McpScope::READ)]
    public function describeCollection(
        #[Schema(description: 'The id of the collection, as returned by list_collections.')]
        string $collection,
    ): array {
        return $this->toolRunner->run('describe_collection', $collection, 'describe', fn (): array => $this->collectionReader->describe($collection));
    }

    /**
     * @param array<string, array{comparison?: string, value?: mixed, value2?: mixed}>|null $filters
     * @param array<string, string>|null                                                    $sort
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'list_records',
        title: 'List records',
        description: 'Lists the records of a collection, optionally searching, filtering and sorting them. Call describe_collection first to know the available filters and sortable fields.',
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    #[McpScope(McpScope::READ)]
    public function listRecords(
        #[Schema(description: 'The id of the collection, as returned by list_collections.')]
        string $collection,
        #[Schema(description: 'Text to search in the searchable fields of the collection.')]
        ?string $query = null,
        #[Schema(
            description: 'Filters indexed by the name of the filter (e.g. {"status": {"comparison": "=", "value": "published"}}). Use the comparisons listed by describe_collection.',
            type: 'object',
            additionalProperties: [
                'type' => 'object',
                'properties' => [
                    'comparison' => ['type' => 'string'],
                    'value' => true,
                    'value2' => true,
                ],
                'required' => ['value'],
                'additionalProperties' => false,
            ],
        )]
        ?array $filters = null,
        #[Schema(
            description: 'Sort directions indexed by the name of a sortable field (e.g. {"createdAt": "desc"}).',
            type: 'object',
            additionalProperties: ['type' => 'string', 'enum' => ['asc', 'desc']],
        )]
        ?array $sort = null,
        #[Schema(description: 'The page number (the first page is 1).', minimum: 1)]
        int $page = 1,
        #[Schema(description: 'The number of records per page. It can\'t be larger than the page size returned by describe_collection.', minimum: 1)]
        ?int $pageSize = null,
    ): array {
        return $this->toolRunner->run('list_records', $collection, 'index', fn (): array => $this->collectionReader->listRecords($collection, $query, $filters ?? [], $sort ?? [], $page, $pageSize));
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_record',
        title: 'Get a record',
        description: 'Returns all the fields of a record that the user can see.',
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    #[McpScope(McpScope::READ)]
    public function getRecord(
        #[Schema(description: 'The id of the collection, as returned by list_collections.')]
        string $collection,
        #[Schema(description: 'The id of the record.')]
        string $id,
    ): array {
        return $this->toolRunner->run('get_record', $collection, 'detail', fn (): array => $this->collectionReader->getRecord($collection, $id));
    }
}
