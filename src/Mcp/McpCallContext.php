<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Holds the data of the MCP tool call being run. Code that needs to know if it's
 * running inside an MCP call (e.g. to log it) must use this service instead of
 * looking at the route of the current request, which isn't reliable with sub-requests.
 *
 * @experimental
 */
final class McpCallContext implements ResetInterface
{
    private ?string $toolName = null;
    private ?string $collectionId = null;
    private ?string $operation = null;
    private ?string $clientId = null;
    private ?string $grantId = null;
    /** @var list<string> */
    private array $scopes = [];

    /**
     * @param list<string> $scopes
     */
    public function start(string $toolName, ?string $collectionId, ?string $operation, ?string $clientId, ?string $grantId, array $scopes): void
    {
        if ($this->isActive()) {
            throw new \LogicException(sprintf('Cannot start the MCP call of the "%s" tool because the call of the "%s" tool is still active. Call reset() when a tool call ends.', $toolName, $this->toolName));
        }

        $this->toolName = $toolName;
        $this->collectionId = $collectionId;
        $this->operation = $operation;
        $this->clientId = $clientId;
        $this->grantId = $grantId;
        $this->scopes = $scopes;
    }

    public function isActive(): bool
    {
        return null !== $this->toolName;
    }

    public function getToolName(): string
    {
        return $this->toolName ?? throw new \LogicException('There is no active MCP call. Check isActive() before calling getToolName().');
    }

    public function getCollectionId(): ?string
    {
        return $this->collectionId;
    }

    public function getOperation(): ?string
    {
        return $this->operation;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function getGrantId(): ?string
    {
        return $this->grantId;
    }

    /**
     * @return list<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function hasScope(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }

    public function reset(): void
    {
        $this->toolName = null;
        $this->collectionId = null;
        $this->operation = null;
        $this->clientId = null;
        $this->grantId = null;
        $this->scopes = [];
    }
}
