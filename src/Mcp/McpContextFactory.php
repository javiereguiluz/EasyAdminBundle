<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ControllerFactory;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception\McpAccessDeniedException;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\EventListener\IsGrantedAttributeListener;

/**
 * Runs code inside the EasyAdmin context of a CRUD action, applying the same
 * rules as the HTTP controller: #[IsGranted] on the controller class and method,
 * BeforeCrudActionEvent listeners and the EA_EXECUTE_ACTION permission.
 *
 * @experimental
 */
final readonly class McpContextFactory
{
    public const SUPPORTED_ACTIONS = [Action::INDEX, Action::DETAIL, Action::NEW, Action::EDIT, Action::DELETE];

    public function __construct(
        private RequestStack $requestStack,
        private HttpKernelInterface $httpKernel,
        private CollectionResolver $collectionResolver,
        private ControllerFactory $controllerFactory,
        private AdminContextFactory $adminContextFactory,
        private EventDispatcherInterface $eventDispatcher,
        private ?TokenStorageInterface $tokenStorage,
        private ?AuthorizationCheckerInterface $authorizationChecker,
        private ?IsGrantedAttributeListener $isGrantedAttributeListener,
        private AdminRouteGeneratorInterface $adminRouteGenerator,
        private UrlGeneratorInterface $urlGenerator,
        private ?AccessMapInterface $accessMap,
    ) {
    }

    /**
     * When an entity id is given, the entity is loaded by id without the restrictions of
     * createIndexQueryBuilder(), so callers must verify the record with RecordLoader
     * before returning any of its data.
     *
     * @template T
     *
     * @param array<string, mixed>                               $query    the query parameters of the sub-request (e.g. search, filters, sort and page)
     * @param callable(AdminContext, CrudControllerInterface): T $callback it runs while the sub-request with the EasyAdmin context is the current request
     *
     * @return T
     *
     * @throws McpAccessDeniedException
     */
    public function run(McpCollection $collection, string $action, string|int|null $entityId, array $query, callable $callback): mixed
    {
        if (!\in_array($action, self::SUPPORTED_ACTIONS, true)) {
            throw new \InvalidArgumentException(sprintf('The "%s" action is not supported over MCP. Supported actions: "%s".', $action, implode('", "', self::SUPPORTED_ACTIONS)));
        }

        // fail closed: EasyAdmin's own authorization checker allows everything when
        // the application has no security, which is never acceptable over MCP
        if (null === $this->authorizationChecker || null === $this->isGrantedAttributeListener) {
            throw new McpAccessDeniedException('MCP calls require Symfony SecurityBundle to be installed and enabled.');
        }

        // tokens without a real user (e.g. some OAuth "client credentials" tokens) are refused too
        $user = $this->tokenStorage?->getToken()?->getUser();
        if (!$user instanceof UserInterface || '' === $user->getUserIdentifier()) {
            throw new McpAccessDeniedException('MCP calls require an authenticated user.');
        }

        if (null === $mcpDashboard = $this->collectionResolver->findMcpDashboard()) {
            throw new McpAccessDeniedException('No dashboard exposes CRUD controllers to MCP.');
        }
        $dashboardFqcn = $mcpDashboard[0]::class;

        if (null === $mcpRequest = $this->requestStack->getCurrentRequest()) {
            throw new \LogicException('MCP tool calls must run inside an HTTP request.');
        }

        $attributes = [
            EA::DASHBOARD_CONTROLLER_FQCN => $dashboardFqcn,
            EA::CRUD_CONTROLLER_FQCN => $collection->crudControllerFqcn,
            EA::CRUD_ACTION => $action,
            EA::ROUTE_CREATED_BY_EASYADMIN => true,
            '_locale' => $mcpRequest->getLocale(),
        ];
        if (null !== $entityId) {
            $attributes[EA::ENTITY_ID] = $entityId;
        }

        // only the server parameters (host, scheme, headers) of the MCP request are kept, so the query,
        // the body and the cookies sent by the MCP client never reach the code of the backend
        $subRequest = new Request($query, [], $attributes, [], [], $mcpRequest->server->all());
        // read actions are GET requests in the backend (e.g. the filters form only reads GET parameters)
        $subRequest->setMethod('GET');
        $subRequest->setLocale($mcpRequest->getLocale());
        $subRequest->setDefaultLocale($mcpRequest->getDefaultLocale());
        // some features (e.g. CSRF tokens created while processing fields) need a session,
        // but MCP requests are stateless; this session is never persisted
        $subRequest->setSession(new Session(new MockArraySessionStorage()));

        $this->checkBackendAccessControl($dashboardFqcn, $collection, $action, $entityId, $mcpRequest);

        $this->requestStack->push($subRequest);
        try {
            $dashboardController = $this->controllerFactory->getDashboardControllerInstance($dashboardFqcn, $subRequest);
            $crudController = $this->controllerFactory->getCrudControllerInstance($collection->crudControllerFqcn, $action, $subRequest);
            if (null === $dashboardController || null === $crudController) {
                throw new \LogicException(sprintf('The "%s" dashboard or the "%s" CRUD controller cannot be instantiated.', $dashboardFqcn, $collection->crudControllerFqcn));
            }

            try {
                $context = $this->adminContextFactory->create($subRequest, $dashboardController, $crudController, $action);
                $subRequest->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);

                $this->checkIsGrantedAttributes($crudController, $action, $context, $subRequest);

                $event = new BeforeCrudActionEvent($context);
                $this->eventDispatcher->dispatch($event);
            } catch (AccessDeniedException|AccessDeniedHttpException $e) {
                throw McpAccessDeniedException::actionDenied(sprintf('You don\'t have permission to run the "%s" action of the "%s" collection.', $action, $collection->id), $e);
            }

            if ($event->isPropagationStopped()) {
                throw McpAccessDeniedException::actionDenied(sprintf('The "%s" action of the "%s" collection was stopped by the application.', $action, $collection->id));
            }

            $entity = null === $entityId ? null : $context->getEntity();
            if (!$this->authorizationChecker->isGranted(Permission::EA_EXECUTE_ACTION, ['action' => $action, 'entity' => $entity, 'entityFqcn' => $collection->entityFqcn, 'crud' => $context->getCrud()])) {
                throw McpAccessDeniedException::actionDenied(sprintf('You don\'t have permission to run the "%s" action of the "%s" collection.', $action, $collection->id));
            }

            return $callback($context, $crudController);
        } finally {
            $this->requestStack->pop();
        }
    }

    /**
     * Checks the #[IsGranted] attributes of the controller method that handles the given action,
     * without running it. Use it inside the callback passed to run().
     */
    public function isGrantedByAttributes(CrudControllerInterface $crudController, string $action, AdminContext $context): bool
    {
        if (!\in_array($action, self::SUPPORTED_ACTIONS, true) || null === $this->isGrantedAttributeListener) {
            return false;
        }

        try {
            $this->checkIsGrantedAttributes($crudController, $action, $context, $context->getRequest());
        } catch (McpAccessDeniedException) {
            return false;
        }

        return true;
    }

    /**
     * Applies #[IsGranted] with the same semantics as the HTTP request by running
     * Symfony's own listener on the controller method that would handle the action.
     */
    private function checkIsGrantedAttributes(CrudControllerInterface $crudController, string $methodName, AdminContext $context, Request $subRequest): void
    {
        $event = new ControllerArgumentsEvent($this->httpKernel, [$crudController, $methodName], [$context], $subRequest, HttpKernelInterface::SUB_REQUEST);

        try {
            $this->isGrantedAttributeListener?->onKernelControllerArguments($event);
        } catch (AccessDeniedException|HttpException $e) {
            throw McpAccessDeniedException::actionDenied(sprintf('You don\'t have permission to run the "%s" action (denied by an #[IsGranted] attribute).', $methodName), $e);
        }
    }

    /**
     * Applies the access_control rules of the security config to the backend URL of the action,
     * as if the user browsed it. Without this, a user who can't access the backend pages
     * (e.g. because of an "^/admin" rule) could read the same data over MCP.
     */
    private function checkBackendAccessControl(string $dashboardFqcn, McpCollection $collection, string $action, string|int|null $entityId, Request $mcpRequest): void
    {
        if (null === $routeName = $this->adminRouteGenerator->findRouteName($dashboardFqcn, $collection->crudControllerFqcn, $action)) {
            throw McpAccessDeniedException::actionDenied(sprintf('The "%s" action of the "%s" collection doesn\'t exist in the backend.', $action, $collection->id));
        }

        if (null === $this->accessMap) {
            return;
        }

        // both the canonical "entityId" placeholder and its "id" alias are passed because routes can use either
        $entityIdValue = $entityId ?? '0';
        try {
            $backendUrl = $this->urlGenerator->generate($routeName, [EA::ENTITY_ID => $entityIdValue, 'id' => $entityIdValue]);
        } catch (RoutingExceptionInterface $e) {
            throw McpAccessDeniedException::actionDenied(sprintf('The "%s" action of the "%s" collection can\'t be checked against the access_control rules of the backend.', $action, $collection->id), $e);
        }
        $backendRequest = Request::create($backendUrl, 'GET', [], [], [], $mcpRequest->server->all());

        [$attributes] = $this->accessMap->getPatterns($backendRequest);
        if (null === $attributes || [] === $attributes || [AuthenticatedVoter::PUBLIC_ACCESS] === $attributes) {
            return;
        }

        foreach ($attributes as $attribute) {
            if ($this->authorizationChecker?->isGranted($attribute, $backendRequest)) {
                return;
            }
        }

        throw McpAccessDeniedException::actionDenied(sprintf('You don\'t have permission to run the "%s" action of the "%s" collection (denied by an access_control rule of the backend).', $action, $collection->id));
    }
}
