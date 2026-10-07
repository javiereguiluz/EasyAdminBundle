<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Value;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use Money\Money;

/**
 * @experimental
 */
final class MoneyValueNormalizer implements McpValueNormalizerInterface
{
    public function supports(FieldDto $field): bool
    {
        return MoneyField::class === $field->getFieldFqcn();
    }

    public function getSchema(FieldDto $field): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'amount' => ['type' => 'string', 'description' => 'decimal amount (e.g. "1234.50")'],
                'currency' => ['type' => ['string', 'null'], 'description' => 'ISO 4217 currency code'],
            ],
        ];
    }

    public function normalize(FieldDto $field, mixed $value): mixed
    {
        $amount = class_exists(Money::class) && $value instanceof Money ? $value->getAmount() : $value;
        if (!is_numeric($amount)) {
            return null;
        }

        $divisor = $field->getFormTypeOption('divisor') ?? 1;
        $scale = $field->getFormTypeOption('scale') ?? $field->getCustomOption(MoneyField::OPTION_NUM_DECIMALS) ?? 2;

        return [
            'amount' => number_format($amount / $divisor, $scale, '.', ''),
            'currency' => $field->getFormTypeOption('currency'),
        ];
    }
}
