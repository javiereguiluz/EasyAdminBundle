<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Read;

use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\CollectionResolver;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\RecordLoader;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Value\FieldValueNormalizer;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Value\McpAssociationValue;

/**
 * Turns records with processed fields into arrays that can be sent to MCP clients.
 *
 * @experimental
 */
final readonly class RecordNormalizer
{
    public function __construct(
        private FieldValueNormalizer $valueNormalizer,
        private CollectionResolver $collectionResolver,
        private RecordLoader $recordLoader,
    ) {
    }

    /**
     * @param iterable<EntityDto> $entityDtos records whose fields have been processed by FieldFactory
     *
     * @return list<array{id: string, fields: array<string, mixed>, truncated_fields?: list<string>}>
     */
    public function normalize(iterable $entityDtos): array
    {
        $records = [];
        /** @var array<class-string, array<string, true>> $associatedIds related record ids grouped by CRUD controller */
        $associatedIds = [];

        foreach ($entityDtos as $entityDto) {
            if (!$entityDto->isAccessible() || null === $entityDto->getFields()) {
                continue;
            }

            $record = ['id' => $entityDto->getPrimaryKeyValueAsString(), 'fields' => []];
            foreach ($entityDto->getFields() as $field) {
                if (!$this->valueNormalizer->isSupported($field)) {
                    continue;
                }

                $normalizedValue = $this->valueNormalizer->normalize($field);
                $record['fields'][$field->getProperty()] = $normalizedValue->value;
                if ($normalizedValue->truncated) {
                    $record['truncated_fields'][] = $field->getProperty();
                }

                if ($normalizedValue->value instanceof McpAssociationValue && null !== $normalizedValue->value->targetCrudControllerFqcn) {
                    foreach (array_keys($normalizedValue->value->records) as $id) {
                        $associatedIds[$normalizedValue->value->targetCrudControllerFqcn][(string) $id] = true;
                    }
                }
            }

            $records[] = $record;
        }

        $labels = $this->getAssociationLabels($associatedIds);

        foreach ($records as $i => $record) {
            foreach ($record['fields'] as $property => $value) {
                if ($value instanceof McpAssociationValue) {
                    $records[$i]['fields'][$property] = $this->normalizeAssociation($value, $labels[$value->targetCrudControllerFqcn ?? ''] ?? []);
                }
            }
        }

        return $records;
    }

    /**
     * Labels are only included for the related records that the user can access through
     * an exposed collection; otherwise, labels could leak data that the user can't see.
     *
     * @param array<class-string, array<string, true>> $associatedIds
     *
     * @return array<string, array<string, string>> labels indexed by CRUD controller and record id
     */
    private function getAssociationLabels(array $associatedIds): array
    {
        if ([] === $associatedIds) {
            return [];
        }

        $collectionsByCrudController = [];
        foreach ($this->collectionResolver->getCollections() as $collection) {
            $collectionsByCrudController[$collection->crudControllerFqcn] = $collection;
        }

        $labels = [];
        foreach ($associatedIds as $crudControllerFqcn => $ids) {
            if (null === $collection = $collectionsByCrudController[$crudControllerFqcn] ?? null) {
                continue;
            }

            foreach ($this->recordLoader->findAccessible($collection, array_map('strval', array_keys($ids))) as $id => $record) {
                if ($record instanceof \Stringable) {
                    $labels[$crudControllerFqcn][$id] = mb_substr((string) $record, 0, 255);
                }
            }
        }

        return $labels;
    }

    /**
     * @param array<string, string> $labels
     *
     * @return array<string, mixed>
     */
    private function normalizeAssociation(McpAssociationValue $value, array $labels): array
    {
        $items = [];
        foreach (array_keys($value->records) as $id) {
            $id = (string) $id;
            $items[] = isset($labels[$id]) ? ['id' => $id, 'label' => $labels[$id]] : ['id' => $id];
        }

        if (!$value->toMany) {
            return $items[0];
        }

        return ['count' => $value->count, 'items' => $items];
    }
}
