<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Mcp;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\CollectionResolver;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpCollection;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity\Organization;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Boots the MCP test app with two organizations ("Organization A" and "Organization B")
 * and pushes a request that simulates the HTTP request of an MCP tool call.
 */
abstract class AbstractMcpTestCase extends KernelTestCase
{
    use McpTestDataTrait;

    protected RequestStack $requestStack;
    protected Request $mcpRequest;
    protected EntityManagerInterface $entityManager;
    /** @var array<string, int> product ids indexed by product name */
    protected array $productIds = [];

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        // fills the EasyAdmin route cache, as the router does in a real application
        $container->get('router')->getRouteCollection();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;
        $this->productIds = self::createTestData($entityManager);

        /** @var RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $this->requestStack = $requestStack;
        $this->mcpRequest = Request::create('/mcp', 'POST', ['crudAction' => 'delete', 'entityId' => '999']);
        $this->requestStack->push($this->mcpRequest);
    }

    protected function tearDown(): void
    {
        // a sub-request left on the stack would be a bug, so it must not be masked
        $this->assertSame($this->mcpRequest, $this->requestStack->getCurrentRequest());
        $this->requestStack->pop();

        parent::tearDown();
    }

    /**
     * @param list<string> $roles
     */
    protected function logIn(string $username, array $roles = ['ROLE_USER']): void
    {
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = static::getContainer()->get('security.token_storage');
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser($username, '1234', $roles), 'main', $roles));
    }

    protected function getCollection(string $id): McpCollection
    {
        return $this->getCollectionResolver()->getCollections()[$id] ?? throw new \LogicException(sprintf('The "%s" collection is not exposed.', $id));
    }

    protected function getCollectionResolver(): CollectionResolver
    {
        /** @var CollectionResolver $collectionResolver */
        $collectionResolver = static::getContainer()->get('test.'.CollectionResolver::class);

        return $collectionResolver;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $serviceId
     *
     * @return T
     */
    protected function getMcpService(string $serviceId): object
    {
        /** @var T $service */
        $service = static::getContainer()->get('test.'.$serviceId);

        return $service;
    }
}
