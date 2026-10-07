<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Server;

use EasyCorp\Bundle\EasyAdminBundle\Mcp\Security\McpGrantResolver;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * Hides from tools/list the tools whose OAuth scope wasn't granted, and refuses
 * their calls. Tools that don't declare a scope with #[McpScope] (e.g. tools
 * of other bundles) are not affected. McpToolRunner checks the scope again on
 * every call, so this handler is not the only protection.
 *
 * @experimental
 *
 * @implements RequestHandlerInterface<ListToolsResult|CallToolResult>
 */
final readonly class McpToolScopeHandler implements RequestHandlerInterface
{
    /**
     * @param array<string, string> $toolScopes the OAuth scope required by each tool
     */
    public function __construct(
        private RegistryInterface $registry,
        private McpGrantResolver $grantResolver,
        private array $toolScopes,
    ) {
    }

    public function supports(Request $request): bool
    {
        // servers that don't expose any tool with a declared scope keep the built-in handler of the SDK
        if ($request instanceof ListToolsRequest) {
            return $this->hasScopedTools();
        }

        // allowed calls are not handled here, so they reach the built-in handler of the SDK
        return $request instanceof CallToolRequest && !$this->isAllowed($request->name);
    }

    public function handle(Request $request, SessionInterface $session): Response
    {
        if ($request instanceof CallToolRequest) {
            return new Response($request->getId(), CallToolResult::error([
                new TextContent(sprintf('The "%s" tool requires an access token with the "%s" scope.', $request->name, $this->toolScopes[$request->name] ?? '')),
            ]));
        }

        $page = $this->registry->getTools(null, $request instanceof ListToolsRequest ? $request->cursor : null);
        $tools = array_values(array_filter($page->references, fn ($tool): bool => $this->isAllowed($tool->name)));

        return new Response($request->getId(), new ListToolsResult($tools, $page->nextCursor));
    }

    private function hasScopedTools(): bool
    {
        foreach ($this->registry->getTools()->references as $tool) {
            if (isset($this->toolScopes[$tool->name])) {
                return true;
            }
        }

        return false;
    }

    private function isAllowed(string $toolName): bool
    {
        if (null === $requiredScope = $this->toolScopes[$toolName] ?? null) {
            return true;
        }

        return true === $this->grantResolver->resolve()?->hasScope($requiredScope);
    }
}
