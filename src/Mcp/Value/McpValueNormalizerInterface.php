<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Value;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

/**
 * Turns the values of a field into data that can be sent to MCP clients as JSON.
 * Fields not supported by any normalizer are never sent to MCP clients.
 *
 * Services implementing this interface are autoconfigured with the
 * "ea.mcp_value_normalizer" tag (use the tag priority to run before the built-in ones).
 *
 * @experimental
 */
interface McpValueNormalizerInterface
{
    public function supports(FieldDto $field): bool;

    /**
     * @return array<string, mixed> the JSON Schema of the normalized value (e.g. ['type' => 'string', 'format' => 'email'])
     */
    public function getSchema(FieldDto $field): array;

    /**
     * @param mixed $value the raw value of the field (it's never null)
     *
     * @return mixed a value that can be encoded as JSON
     */
    public function normalize(FieldDto $field, mixed $value): mixed;
}
