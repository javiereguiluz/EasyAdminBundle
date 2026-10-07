<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Schema;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Value\FieldValueNormalizer;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Generates the JSON Schema of the processed fields of a CRUD controller.
 *
 * The "required" information comes from the form options of the field, so it's only
 * a hint: data transformers, compound fields, dynamic options and constraints applied
 * to the whole object are not visible here.
 *
 * @experimental
 */
final readonly class FieldSchemaGenerator
{
    public function __construct(
        private FieldValueNormalizer $valueNormalizer,
        private ?TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, mixed>|null null if the field is not supported over MCP
     */
    public function generate(FieldDto $field): ?array
    {
        if (null === $schema = $this->valueNormalizer->getSchema($field)) {
            return null;
        }

        $schema['title'] = $this->getLabel($field);

        $descriptions = array_filter([$schema['description'] ?? null, $this->translate($field->getHelp())], static fn (?string $description): bool => null !== $description && '' !== $description);
        if ([] !== $descriptions) {
            $schema['description'] = implode('. ', $descriptions);
        }

        if (true === $field->isVirtual()) {
            $schema['readOnly'] = true;
        }

        $schema['x-sortable'] = false !== $field->isSortable() && true !== $field->isVirtual();

        if (true === $field->getFormTypeOption('required')) {
            $schema['x-required'] = true;
        }

        return $schema;
    }

    public function getLabel(FieldDto $field): string
    {
        $label = $this->translate($field->getLabel());

        return null === $label || '' === $label ? $field->getProperty() : $label;
    }

    private function translate(TranslatableInterface|string|bool|null $message): ?string
    {
        if ($message instanceof TranslatableInterface) {
            $message = null === $this->translator ? null : $message->trans($this->translator);
        }

        if (!\is_string($message)) {
            return null;
        }

        $message = trim(html_entity_decode(strip_tags($message), \ENT_QUOTES | \ENT_HTML5));

        return '' === $message ? null : $message;
    }
}
