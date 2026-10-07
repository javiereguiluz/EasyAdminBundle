<?php

use Symfony\AI\McpBundle\McpBundle;

if (!class_exists(McpBundle::class)) {
    return;
}

$container->loadFromExtension('mcp', [
    'servers' => [
        'easyadmin' => [
            'instructions' => 'This server gives read-only access to the data of an EasyAdmin backend. It is read-only: it cannot create, change or delete data. Call list_collections first, then describe_collection before listing or reading records.',
            'http' => [
                'path' => '/admin/mcp',
            ],
            'session' => [
                'store' => 'memory',
            ],
            'registry' => ['EasyCorp\\Bundle\\'],
        ],
    ],
]);
