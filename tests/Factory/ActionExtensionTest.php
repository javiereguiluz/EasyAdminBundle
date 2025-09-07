<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Factory;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\ActionsExtension;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionExtensionContext;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ActionExtensionRegistry;
use PHPUnit\Framework\TestCase;

class ActionExtensionTest extends TestCase
{
    public function testExtensionsAreSortedByPriority(): void
    {
        $extension1 = new TestActionExtension(10);
        $extension2 = new TestActionExtension(20);
        $extension3 = new TestActionExtension(15);
        
        $extensions = [$extension1, $extension2, $extension3];
        usort($extensions, function ($a, $b) {
            if ($a->getPriority() === $b->getPriority()) {
                return 0;
            }
            return $b->getPriority() - $a->getPriority();
        });
        
        $this->assertSame(20, $extensions[0]->getPriority());
        $this->assertSame(15, $extensions[1]->getPriority());
        $this->assertSame(10, $extensions[2]->getPriority());
    }

    public function testExtensionCanAddNewAction(): void
    {
        $actions = Actions::new()
            ->add(Crud::PAGE_INDEX, Action::NEW);
        
        $extension = new AddActionExtension();
        $context = $this->createContext(Crud::PAGE_INDEX);
        
        $modifiedActions = $extension->extend($actions, $context);
        $dto = $modifiedActions->getAsDto(Crud::PAGE_INDEX);
        
        $this->assertNotNull($dto->getAction(Crud::PAGE_INDEX, 'custom_action'));
        $this->assertNotNull($dto->getAction(Crud::PAGE_INDEX, Action::NEW));
    }

    public function testExtensionCanOverwriteExistingAction(): void
    {
        $actions = Actions::new()
            ->add(Crud::PAGE_INDEX, Action::NEW);
        
        $extension = new OverwriteActionExtension();
        $context = $this->createContext(Crud::PAGE_INDEX);
        
        $modifiedActions = $extension->extend($actions, $context);
        $dto = $modifiedActions->getAsDto(Crud::PAGE_INDEX);
        
        $newAction = $dto->getAction(Crud::PAGE_INDEX, Action::NEW);
        $this->assertNotNull($newAction);
        $this->assertSame('Custom New Label', $newAction->getLabel());
    }

    public function testExtensionCanRemoveAction(): void
    {
        $actions = Actions::new()
            ->add(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::EDIT);
        
        $extension = new RemoveActionExtension();
        $context = $this->createContext(Crud::PAGE_INDEX);
        
        $modifiedActions = $extension->extend($actions, $context);
        $dto = $modifiedActions->getAsDto(Crud::PAGE_INDEX);
        
        $this->assertNull($dto->getAction(Crud::PAGE_INDEX, Action::EDIT));
        $this->assertNotNull($dto->getAction(Crud::PAGE_INDEX, Action::NEW));
    }

    public function testLastExtensionWinsOnNameCollision(): void
    {
        $actions = Actions::new()
            ->add(Crud::PAGE_INDEX, Action::NEW);
        
        $extension1 = new OverwriteActionExtension('First Label');
        $extension2 = new OverwriteActionExtension('Second Label');
        
        $context = $this->createContext(Crud::PAGE_INDEX);
        
        $modifiedActions = $extension1->extend($actions, $context);
        $modifiedActions = $extension2->extend($modifiedActions, $context);
        
        $dto = $modifiedActions->getAsDto(Crud::PAGE_INDEX);
        $newAction = $dto->getAction(Crud::PAGE_INDEX, Action::NEW);
        
        $this->assertNotNull($newAction);
        $this->assertSame('Second Label', $newAction->getLabel());
    }

    public function testExtensionReceivesCorrectContext(): void
    {
        $extension = new ContextAwareExtension();
        $context = new ActionExtensionContext(
            'App\Controller\ProductCrudController',
            'App\Entity\Product',
            Crud::PAGE_INDEX,
            'App\Controller\DashboardController',
            null,
            ['ROLE_USER', 'ROLE_ADMIN']
        );
        
        $actions = Actions::new();
        $modifiedActions = $extension->extend($actions, $context);
        $dto = $modifiedActions->getAsDto(Crud::PAGE_INDEX);
        
        $customAction = $dto->getAction(Crud::PAGE_INDEX, 'context_action');
        $this->assertNotNull($customAction);
        $this->assertSame('Action for Product', $customAction->getLabel());
    }

    private function createContext(string $pageName): ActionExtensionContext
    {
        return new ActionExtensionContext(
            'App\Controller\TestCrudController',
            'App\Entity\TestEntity',
            $pageName,
            'App\Controller\TestDashboardController',
            null,
            []
        );
    }
}

class TestActionExtension implements ActionsExtension
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

class AddActionExtension implements ActionsExtension
{
    public function getPriority(): int
    {
        return 0;
    }

    public function extend(Actions $actions, ActionExtensionContext $context): Actions
    {
        return $actions->add($context->getPageName(), Action::new('custom_action', 'Custom Action')->linkToUrl('#'));
    }
}

class OverwriteActionExtension implements ActionsExtension
{
    public function __construct(private string $label = 'Custom New Label')
    {
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function extend(Actions $actions, ActionExtensionContext $context): Actions
    {
        return $actions->update($context->getPageName(), Action::NEW, function($action) {
            return $action->setLabel($this->label);
        });
    }
}

class RemoveActionExtension implements ActionsExtension
{
    public function getPriority(): int
    {
        return 0;
    }

    public function extend(Actions $actions, ActionExtensionContext $context): Actions
    {
        return $actions->remove($context->getPageName(), Action::EDIT);
    }
}

class ContextAwareExtension implements ActionsExtension
{
    public function getPriority(): int
    {
        return 0;
    }

    public function extend(Actions $actions, ActionExtensionContext $context): Actions
    {
        if ($context->getEntityFqcn() === 'App\Entity\Product') {
            $actions = $actions->add(
                $context->getPageName(), 
                Action::new('context_action', 'Action for Product')->linkToUrl('#')
            );
        }
        
        return $actions;
    }
}