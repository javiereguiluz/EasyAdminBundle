<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Value;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

/**
 * Normalizes the values of the fields using the first normalizer that supports
 * each field. Fields not supported by any normalizer are never normalized.
 *
 * @experimental
 */
final readonly class FieldValueNormalizer
{
    /**
     * @param iterable<McpValueNormalizerInterface> $normalizers
     */
    public function __construct(
        private iterable $normalizers,
        private int $maxStringLength,
    ) {
    }

    public function isSupported(FieldDto $field): bool
    {
        return null !== $this->getNormalizer($field);
    }

    /**
     * @return array<string, mixed>|null null if the field is not supported
     */
    public function getSchema(FieldDto $field): ?array
    {
        if (null === $normalizer = $this->getNormalizer($field)) {
            return null;
        }

        // masked values (e.g. "**** 1234") can't follow the schema of the original value
        if ($this->usesFormatValueCallable($field)) {
            return ['type' => 'string', 'description' => 'formatted value'];
        }

        return $normalizer->getSchema($field);
    }

    /**
     * @throws \LogicException if the field is not supported
     */
    public function normalize(FieldDto $field): McpNormalizedValue
    {
        if (null === $normalizer = $this->getNormalizer($field)) {
            throw new \LogicException(sprintf('The "%s" field (of type "%s") is not supported over MCP. Check isSupported() before calling normalize().', $field->getProperty(), $field->getFieldFqcn()));
        }

        // the output of formatValue() wins over the raw value because it often masks data (e.g. showing only the last 4 digits of a card)
        if ($this->usesFormatValueCallable($field)) {
            $formattedValue = $field->getFormattedValue();
            if (null === $formattedValue || (!\is_scalar($formattedValue) && !$formattedValue instanceof \Stringable)) {
                return new McpNormalizedValue(null);
            }

            return $this->truncate(trim(html_entity_decode(strip_tags((string) $formattedValue), \ENT_QUOTES | \ENT_HTML5)));
        }

        $value = $field->getValue();
        if (null === $value) {
            return new McpNormalizedValue(null);
        }

        $normalizedValue = $normalizer->normalize($field, $value);

        return \is_string($normalizedValue) ? $this->truncate($normalizedValue) : new McpNormalizedValue($normalizedValue);
    }

    private function getNormalizer(FieldDto $field): ?McpValueNormalizerInterface
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($field)) {
                return $normalizer;
            }
        }

        return null;
    }

    private function usesFormatValueCallable(FieldDto $field): bool
    {
        return null !== $field->getFormatValueCallable();
    }

    private function truncate(string $value): McpNormalizedValue
    {
        if (mb_strlen($value) <= $this->maxStringLength) {
            return new McpNormalizedValue($value);
        }

        return new McpNormalizedValue(mb_substr($value, 0, $this->maxStringLength), true);
    }
}
