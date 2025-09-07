<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Factory;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\ActionsExtension;

/**
 * Registry that holds all action extensions sorted by priority.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class ActionExtensionRegistry
{
    /**
     * @var ActionsExtension[]
     */
    private array $extensions;

    /**
     * @param ActionsExtension[] $extensions Sorted by priority (highest first)
     */
    public function __construct(array $extensions = [])
    {
        $this->extensions = $extensions;
    }

    /**
     * @return ActionsExtension[]
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Returns information about extensions for debugging purposes.
     *
     * @return array{class: string, priority: int}[]
     */
    public function getExtensionsDebugInfo(): array
    {
        $debugInfo = [];
        foreach ($this->extensions as $extension) {
            $debugInfo[] = [
                'class' => \get_class($extension),
                'priority' => $extension->getPriority(),
            ];
        }
        
        return $debugInfo;
    }
}