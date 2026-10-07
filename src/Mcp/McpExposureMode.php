<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp;

/**
 * @experimental
 */
enum McpExposureMode
{
    // only the CRUD controllers marked with exposeToMcp() or #[ExposeToMcp] are exposed
    case Selected;
    // all CRUD controllers are exposed except those marked with excludeFromMcp() or #[ExcludeFromMcp]
    case All;
}
