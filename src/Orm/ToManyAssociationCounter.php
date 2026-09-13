<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Orm;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Counts the elements of the to-many associations displayed for a collection of entities
 * (e.g. the rows of an index page) with a single grouped COUNT query per association, instead
 * of loading the entire collection of each entity to display the number of related elements.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class ToManyAssociationCounter
{
    /** @var array<class-string, array<string, array<string, int>>> entity FQCN => property name => entity ID => number of elements */
    private array $counts = [];

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    public function reset(): void
    {
        $this->counts = [];
    }

    public function preload(EntityCollection $entityDtos, FieldCollection $fields, string $pageName): void
    {
        $firstEntityDto = null;
        foreach ($entityDtos as $entityDto) {
            if (null !== $entityDto->getInstance()) {
                $firstEntityDto = $entityDto;
                break;
            }
        }

        if (null === $firstEntityDto) {
            return;
        }

        $classMetadata = $firstEntityDto->getClassMetadata();
        foreach ($this->findCountableProperties($fields, $classMetadata, $pageName) as $propertyName) {
            $entityDtosToCount = [];
            foreach ($entityDtos as $entityDto) {
                if (null !== $entityDto->getInstance() && $this->isCountable($this->readCollection($entityDto->getInstance(), $propertyName))) {
                    $entityDtosToCount[] = $entityDto;
                }
            }

            if (\count($entityDtosToCount) < 2) {
                continue;
            }

            $this->countAssociatedEntities($firstEntityDto->getFqcn(), $classMetadata, $propertyName, $entityDtosToCount);
        }
    }

    /**
     * Returns the preloaded number of elements of the given collection, which is the value
     * of the given property of the entity, or null if the collection must be counted directly
     * (e.g. because it's already loaded or because it contains changes not yet flushed).
     */
    public function getCount(EntityDto $entityDto, string $propertyName, mixed $collection): ?int
    {
        if (null === $entityDto->getInstance() || !$this->isCountable($collection)) {
            return null;
        }

        return $this->counts[$entityDto->getFqcn()][$propertyName][$entityDto->getPrimaryKeyValueAsString()] ?? null;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     *
     * @return list<string>
     */
    private function findCountableProperties(FieldCollection $fields, ClassMetadata $classMetadata, string $pageName): array
    {
        $propertyNames = [];
        foreach ($fields as $field) {
            $propertyName = $field->getProperty();

            if (str_contains($propertyName, '.') || !$field->isDisplayedOn($pageName)) {
                continue;
            }

            if (!$classMetadata->hasAssociation($propertyName) || !$classMetadata->isCollectionValuedAssociation($propertyName)) {
                continue;
            }

            if (!$this->isRenderedAsAssociationField($field->getFieldFqcn(), $classMetadata, $propertyName)) {
                continue;
            }

            $targetClassMetadata = $this->getEntityManager($classMetadata->getName())->getClassMetadata($classMetadata->getAssociationTargetClass($propertyName));
            if ($targetClassMetadata->isIdentifierComposite) {
                continue;
            }

            $propertyNames[$propertyName] = $propertyName;
        }

        return array_values($propertyNames);
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function isRenderedAsAssociationField(?string $fieldFqcn, ClassMetadata $classMetadata, string $propertyName): bool
    {
        if (AssociationField::class === $fieldFqcn) {
            return true;
        }

        if (Field::class !== $fieldFqcn) {
            return false;
        }

        // FieldFactory turns a generic field of a to-many association with orphan removal
        // into a CollectionField, which iterates the collection instead of counting it
        /** @var bool $orphanRemoval */
        $orphanRemoval = $classMetadata->getAssociationMapping($propertyName)['orphanRemoval'];

        return !$orphanRemoval;
    }

    private function readCollection(object $entityInstance, string $propertyName): mixed
    {
        if (!$this->propertyAccessor->isReadable($entityInstance, $propertyName)) {
            return null;
        }

        return $this->propertyAccessor->getValue($entityInstance, $propertyName);
    }

    private function isCountable(mixed $collection): bool
    {
        return $collection instanceof PersistentCollection && !$collection->isInitialized() && !$collection->isDirty();
    }

    /**
     * @param class-string          $entityFqcn
     * @param ClassMetadata<object> $classMetadata
     * @param list<EntityDto>       $entityDtos
     */
    private function countAssociatedEntities(string $entityFqcn, ClassMetadata $classMetadata, string $propertyName, array $entityDtos): void
    {
        $entityManager = $this->getEntityManager($entityFqcn);
        $identifierName = $classMetadata->getSingleIdentifierFieldName();
        $identifierType = $classMetadata->getTypeOfField($identifierName) ?? Types::STRING;

        // the IDs are converted explicitly because Doctrine infers the type of array parameters
        // from their PHP type, which turns objects such as UUIDs into strings that don't match
        // the value stored in the database
        $identifierValues = [];
        foreach ($entityDtos as $entityDto) {
            $identifierValues[] = $entityManager->getConnection()->convertToDatabaseValue($entityDto->getPrimaryKeyValue(), $identifierType);
        }
        $parameterType = \in_array($identifierType, [Types::INTEGER, Types::SMALLINT], true) ? ArrayParameterType::INTEGER : ArrayParameterType::STRING;

        $dql = sprintf(
            'SELECT entity.%1$s AS entityId, COUNT(associated) AS numElements FROM %2$s entity LEFT JOIN entity.%3$s associated WHERE entity.%1$s IN (:ids) GROUP BY entity.%1$s',
            $identifierName,
            $entityFqcn,
            $propertyName,
        );

        $rows = $entityManager->createQuery($dql)
            ->setParameter('ids', $identifierValues, $parameterType)
            ->getScalarResult();

        foreach ($rows as $row) {
            $this->counts[$entityFqcn][$propertyName][(string) $row['entityId']] = (int) $row['numElements'];
        }
    }

    /**
     * @param class-string $entityFqcn
     */
    private function getEntityManager(string $entityFqcn): EntityManagerInterface
    {
        $entityManager = $this->doctrine->getManagerForClass($entityFqcn);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \RuntimeException(sprintf('There is no Doctrine Entity Manager defined for the "%s" class.', $entityFqcn));
        }

        return $entityManager;
    }
}
