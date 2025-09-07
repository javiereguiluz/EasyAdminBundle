<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Factory;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\ActionsExtension;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionExtensionContext;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ActionExtensionRegistry;
use PHPUnit\Framework\TestCase;

class ActionExtensionRegistryTest extends TestCase
{
    public function testRegistryReturnsExtensionsInOrder(): void
    {
        $extension1 = $this->createMock(ActionsExtension::class);
        $extension1->method('getPriority')->willReturn(10);
        
        $extension2 = $this->createMock(ActionsExtension::class);
        $extension2->method('getPriority')->willReturn(20);
        
        $extension3 = $this->createMock(ActionsExtension::class);
        $extension3->method('getPriority')->willReturn(15);
        
        $registry = new ActionExtensionRegistry([$extension2, $extension3, $extension1]);
        $extensions = $registry->getExtensions();
        
        $this->assertCount(3, $extensions);
        $this->assertSame($extension2, $extensions[0]);
        $this->assertSame($extension3, $extensions[1]);
        $this->assertSame($extension1, $extensions[2]);
    }

    public function testRegistryWithNoExtensions(): void
    {
        $registry = new ActionExtensionRegistry([]);
        $extensions = $registry->getExtensions();
        
        $this->assertCount(0, $extensions);
    }

    public function testGetExtensionsDebugInfo(): void
    {
        $extension1 = new TestExtension(10);
        $extension2 = new TestExtension(20);
        
        $registry = new ActionExtensionRegistry([$extension1, $extension2]);
        $debugInfo = $registry->getExtensionsDebugInfo();
        
        $this->assertCount(2, $debugInfo);
        $this->assertSame(TestExtension::class, $debugInfo[0]['class']);
        $this->assertSame(10, $debugInfo[0]['priority']);
        $this->assertSame(TestExtension::class, $debugInfo[1]['class']);
        $this->assertSame(20, $debugInfo[1]['priority']);
    }
}

class TestExtension implements ActionsExtension
{
    public function __construct(private int $priority)
    {
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function extend(Actions $actions, ActionExtensionContext $context): Actions
    {
        return $actions;
    }
}