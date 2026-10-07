<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp;

/**
 * Adds checks to the "easyadmin:mcp:doctor" command (e.g. the configuration
 * needed by MCP tools defined by other bundles).
 *
 * @experimental
 */
interface McpDoctorCheckInterface
{
    public const STATUS_OK = 'OK';
    public const STATUS_WARNING = 'WARNING';
    public const STATUS_ERROR = 'ERROR';

    /**
     * @return iterable<array{string, string, string}> the status (one of the STATUS_* constants), the name and the details of each check
     */
    public function check(): iterable;
}
