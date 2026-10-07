<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp;

/**
 * The MCP exposure explicitly configured for a CRUD controller,
 * either with a PHP attribute or with a Crud method.
 *
 * @experimental
 */
final readonly class McpCrudExposure
{
    private const ALIAS_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    private function __construct(
        public bool $exposed,
        public bool $readOnly = false,
        public ?string $alias = null,
    ) {
        if (null !== $alias && 1 !== preg_match(self::ALIAS_PATTERN, $alias)) {
            throw new \InvalidArgumentException(sprintf('The MCP alias "%s" is not valid. It must start with a lowercase letter and contain only lowercase letters, numbers and underscores (64 characters at most).', $alias));
        }
    }

    public static function exposed(bool $readOnly = false, ?string $alias = null): self
    {
        return new self(true, $readOnly, $alias);
    }

    public static function excluded(): self
    {
        return new self(false);
    }
}
