<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Security;

use EasyCorp\Bundle\EasyAdminBundle\Mcp\Security\McpGrantResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Simulates the resource server of an OAuth authorization server: a bearer token
 * like "alice:mcp:read mcp:write" authenticates "alice" with the "mcp:read" and "mcp:write" scopes.
 */
final class TestOAuthAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function supports(Request $request): ?bool
    {
        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        [$username, $scopes] = explode(':', substr((string) $request->headers->get('Authorization'), 7), 2) + [1 => ''];
        $passport = new SelfValidatingPassport(new UserBadge($username));
        $passport->setAttribute('scopes', array_values(array_filter(explode(' ', $scopes))));

        return $passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);
        $token->setAttribute(McpGrantResolver::SCOPES_ATTRIBUTE, $passport->getAttribute('scopes'));
        $token->setAttribute(McpGrantResolver::CLIENT_ID_ATTRIBUTE, 'test-client');
        $token->setAttribute(McpGrantResolver::GRANT_ID_ATTRIBUTE, 'grant-'.$passport->getUser()->getUserIdentifier());

        return $token;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->start($request, $exception);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new Response('', Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Bearer resource_metadata="http://localhost/.well-known/oauth-protected-resource/admin/mcp"',
        ]);
    }
}
