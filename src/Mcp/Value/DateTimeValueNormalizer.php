<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Value;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;

/**
 * @experimental
 */
final class DateTimeValueNormalizer implements McpValueNormalizerInterface
{
    private const FORMATS = [
        DateField::class => ['date', 'Y-m-d'],
        DateTimeField::class => ['date-time', \DateTimeInterface::ATOM],
        TimeField::class => ['time', 'H:i:s'],
    ];

    public function supports(FieldDto $field): bool
    {
        return isset(self::FORMATS[$field->getFieldFqcn() ?? '']);
    }

    public function getSchema(FieldDto $field): array
    {
        return ['type' => 'string', 'format' => self::FORMATS[$field->getFieldFqcn() ?? ''][0]];
    }

    public function normalize(FieldDto $field, mixed $value): mixed
    {
        if (!$value instanceof \DateTimeInterface) {
            return null;
        }

        return $value->format(self::FORMATS[$field->getFieldFqcn() ?? ''][1]);
    }
}
