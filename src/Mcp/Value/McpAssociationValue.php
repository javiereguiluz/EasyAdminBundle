<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Value;

/**
 * The value of an association before resolving the labels of the related records,
 * which needs to check (in a single query per target collection) if the user
 * can access each of those records.
 *
 * @experimental
 */
final readonly class McpAssociationValue
{
    /**
     * @param class-string|null     $targetCrudControllerFqcn
     * @param array<string, object> $records                  the related records indexed by their id (at most the configured limit)
     * @param int                   $count                    the total number of related records
     */
    public function __construct(
        public ?string $targetCrudControllerFqcn,
        public bool $toMany,
        public array $records,
        public int $count,
    ) {
    }
}
