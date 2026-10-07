<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Attribute;

/**
 * Declares the OAuth scope that an MCP tool requires (e.g. "read" requires the
 * "mcp:read" scope). The scope is checked on every call, not only when listing tools.
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class McpScope
{
    public const READ = 'read';
    public const WRITE = 'write';

    public function __construct(
        public readonly string $scope,
    ) {
    }

    /**
     * @return string the OAuth scope name (e.g. "mcp:read")
     */
    public function getOAuthScope(): string
    {
        return 'mcp:'.$this->scope;
    }
}
