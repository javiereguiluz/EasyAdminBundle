<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception;

/**
 * Thrown when the arguments of an MCP tool call are not valid (e.g. sorting by a field
 * that doesn't exist or that the user can't see). Its message is shown to MCP clients.
 *
 * @experimental
 */
final class McpInvalidArgumentException extends \InvalidArgumentException
{
}
