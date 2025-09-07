<?php

namespace App\EasyAdmin\Extension;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\ActionsExtension;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionExtensionContext;

/**
 * Example 1: Add a custom "Export" action to all index pages
 * 
 * To use this extension:
 * 1. Create this class in your project
 * 2. Register it as a service with the 'ea.action_extension' tag:
 * 
 * services:
 *     App\EasyAdmin\Extension\ExportActionExtension:
 *         tags:
 *             - { name: 'ea.action_extension', priority: 10 }
 */
class ExportActionExtension implements ActionsExtension
{
    public function getPriority(): int
    {
        return 10;
    }

    public function extend(Actions $actions, ActionExtensionContext $context): Actions
    {
        // Only add export action to index pages
        if ($context->getPageName() !== Crud::PAGE_INDEX) {
            return $actions;
        }

        // Add export action to all index pages
        return $actions->add(
            Crud::PAGE_INDEX,
            Action::new('export', 'Export to CSV')
                ->linkToRoute('admin_export', [
                    'entity' => $context->getEntityFqcn(),
                ])
                ->createAsGlobalAction()
                ->addCssClass('btn btn-success')
                ->setIcon('fa fa-download')
        );
    }
}

/**
 * Example 2: Conditionally modify actions based on user roles
 */
class RoleBasedActionExtension implements ActionsExtension
{
    public function getPriority(): int
    {
        return 20; // Higher priority to run before other extensions
    }

    public function extend(Actions $actions, ActionExtensionContext $context): Actions
    {
        $userRoles = $context->getUserRoles();

        // Remove delete action for non-admin users
        if (!in_array('ROLE_ADMIN', $userRoles, true)) {
            $actions = $actions->remove($context->getPageName(), Action::DELETE);
            
            if ($context->getPageName() === Crud::PAGE_INDEX) {
                $actions = $actions->remove($context->getPageName(), Action::BATCH_DELETE);
            }
        }

        // Add special admin action for admin users
        if (in_array('ROLE_SUPER_ADMIN', $userRoles, true) && $context->getPageName() === Crud::PAGE_DETAIL) {
            $actions = $actions->add(
                Crud::PAGE_DETAIL,
                Action::new('audit', 'View Audit Log')
                    ->linkToRoute('admin_audit', ['id' => '__id__'])
                    ->addCssClass('btn btn-info')
            );
        }

        return $actions;
    }
}

/**
 * Example 3: Entity-specific action modifications
 */
class ProductActionExtension implements ActionsExtension
{
    public function getPriority(): int
    {
        return 5;
    }

    public function extend(Actions $actions, ActionExtensionContext $context): Actions
    {
        // Only apply to Product entity
        if ($context->getEntityFqcn() !== 'App\Entity\Product') {
            return $actions;
        }

        // Add duplicate action to detail and index pages
        if (in_array($context->getPageName(), [Crud::PAGE_DETAIL, Crud::PAGE_INDEX], true)) {
            $actions = $actions->add(
                $context->getPageName(),
                Action::new('duplicate', 'Duplicate Product')
                    ->linkToRoute('admin_product_duplicate', ['id' => '__id__'])
                    ->setIcon('fa fa-copy')
            );
        }

        // Modify the edit action label for products
        if ($context->getPageName() === Crud::PAGE_INDEX) {
            $actions = $actions->update(
                Crud::PAGE_INDEX,
                Action::EDIT,
                fn(Action $action) => $action->setLabel('Modify Product')
            );
        }

        return $actions;
    }
}

/**
 * Example 4: Override existing actions
 * This extension has higher priority and will override actions from other extensions
 */
class OverrideActionExtension implements ActionsExtension
{
    public function getPriority(): int
    {
        return 100; // Very high priority to ensure it runs first
    }

    public function extend(Actions $actions, ActionExtensionContext $context): Actions
    {
        // Replace the default NEW action with a custom implementation
        if ($context->getPageName() === Crud::PAGE_INDEX) {
            // Remove the default NEW action
            try {
                $actions = $actions->remove(Crud::PAGE_INDEX, Action::NEW);
            } catch (\InvalidArgumentException $e) {
                // Action doesn't exist, that's fine
            }

            // Add our custom NEW action
            $actions = $actions->add(
                Crud::PAGE_INDEX,
                Action::new(Action::NEW, 'Create New Item')
                    ->linkToRoute('custom_new_route', [
                        'entity' => $context->getEntityFqcn(),
                    ])
                    ->createAsGlobalAction()
                    ->addCssClass('btn btn-primary btn-lg')
                    ->setIcon('fa fa-plus-circle')
            );
        }

        return $actions;
    }
}

/**
 * Example 5: Add actions based on multiple conditions
 */
class ConditionalActionExtension implements ActionsExtension
{
    public function __construct(
        private bool $featureFlagEnabled = false
    ) {
    }

    public function getPriority(): int
    {
        return 0; // Default priority
    }

    public function extend(Actions $actions, ActionExtensionContext $context): Actions
    {
        // Only add beta features if feature flag is enabled
        if (!$this->featureFlagEnabled) {
            return $actions;
        }

        // Add beta action to specific entities
        $betaEntities = [
            'App\Entity\Product',
            'App\Entity\Order',
            'App\Entity\Customer',
        ];

        if (in_array($context->getEntityFqcn(), $betaEntities, true)) {
            $actions = $actions->add(
                $context->getPageName(),
                Action::new('beta_feature', 'Beta Feature')
                    ->linkToRoute('admin_beta_feature', [
                        'entity' => $context->getEntityFqcn(),
                        'id' => '__id__',
                    ])
                    ->addCssClass('btn btn-warning')
                    ->setHtmlAttributes([
                        'data-bs-toggle' => 'tooltip',
                        'title' => 'This is a beta feature',
                    ])
            );
        }

        return $actions;
    }
}

/**
 * Configuration example in services.yaml:
 * 
 * services:
 *     # Auto-configure all action extensions
 *     _instanceof:
 *         EasyCorp\Bundle\EasyAdminBundle\Contracts\ActionsExtension:
 *             tags: ['ea.action_extension']
 * 
 *     # Register individual extensions with specific priorities
 *     App\EasyAdmin\Extension\ExportActionExtension:
 *         tags:
 *             - { name: 'ea.action_extension', priority: 10 }
 * 
 *     App\EasyAdmin\Extension\RoleBasedActionExtension:
 *         tags:
 *             - { name: 'ea.action_extension', priority: 20 }
 * 
 *     App\EasyAdmin\Extension\ProductActionExtension:
 *         tags:
 *             - { name: 'ea.action_extension', priority: 5 }
 * 
 *     App\EasyAdmin\Extension\ConditionalActionExtension:
 *         arguments:
 *             $featureFlagEnabled: '%app.beta_features_enabled%'
 *         tags:
 *             - { name: 'ea.action_extension', priority: 0 }
 */