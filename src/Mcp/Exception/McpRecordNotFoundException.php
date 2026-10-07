<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception;

/**
 * Thrown when a record doesn't exist or the user can't access it. Both cases
 * use the same exception so MCP clients can't learn which records exist.
 *
 * @experimental
 */
final class McpRecordNotFoundException extends \RuntimeException
{
}
