<?php

use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Security\TestOAuthAuthenticator;
use Symfony\Component\Security\Core\User\InMemoryUser;

$container->loadFromExtension('security', [
    'password_hashers' => [
        InMemoryUser::class => 'plaintext',
    ],

    'providers' => [
        'test_users' => [
            'memory' => [
                'users' => [
                    'user' => [
                        'password' => '1234',
                        'roles' => ['ROLE_USER'],
                    ],
                    'alice' => [
                        'password' => '1234',
                        'roles' => ['ROLE_USER'],
                    ],
                    'bob' => [
                        'password' => '1234',
                        'roles' => ['ROLE_USER'],
                    ],
                    'admin' => [
                        'password' => '1234',
                        'roles' => ['ROLE_ADMIN'],
                    ],
                ],
            ],
        ],
    ],

    'firewalls' => [
        // simulates the resource server of an OAuth authorization server
        'mcp' => [
            'pattern' => '^/admin/mcp',
            'stateless' => true,
            'provider' => 'test_users',
            'custom_authenticators' => [TestOAuthAuthenticator::class],
        ],
        'main' => [
            'provider' => 'test_users',
            'http_basic' => null,
        ],
    ],

    // without this rule, requests without a token reach the MCP server (whose tools refuse
    // to run) instead of getting the 401 response that makes MCP clients start the OAuth flow
    'access_control' => [
        ['path' => '^/admin/mcp', 'roles' => 'IS_AUTHENTICATED_FULLY'],
        ['path' => '^/admin/backend-restricted-product', 'roles' => 'ROLE_ADMIN'],
    ],

    'role_hierarchy' => [
        'ROLE_ADMIN' => ['ROLE_USER'],
    ],
]);
