<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Contracts;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionExtensionContext;

/**
 * Contract for services that extend actions in EasyAdmin.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
interface ActionsExtension
{
    /**
     * Returns the priority of this extension.
     * Higher values mean higher priority (executed first).
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Extends the actions with custom logic.
     * Returns a modified Actions instance instead of mutating in place.
     *
     * @param Actions $actions The current actions configuration
     * @param ActionExtensionContext $context Context information for the extension
     *
     * @return Actions The modified actions configuration
     */
    public function extend(Actions $actions, ActionExtensionContext $context): Actions;
}