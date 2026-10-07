<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Attribute;

/**
 * Marks the CRUD controller as exposed to MCP clients, optionally as read-only
 * or with a stable collection id. Crud::exposeToMcp() and Crud::excludeFromMcp()
 * override this attribute.
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ExposeToMcp
{
    public function __construct(
        /** @var bool $readOnly If true, MCP tools can never change the data of this CRUD controller */
        public bool $readOnly = false,
        /** @var string|null $alias The stable id of the collection used by MCP tools (by default, it's derived from the controller class name) */
        public ?string $alias = null,
    ) {
    }
}
