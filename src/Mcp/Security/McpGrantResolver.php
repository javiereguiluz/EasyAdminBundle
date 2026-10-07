<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Security;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Reads the OAuth scopes, client and grant of the current request from the security token.
 * It supports the tokens created by league/oauth2-server-bundle ("scopes" and "oauth_client_id"
 * attributes) and any token with an "oauth_grant_id" attribute.
 *
 * @experimental
 */
final readonly class McpGrantResolver
{
    public const SCOPES_ATTRIBUTE = 'scopes';
    public const CLIENT_ID_ATTRIBUTE = 'oauth_client_id';
    public const GRANT_ID_ATTRIBUTE = 'oauth_grant_id';

    public function __construct(
        private ?TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @return McpGrant|null null when the request wasn't authenticated with OAuth (in that case, MCP tools must not run)
     */
    public function resolve(): ?McpGrant
    {
        $token = $this->tokenStorage?->getToken();
        if (null === $token || !$token->hasAttribute(self::SCOPES_ATTRIBUTE)) {
            return null;
        }

        $scopes = $token->getAttribute(self::SCOPES_ATTRIBUTE);
        if (!\is_array($scopes)) {
            return null;
        }

        // unknown scopes are ignored (e.g. "mcp:write" when the application can't write) instead of failing
        $scopes = array_values(array_filter($scopes, \is_string(...)));
        $clientId = $token->hasAttribute(self::CLIENT_ID_ATTRIBUTE) ? $token->getAttribute(self::CLIENT_ID_ATTRIBUTE) : null;
        $grantId = $token->hasAttribute(self::GRANT_ID_ATTRIBUTE) ? $token->getAttribute(self::GRANT_ID_ATTRIBUTE) : null;

        return new McpGrant(
            $scopes,
            \is_scalar($clientId) ? (string) $clientId : null,
            \is_scalar($grantId) ? (string) $grantId : null,
        );
    }
}
