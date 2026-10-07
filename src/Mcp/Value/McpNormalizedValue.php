<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Value;

/**
 * @experimental
 */
final readonly class McpNormalizedValue
{
    public function __construct(
        public mixed $value,
        public bool $truncated = false,
    ) {
    }
}
