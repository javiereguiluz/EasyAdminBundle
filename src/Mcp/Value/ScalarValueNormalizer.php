<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Value;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CurrencyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\LanguageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\LocaleField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\PercentField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimezoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\Uid\AbstractUid;

/**
 * @experimental
 */
final class ScalarValueNormalizer implements McpValueNormalizerInterface
{
    private const SCHEMAS = [
        TextField::class => ['type' => 'string'],
        TextareaField::class => ['type' => 'string'],
        TextEditorField::class => ['type' => 'string', 'contentMediaType' => 'text/html'],
        EmailField::class => ['type' => 'string', 'format' => 'email'],
        UrlField::class => ['type' => 'string', 'format' => 'uri'],
        TelephoneField::class => ['type' => 'string'],
        SlugField::class => ['type' => 'string'],
        ColorField::class => ['type' => 'string'],
        CountryField::class => ['type' => 'string', 'description' => 'ISO 3166-1 alpha-2 country code'],
        CurrencyField::class => ['type' => 'string', 'description' => 'ISO 4217 currency code'],
        LanguageField::class => ['type' => 'string', 'description' => 'ISO 639 language code'],
        LocaleField::class => ['type' => 'string', 'description' => 'locale code'],
        TimezoneField::class => ['type' => 'string', 'description' => 'IANA timezone identifier'],
        IdField::class => ['type' => ['integer', 'string']],
        IntegerField::class => ['type' => 'integer'],
        // decimal values are kept as strings to not lose precision
        NumberField::class => ['type' => ['number', 'string']],
        PercentField::class => ['type' => ['number', 'string']],
        BooleanField::class => ['type' => 'boolean'],
    ];

    public function supports(FieldDto $field): bool
    {
        return isset(self::SCHEMAS[$field->getFieldFqcn() ?? '']);
    }

    public function getSchema(FieldDto $field): array
    {
        return self::SCHEMAS[$field->getFieldFqcn() ?? ''];
    }

    public function normalize(FieldDto $field, mixed $value): mixed
    {
        if (BooleanField::class === $field->getFieldFqcn()) {
            return (bool) $value;
        }

        if (\is_float($value) && !is_finite($value)) {
            return null;
        }

        if (\is_scalar($value)) {
            return $value;
        }

        if (IdField::class === $field->getFieldFqcn() && $value instanceof AbstractUid) {
            // the same format used by the backend (e.g. RFC 4122 for UUIDs and base 32 for ULIDs)
            return (string) $value;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        // values of other types (e.g. objects shown with a text field) are never cast to strings
        // because their string representation can contain data that the field doesn't show
        return null;
    }
}
