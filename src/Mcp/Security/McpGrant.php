<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Security;

/**
 * The authorization that the user gave to an MCP client.
 *
 * @experimental
 */
final readonly class McpGrant
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public array $scopes,
        public ?string $clientId,
        public ?string $grantId,
    ) {
    }

    public function hasScope(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }
}
