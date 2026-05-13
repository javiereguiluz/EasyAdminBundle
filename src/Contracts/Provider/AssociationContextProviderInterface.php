<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider;

use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;

/**
 * Inject this in services that need to resolve the CrudDto of a CRUD controller
 * other than the one currently being rendered (e.g. to run permission checks
 * against a target controller from an AssociationField link).
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
interface AssociationContextProviderInterface
{
    public function getCrudDto(string $crudControllerFqcn, string $crudAction): ?CrudDto;
}
