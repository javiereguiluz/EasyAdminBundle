<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;

/**
 * A CRUD controller exposed to MCP clients.
 *
 * @experimental
 */
final readonly class McpCollection
{
    /**
     * @param string                                $id                 the id used by MCP tools to refer to this collection
     * @param class-string<CrudControllerInterface> $crudControllerFqcn
     * @param class-string                          $entityFqcn
     */
    public function __construct(
        public string $id,
        public string $crudControllerFqcn,
        public string $entityFqcn,
        public bool $readOnly,
    ) {
    }
}
