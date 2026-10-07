<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Value;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

/**
 * Returns the stored value of the choice (or the value/name of enums), never its label,
 * and describes the possible values with the choices configured in the field.
 *
 * @experimental
 */
final class ChoiceValueNormalizer implements McpValueNormalizerInterface
{
    public function supports(FieldDto $field): bool
    {
        return ChoiceField::class === $field->getFieldFqcn();
    }

    public function getSchema(FieldDto $field): array
    {
        $values = [];
        $choices = (array) $field->getFormTypeOption('choices');
        array_walk_recursive($choices, function (mixed $choice) use (&$values): void {
            $values[] = $this->normalizeChoice($choice);
        });
        $itemSchema = ['enum' => array_values(array_unique(array_filter($values, static fn (mixed $value): bool => null !== $value), \SORT_REGULAR))];

        return true === $field->getCustomOption(ChoiceField::OPTION_ALLOW_MULTIPLE_CHOICES)
            ? ['type' => 'array', 'items' => $itemSchema]
            : $itemSchema;
    }

    public function normalize(FieldDto $field, mixed $value): mixed
    {
        if (is_iterable($value)) {
            $values = [];
            foreach ($value as $item) {
                $values[] = $this->normalizeChoice($item);
            }

            return $values;
        }

        return $this->normalizeChoice($value);
    }

    private function normalizeChoice(mixed $choice): int|string|float|bool|null
    {
        return match (true) {
            $choice instanceof \BackedEnum => $choice->value,
            $choice instanceof \UnitEnum => $choice->name,
            \is_scalar($choice) => $choice,
            default => null,
        };
    }
}
