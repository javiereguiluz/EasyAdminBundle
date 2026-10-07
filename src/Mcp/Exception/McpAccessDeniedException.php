<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception;

/**
 * Thrown when an MCP tool call is refused by any of the EasyAdmin rules
 * that apply over MCP (exposure, permissions, #[IsGranted], event listeners).
 *
 * @experimental
 */
final class McpAccessDeniedException extends \RuntimeException
{
    private bool $actionDenied = false;

    /**
     * Creates the exception used when the user can't run a specific action of a collection
     * (as opposed to errors that affect all calls, such as a missing authenticated user).
     */
    public static function actionDenied(string $message, ?\Throwable $previous = null): self
    {
        $exception = new self($message, 0, $previous);
        $exception->actionDenied = true;

        return $exception;
    }

    public function isActionDenied(): bool
    {
        return $this->actionDenied;
    }
}
