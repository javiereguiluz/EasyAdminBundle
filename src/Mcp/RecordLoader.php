<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception\McpAccessDeniedException;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception\McpRecordNotFoundException;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Loads records by id through the createIndexQueryBuilder() method of their CRUD
 * controller (instead of the find() method of the repository) so all the restrictions
 * applied to the index page (e.g. multi-tenant filters) also apply to them.
 *
 * @experimental
 */
final readonly class RecordLoader
{
    public function __construct(
        private McpContextFactory $contextFactory,
        private FilterFactory $filterFactory,
        private ?AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * Loads the record running the INDEX action of the collection, so the user must be allowed
     * to run it. It throws McpRecordNotFoundException if the record can't be loaded.
     */
    public function load(McpCollection $collection, string|int $id): object
    {
        return $this->contextFactory->run($collection, Action::INDEX, null, [], fn (AdminContext $context, CrudControllerInterface $crudController): object => $this->loadInIndexContext($context, $crudController, $id));
    }

    /**
     * @param list<string|int> $ids
     *
     * @return list<object> the records in the same order as the given ids (it throws McpRecordNotFoundException if any of them can't be loaded)
     */
    public function loadMany(McpCollection $collection, array $ids): array
    {
        return $this->contextFactory->run($collection, Action::INDEX, null, [], function (AdminContext $context, CrudControllerInterface $crudController) use ($ids): array {
            $records = [];
            foreach ($ids as $id) {
                $records[] = $this->loadInIndexContext($context, $crudController, $id);
            }

            return $records;
        });
    }

    /**
     * Returns the records that the user can access among the given ones, using a single query.
     * It never throws when access is denied: it returns no records instead.
     *
     * @param list<string|int> $ids
     *
     * @return array<string, object> the accessible records indexed by their id
     */
    public function findAccessible(McpCollection $collection, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        try {
            return $this->contextFactory->run($collection, Action::INDEX, null, [], function (AdminContext $context, CrudControllerInterface $crudController) use ($ids): array {
                $entityDto = $context->getEntity();
                $idName = $entityDto->getClassMetadata()->getSingleIdentifierFieldName();
                $queryBuilder = $this->createIndexQueryBuilder($context, $crudController);
                // each id is a separate parameter so it's converted with the type of the id (e.g. binary UUIDs)
                $idType = $entityDto->getClassMetadata()->getTypeOfField($idName);
                $placeholders = [];
                foreach (array_values(array_unique($ids)) as $i => $id) {
                    $placeholders[] = ':mcp_record_id_'.$i;
                    $queryBuilder->setParameter('mcp_record_id_'.$i, $id, $idType);
                }
                $queryBuilder->andWhere(sprintf('%s.%s IN (%s)', $queryBuilder->getRootAliases()[0], $idName, implode(', ', $placeholders)));

                $records = [];
                foreach ($this->getEntities($queryBuilder->getQuery()->getResult(), $entityDto->getFqcn()) as $record) {
                    if (!$this->isAccessible($entityDto, $record)) {
                        continue;
                    }

                    $id = $entityDto->getClassMetadata()->getIdentifierValues($record)[$idName] ?? null;
                    if (\is_scalar($id) || $id instanceof \Stringable) {
                        $records[(string) $id] = $record;
                    }
                }

                return $records;
            });
        } catch (McpAccessDeniedException) {
            return [];
        }
    }

    /**
     * Use this method only inside the context of the INDEX action created by McpContextFactory.
     *
     * @throws McpRecordNotFoundException
     */
    public function loadInIndexContext(AdminContext $context, CrudControllerInterface $crudController, string|int $id): object
    {
        if (Action::INDEX !== $context->getCrud()?->getCurrentAction()) {
            throw new \LogicException('Records can only be loaded in the context of the INDEX action.');
        }

        $entityDto = $context->getEntity();
        $metadata = $entityDto->getClassMetadata();
        $idName = $metadata->getSingleIdentifierFieldName();

        $queryBuilder = $this->createIndexQueryBuilder($context, $crudController);
        $queryBuilder
            ->andWhere(sprintf('%s.%s = :mcp_record_id', $queryBuilder->getRootAliases()[0], $idName))
            ->setParameter('mcp_record_id', $id, $metadata->getTypeOfField($idName));

        $record = $this->getEntities($queryBuilder->getQuery()->getResult(), $entityDto->getFqcn())[0] ?? null;
        if (null === $record || !$this->isAccessible($entityDto, $record)) {
            throw new McpRecordNotFoundException(sprintf('The record "%s" doesn\'t exist or you can\'t access it.', $id));
        }

        return $record;
    }

    private function createIndexQueryBuilder(AdminContext $context, CrudControllerInterface $crudController): QueryBuilder
    {
        $entityDto = $context->getEntity();
        $fields = new FieldCollection($crudController->configureFields(Crud::PAGE_INDEX));
        $filters = $this->filterFactory->create($context->getCrud()->getFiltersConfig(), $fields, $entityDto);

        // results are never limited with setMaxResults() because it breaks queries that fetch-join collections
        return $crudController->createIndexQueryBuilder($context->getSearch(), $entityDto, $fields, $filters);
    }

    /**
     * @param iterable<mixed> $results
     * @param class-string    $entityFqcn
     *
     * @return list<object>
     */
    private function getEntities(iterable $results, string $entityFqcn): array
    {
        $entities = [];
        foreach ($results as $result) {
            // queries with custom SELECT clauses can return arrays instead of entities
            $candidate = \is_array($result) ? ($result[0] ?? null) : $result;
            if ($candidate instanceof $entityFqcn) {
                $entities[] = $candidate;
            }
        }

        return $entities;
    }

    private function isAccessible(EntityDto $entityDto, object $record): bool
    {
        return null !== $this->authorizationChecker
            && $this->authorizationChecker->isGranted(Permission::EA_ACCESS_ENTITY, $entityDto->newWithInstance($record));
    }
}
