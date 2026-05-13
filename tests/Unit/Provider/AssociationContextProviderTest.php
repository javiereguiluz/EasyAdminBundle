<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Provider;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\DashboardContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ControllerFactory;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AssociationContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Controller\DashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Controller\ProjectDomain\DeveloperCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Controller\ProjectDomain\ProjectCrudController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AssociationContextProviderTest extends KernelTestCase
{
    public function testReturnsNullWhenNoSourceAdminContext(): void
    {
        $adminContextProvider = $this->createMock(AdminContextProviderInterface::class);
        $adminContextProvider->method('getContext')->willReturn(null);

        $provider = new AssociationContextProvider(
            static::getContainer()->get(ControllerFactory::class),
            $adminContextProvider,
            static::getContainer()->get(AdminContextFactory::class),
        );

        $this->assertNull($provider->getCrudDto(DeveloperCrudController::class, Action::DETAIL));
    }

    public function testReturnsNullWhenTargetCrudControllerCannotBeResolved(): void
    {
        $provider = $this->buildProviderWithSourceContext();

        // pass a CRUD controller FQCN that doesn't exist in the container
        // ControllerFactory cannot resolve it and getCrudControllerInstance() returns null
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $provider->getCrudDto('App\Controller\Admin\NonExistentCrudController', Action::DETAIL);
    }

    public function testReturnsCrudDtoForResolvedTargetController(): void
    {
        $provider = $this->buildProviderWithSourceContext();

        $crudDto = $provider->getCrudDto(DeveloperCrudController::class, Action::DETAIL);

        $this->assertInstanceOf(CrudDto::class, $crudDto);
        $this->assertSame(DeveloperCrudController::class, $crudDto->getControllerFqcn());
    }

    public function testCachesResultByControllerAndAction(): void
    {
        $provider = $this->buildProviderWithSourceContext();

        $first = $provider->getCrudDto(DeveloperCrudController::class, Action::DETAIL);
        $second = $provider->getCrudDto(DeveloperCrudController::class, Action::DETAIL);

        // same target controller + action returns the same instance — proves the heavy build didn't re-run
        $this->assertSame($first, $second);
    }

    public function testCacheKeyDistinguishesActions(): void
    {
        $provider = $this->buildProviderWithSourceContext();

        $detail = $provider->getCrudDto(DeveloperCrudController::class, Action::DETAIL);
        $index = $provider->getCrudDto(DeveloperCrudController::class, Action::INDEX);

        // different actions on the same controller produce distinct instances
        $this->assertNotSame($detail, $index);
    }

    public function testCacheKeyDistinguishesControllers(): void
    {
        $provider = $this->buildProviderWithSourceContext();

        $developerCrud = $provider->getCrudDto(DeveloperCrudController::class, Action::DETAIL);
        $projectCrud = $provider->getCrudDto(ProjectCrudController::class, Action::DETAIL);

        $this->assertNotSame($developerCrud, $projectCrud);
        $this->assertSame(DeveloperCrudController::class, $developerCrud->getControllerFqcn());
        $this->assertSame(ProjectCrudController::class, $projectCrud->getControllerFqcn());
    }

    public function testResetClearsCache(): void
    {
        $provider = $this->buildProviderWithSourceContext();

        $first = $provider->getCrudDto(DeveloperCrudController::class, Action::DETAIL);
        $provider->reset();
        $second = $provider->getCrudDto(DeveloperCrudController::class, Action::DETAIL);

        // after reset(), a subsequent call produces a freshly built CrudDto (different instance)
        $this->assertNotSame($first, $second);
        $this->assertInstanceOf(CrudDto::class, $second);
    }

    private function buildProviderWithSourceContext(): AssociationContextProvider
    {
        $requestStack = new RequestStack();
        $adminContext = AdminContext::forTesting(
            dashboardContext: DashboardContext::forTesting(dashboardControllerFqcn: DashboardController::class),
        );

        $request = new Request();
        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $adminContext);
        $requestStack->push($request);

        return new AssociationContextProvider(
            static::getContainer()->get(ControllerFactory::class),
            new AdminContextProvider($requestStack),
            static::getContainer()->get(AdminContextFactory::class),
        );
    }
}
