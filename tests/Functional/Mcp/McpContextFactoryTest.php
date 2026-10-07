<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Exception\McpAccessDeniedException;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpContextFactory;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Controller\ProductCrudController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

class McpContextFactoryTest extends AbstractMcpTestCase
{
    public function testOnlyExposedCollectionsAreResolved(): void
    {
        $this->assertSame(
            ['admin_products', 'backend_restricted_products', 'blocked_products', 'catalog', 'detail_protected_products', 'entity_permission_products', 'method_products', 'organizations', 'permission_products', 'product', 'tag', 'tenant_products'],
            $this->getSortedKeys($this->getCollectionResolver()->getCollections()),
        );
    }

    public function testRunsInsideTheSubRequestOfTheAction(): void
    {
        $this->logIn('user', ['ROLE_USER']);

        $result = $this->getFactory()->run($this->getCollection('product'), Action::INDEX, null, ['query' => 'Product'], function (AdminContext $context): string {
            $currentRequest = $this->requestStack->getCurrentRequest();

            $this->assertNotSame($this->mcpRequest, $currentRequest);
            $this->assertSame($context, $currentRequest->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE));
            $this->assertTrue($currentRequest->attributes->getBoolean(EA::ROUTE_CREATED_BY_EASYADMIN));
            // the query of the MCP request never reaches the sub-request
            $this->assertSame(['query' => 'Product'], $currentRequest->query->all());
            $this->assertSame(ProductCrudController::class, $context->getCrud()->getControllerFqcn());
            $this->assertSame(Action::INDEX, $context->getCrud()->getCurrentAction());

            return 'result';
        });

        $this->assertSame('result', $result);
        $this->assertSame($this->mcpRequest, $this->requestStack->getCurrentRequest());
    }

    public function testEntityIsLoadedForDetail(): void
    {
        $this->logIn('user', ['ROLE_USER']);

        $productName = $this->getFactory()->run($this->getCollection('product'), Action::DETAIL, $this->productIds['Product A1'], [], static fn (AdminContext $context): string => $context->getEntity()->getInstance()->getName());

        $this->assertSame('Product A1', $productName);
    }

    public function testFailsClosedWithoutAuthenticatedUser(): void
    {
        $this->expectException(McpAccessDeniedException::class);
        $this->expectExceptionMessage('MCP calls require an authenticated user.');

        $this->getFactory()->run($this->getCollection('product'), Action::INDEX, null, [], static fn (): null => null);
    }

    public function testIsGrantedOnControllerClass(): void
    {
        $this->logIn('admin', ['ROLE_ADMIN']);
        $this->assertTrue($this->getFactory()->run($this->getCollection('admin_products'), Action::INDEX, null, [], static fn (): bool => true));

        $this->logIn('user', ['ROLE_USER']);
        $this->expectException(McpAccessDeniedException::class);
        $this->expectExceptionMessage('You don\'t have permission to run the "index" action (denied by an #[IsGranted] attribute).');

        $this->getFactory()->run($this->getCollection('admin_products'), Action::INDEX, null, [], static fn (): bool => true);
    }

    public function testIsGrantedOnActionMethod(): void
    {
        $this->logIn('user', ['ROLE_USER']);
        $collection = $this->getCollection('detail_protected_products');

        $this->assertTrue($this->getFactory()->run($collection, Action::INDEX, null, [], static fn (): bool => true));

        $this->expectException(McpAccessDeniedException::class);
        $this->expectExceptionMessage('You don\'t have permission to run the "detail" action (denied by an #[IsGranted] attribute).');

        $this->getFactory()->run($collection, Action::DETAIL, $this->productIds['Product A1'], [], static fn (): bool => true);
    }

    public function testBeforeCrudActionEventListenerCanRefuseTheCall(): void
    {
        $this->logIn('admin', ['ROLE_ADMIN']);

        $this->expectException(McpAccessDeniedException::class);
        $this->expectExceptionMessage('The "index" action of the "blocked_products" collection was stopped by the application.');

        $this->getFactory()->run($this->getCollection('blocked_products'), Action::INDEX, null, [], static fn (): bool => true);
    }

    public function testEasyAdminActionPermission(): void
    {
        $this->logIn('admin', ['ROLE_ADMIN']);
        $this->assertTrue($this->getFactory()->run($this->getCollection('permission_products'), Action::INDEX, null, [], static fn (): bool => true));

        $this->logIn('user', ['ROLE_USER']);
        $this->expectException(McpAccessDeniedException::class);
        $this->expectExceptionMessage('You don\'t have permission to run the "index" action of the "permission_products" collection.');

        $this->getFactory()->run($this->getCollection('permission_products'), Action::INDEX, null, [], static fn (): bool => true);
    }

    public function testSubRequestIsPoppedWhenTheCallbackFails(): void
    {
        $this->logIn('user', ['ROLE_USER']);

        try {
            $this->getFactory()->run($this->getCollection('product'), Action::INDEX, null, [], static function (): never {
                throw new \RuntimeException('Callback failed.');
            });
            $this->fail('The exception of the callback must not be caught.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Callback failed.', $e->getMessage());
        }

        $this->assertSame($this->mcpRequest, $this->requestStack->getCurrentRequest());
    }

    public function testAccessControlOfTheBackendApplies(): void
    {
        $this->logIn('admin', ['ROLE_ADMIN']);
        $this->assertTrue($this->getFactory()->run($this->getCollection('backend_restricted_products'), Action::INDEX, null, [], static fn (): bool => true));

        // an access_control rule of the backend requires ROLE_ADMIN for the URLs of this CRUD controller
        $this->logIn('alice');
        $this->expectException(McpAccessDeniedException::class);
        $this->expectExceptionMessage('denied by an access_control rule of the backend');

        $this->getFactory()->run($this->getCollection('backend_restricted_products'), Action::INDEX, null, [], static fn (): bool => true);
    }

    public function testUsersWithoutIdentifierAreRefused(): void
    {
        // e.g. the user of some OAuth "client credentials" tokens
        $userWithoutIdentifier = new class implements UserInterface {
            public function getRoles(): array
            {
                return ['ROLE_ADMIN'];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return '';
            }
        };
        static::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($userWithoutIdentifier, 'main', ['ROLE_ADMIN']));

        $this->expectException(McpAccessDeniedException::class);
        $this->expectExceptionMessage('MCP calls require an authenticated user.');

        $this->getFactory()->run($this->getCollection('product'), Action::INDEX, null, [], static fn (): bool => true);
    }

    public function testSubRequestDoesNotContainTheBodyOrCookiesOfTheMcpRequest(): void
    {
        $this->logIn('alice');
        $this->mcpRequest->cookies->set('PHPSESSID', 'abc');

        $this->getFactory()->run($this->getCollection('product'), Action::INDEX, null, [], function (): void {
            $subRequest = $this->requestStack->getCurrentRequest();
            $this->assertSame([], $subRequest->request->all());
            $this->assertSame([], $subRequest->cookies->all());
            $this->assertSame('GET', $subRequest->getMethod());
        });
    }

    public function testUnsupportedAction(): void
    {
        $this->logIn('admin', ['ROLE_ADMIN']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "batchDelete" action is not supported over MCP.');

        $this->getFactory()->run($this->getCollection('product'), Action::BATCH_DELETE, null, [], static fn (): bool => true);
    }

    private function getFactory(): McpContextFactory
    {
        return $this->getMcpService(McpContextFactory::class);
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return list<string>
     */
    private function getSortedKeys(array $array): array
    {
        $keys = array_keys($array);
        sort($keys);

        return $keys;
    }
}
