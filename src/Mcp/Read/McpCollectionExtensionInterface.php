<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Read;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpCollection;

/**
 * Adds information to the collections returned by the list_collections and
 * describe_collection MCP tools (e.g. the actions and the form fields used by
 * MCP tools defined by other bundles).
 *
 * @experimental
 */
interface McpCollectionExtensionInterface
{
    /**
     * Returns the extra actions that the user can run on the collection. They are listed
     * as they are returned, so they must be already checked against the permissions of the user.
     *
     * @param AdminContext<object>            $context        the context of the INDEX action of the collection
     * @param CrudControllerInterface<object> $crudController
     *
     * @return list<string>
     */
    public function getAllowedActions(McpCollection $collection, AdminContext $context, CrudControllerInterface $crudController): array;

    /**
     * @param array<string, mixed> $description    the description returned by describe_collection
     * @param string|null          $sampleRecordId the id of a record that the user can access, to process fields with a real record (null when the collection is empty)
     *
     * @return array<string, mixed> the description with the added information
     */
    public function extendDescription(McpCollection $collection, array $description, ?string $sampleRecordId): array;
}
