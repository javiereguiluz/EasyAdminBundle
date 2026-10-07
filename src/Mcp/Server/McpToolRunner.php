<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Server;

use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityNotFoundException;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception\McpAccessDeniedException;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception\McpInvalidArgumentException;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception\McpRecordNotFoundException;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpCallContext;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Security\McpGrant;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Security\McpGrantResolver;
use Mcp\Exception\ToolCallException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Runs the code of MCP tools applying the rules common to all of them:
 * OAuth scope, rate limit, time limit and the MCP call context.
 *
 * @experimental
 */
final readonly class McpToolRunner
{
    /**
     * @param array<string, string> $toolScopes the OAuth scope required by each tool (e.g. 'list_records' => 'mcp:read')
     */
    public function __construct(
        private McpCallContext $callContext,
        private McpGrantResolver $grantResolver,
        private ?TokenStorageInterface $tokenStorage,
        private ?RateLimiterFactory $rateLimiterFactory,
        private array $toolScopes,
        private int $timeLimit,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     *
     * @throws ToolCallException when the call is refused or fails because of its arguments
     */
    public function run(string $toolName, ?string $collectionId, ?string $operation, callable $callback): mixed
    {
        $grant = $this->checkScope($toolName);
        $this->checkRateLimit($grant);

        $previousTimeLimit = (int) \ini_get('max_execution_time');
        if ($this->timeLimit > 0) {
            set_time_limit($this->timeLimit);
        }

        $this->callContext->start($toolName, $collectionId, $operation, $grant->clientId, $grant->grantId, $grant->scopes);
        try {
            return $callback();
        } catch (McpAccessDeniedException|McpInvalidArgumentException|McpRecordNotFoundException $e) {
            throw new ToolCallException($e->getMessage(), 0, $e);
        } catch (EntityNotFoundException $e) {
            throw new ToolCallException('The record doesn\'t exist or you can\'t access it.', 0, $e);
        } finally {
            $this->callContext->reset();
            // the limit is restored for long-running processes (e.g. workers) that handle several requests
            if ($this->timeLimit > 0) {
                set_time_limit($previousTimeLimit);
            }
        }
    }

    private function checkScope(string $toolName): McpGrant
    {
        // fail closed: tools without a declared scope never run
        if (null === $requiredScope = $this->toolScopes[$toolName] ?? null) {
            throw new ToolCallException(sprintf('The "%s" tool doesn\'t declare its OAuth scope with the #[McpScope] attribute, so it can\'t run.', $toolName));
        }

        $grant = $this->grantResolver->resolve();
        if (null === $grant || !$grant->hasScope($requiredScope)) {
            throw new ToolCallException(sprintf('The "%s" tool requires an access token with the "%s" scope.', $toolName, $requiredScope));
        }

        return $grant;
    }

    private function checkRateLimit(McpGrant $grant): void
    {
        if (null === $this->rateLimiterFactory) {
            return;
        }

        // the limit is per user (not per grant) so registering many clients doesn't multiply it
        $limit = $this->rateLimiterFactory->create($this->tokenStorage?->getToken()?->getUserIdentifier() ?? '')->consume();
        if (!$limit->isAccepted()) {
            throw new ToolCallException(sprintf('Too many calls. Wait %d seconds before calling any tool again.', max(1, $limit->getRetryAfter()->getTimestamp() - time())));
        }
    }
}
