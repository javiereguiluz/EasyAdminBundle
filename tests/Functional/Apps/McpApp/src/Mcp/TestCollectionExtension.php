<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpCollection;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Read\McpCollectionExtensionInterface;

/**
 * Adds an "export" action and a description key to the "tag" collection.
 */
final class TestCollectionExtension implements McpCollectionExtensionInterface
{
    public function getAllowedActions(McpCollection $collection, AdminContext $context, CrudControllerInterface $crudController): array
    {
        return 'tag' === $collection->id ? ['export', 'index'] : [];
    }

    public function extendDescription(McpCollection $collection, array $description, ?string $sampleRecordId): array
    {
        if ('tag' === $collection->id) {
            $description['x_test_extension'] = ['sample_record_id' => $sampleRecordId];
        }

        return $description;
    }
}
