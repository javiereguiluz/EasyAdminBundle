<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Value;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;

/**
 * Normalizes Doctrine associations into McpAssociationValue objects, which
 * RecordNormalizer later turns into {id} or {id, label} items.
 *
 * @experimental
 */
final readonly class AssociationValueNormalizer implements McpValueNormalizerInterface
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private AdminControllerRegistryInterface $adminControllers,
        private int $maxToManyItems,
    ) {
    }

    public function supports(FieldDto $field): bool
    {
        if (!\in_array($field->getFieldFqcn(), [AssociationField::class, CollectionField::class], true)) {
            return false;
        }

        // collection fields can also hold scalar values (e.g. JSON arrays), which aren't supported
        return null !== $field->getDoctrineMetadata()->get('targetEntity');
    }

    public function getSchema(FieldDto $field): array
    {
        $itemSchema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => ['integer', 'string']],
                'label' => ['type' => 'string', 'description' => 'only included when the related record can be accessed'],
            ],
            'required' => ['id'],
        ];

        if (!$this->isToMany($field)) {
            return $itemSchema;
        }

        return [
            'type' => 'object',
            'properties' => [
                'count' => ['type' => 'integer', 'description' => 'the total number of related records'],
                'items' => ['type' => 'array', 'items' => $itemSchema, 'description' => sprintf('at most %d related records', $this->maxToManyItems)],
            ],
        ];
    }

    public function normalize(FieldDto $field, mixed $value): mixed
    {
        $targetEntityFqcn = $field->getDoctrineMetadata()->get('targetEntity');
        $targetCrudControllerFqcn = $field->getCustomOption(AssociationField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER)
            ?? $field->getCustomOption(CollectionField::OPTION_ENTRY_CRUD_CONTROLLER_FQCN)
            ?? (\is_string($targetEntityFqcn) ? $this->adminControllers->findCrudControllerByEntity($targetEntityFqcn) : null);

        if (!$this->isToMany($field)) {
            if (!\is_object($value) || null === $id = $this->getId($value)) {
                return null;
            }

            return new McpAssociationValue($targetCrudControllerFqcn, false, [$id => $value], 1);
        }

        if (!is_iterable($value)) {
            return null;
        }

        // Doctrine collections count and slice their items without loading all of them (e.g. with EXTRA_LAZY associations)
        $count = is_countable($value) ? \count($value) : iterator_count($value);
        $firstRecords = $value instanceof Collection ? $value->slice(0, $this->maxToManyItems) : $value;

        $records = [];
        foreach ($firstRecords as $relatedRecord) {
            if (\count($records) >= $this->maxToManyItems) {
                break;
            }

            if (\is_object($relatedRecord) && null !== $id = $this->getId($relatedRecord)) {
                $records[$id] = $relatedRecord;
            }
        }

        return new McpAssociationValue($targetCrudControllerFqcn, true, $records, $count);
    }

    private function isToMany(FieldDto $field): bool
    {
        return 0 !== ((int) $field->getDoctrineMetadata()->get('type') & ClassMetadata::TO_MANY);
    }

    private function getId(object $record): ?string
    {
        $entityManager = $this->doctrine->getManagerForClass($record::class);
        if (null === $entityManager) {
            return null;
        }

        $idValues = $entityManager->getClassMetadata($record::class)->getIdentifierValues($record);
        $id = 1 === \count($idValues) ? reset($idValues) : null;

        return \is_scalar($id) || $id instanceof \Stringable ? (string) $id : null;
    }
}
