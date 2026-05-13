<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Provider;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AssociationContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ControllerFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Resolves and caches the target CrudDto for a given CRUD controller, so callers
 * can perform permission checks against it without rebuilding the full AdminContext
 * once per row (e.g. AssociationField links on an index page).
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class AssociationContextProvider implements AssociationContextProviderInterface, ResetInterface
{
    /** @var array<string, ?CrudDto> */
    private array $cache = [];

    public function __construct(
        private readonly ControllerFactory $controllerFactory,
        private readonly AdminContextProviderInterface $adminContextProvider,
        private readonly AdminContextFactory $adminContextFactory,
    ) {
    }

    public function getCrudDto(string $crudControllerFqcn, string $crudAction): ?CrudDto
    {
        $key = $crudControllerFqcn.'::'.$crudAction;
        if (\array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $sourceContext = $this->adminContextProvider->getContext();
        if (null === $sourceContext) {
            return $this->cache[$key] = null;
        }

        // a fresh Request is used on purpose: EA-specific attributes from the main request
        // (entity id, filters, sort, etc.) would otherwise leak into the target controller's context.
        // the voter only consumes action permissions and disabled actions from the resulting CrudDto.
        // the locale is copied so the target context's translated entity labels match the source page.
        $request = new Request();
        $request->setLocale($sourceContext->getRequest()->getLocale());

        $dashboardController = $this->controllerFactory->getDashboardControllerInstance(
            $sourceContext->getDashboardControllerFqcn(),
            $request,
        );

        $crudController = $this->controllerFactory->getCrudControllerInstance(
            $crudControllerFqcn,
            $crudAction,
            $request,
        );

        if (null === $crudController || null === $dashboardController) {
            return $this->cache[$key] = null;
        }

        $targetContext = $this->adminContextFactory->create(
            $request,
            $dashboardController,
            $crudController,
            $crudAction,
        );

        return $this->cache[$key] = $targetContext->getCrud();
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
