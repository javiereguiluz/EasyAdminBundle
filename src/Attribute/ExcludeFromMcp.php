<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Attribute;

/**
 * Marks the CRUD controller as not exposed to MCP clients. Crud::exposeToMcp()
 * and Crud::excludeFromMcp() override this attribute.
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ExcludeFromMcp
{
}
