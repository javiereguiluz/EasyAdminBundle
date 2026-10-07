<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Command;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\CollectionResolver;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpDoctorCheckInterface;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Server\ReadTools;
use Mcp\Capability\Attribute\McpTool;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * Checks the configuration needed to expose the backend to MCP clients.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
#[AsCommand(
    name: 'easyadmin:mcp:doctor',
    description: 'Checks the configuration of the EasyAdmin MCP server',
)]
class McpDoctorCommand extends Command
{
    private const STATUS_OK = McpDoctorCheckInterface::STATUS_OK;
    private const STATUS_WARNING = McpDoctorCheckInterface::STATUS_WARNING;
    private const STATUS_ERROR = McpDoctorCheckInterface::STATUS_ERROR;

    /**
     * @param array<string, class-string>       $bundles
     * @param array<string, mixed>              $limits
     * @param iterable<McpDoctorCheckInterface> $doctorChecks
     */
    public function __construct(
        private readonly CollectionResolver $collectionResolver,
        private readonly AdminRouteGeneratorInterface $adminRouteGenerator,
        private readonly RouterInterface $router,
        private readonly ?FirewallMap $firewallMap,
        private readonly ?AccessMapInterface $accessMap,
        private readonly array $bundles,
        private readonly array $limits,
        private readonly ?object $rateLimiterFactory,
        private readonly ContainerBagInterface $parameters,
        private readonly iterable $doctorChecks = [],
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('EasyAdmin MCP server');

        // the admin routes are cached when the router is first used; in the CLI that might not have happened yet
        if ([] === $this->adminRouteGenerator->getDashboardRoutes()) {
            $this->adminRouteGenerator->generateAll();
        }

        $checks = [];
        $checks[] = $this->checkMcpBundle();
        if (self::STATUS_OK === $checks[0][0]) {
            array_push($checks, ...$this->checkExposure());
            $endpointPath = $this->findEndpointPath();
            $checks[] = null === $endpointPath
                ? [self::STATUS_ERROR, 'MCP endpoint', 'No MCP server exposes the EasyAdmin tools. Add "EasyCorp\Bundle\" to the "registry" option of an MCP server and import the "mcp" routes.']
                : [self::STATUS_OK, 'MCP endpoint', $endpointPath];
            if (null !== $endpointPath) {
                array_push($checks, ...$this->checkSecurity($endpointPath));
            }
            foreach ($this->doctorChecks as $doctorCheck) {
                array_push($checks, ...$doctorCheck->check());
            }
        }
        $checks[] = $this->checkRateLimiter();

        $io->table(['Status', 'Check', 'Details'], array_map(static fn (array $check): array => [
            match ($check[0]) {
                self::STATUS_OK => '<info>OK</info>',
                self::STATUS_WARNING => '<comment>WARNING</comment>',
                default => '<error>ERROR</error>',
            },
            $check[1],
            $check[2],
        ], $checks));

        $io->note([
            'These checks can\'t be done from the command line; check them by hand:',
            '* the "allowed_hosts" option of the MCP server must include the public host name of the application (by default, only localhost is allowed)',
            '* the OAuth metadata endpoints must be reachable from the internet with HTTPS',
            '* the firewall of the MCP endpoint must reload the user on each request (stateless OAuth firewalls do it)',
        ]);

        $hasErrors = [] !== array_filter($checks, static fn (array $check): bool => self::STATUS_ERROR === $check[0]);
        if ($hasErrors) {
            $io->error('The MCP server is not ready. Fix the errors above.');

            return Command::FAILURE;
        }

        $io->success('The MCP server configuration looks right.');

        return Command::SUCCESS;
    }

    /**
     * @return array{string, string, string}
     */
    private function checkMcpBundle(): array
    {
        if (!class_exists(McpTool::class) || !class_exists(McpBundle::class)) {
            return [self::STATUS_ERROR, 'symfony/mcp-bundle', 'Not installed. Run: composer require symfony/mcp-bundle nyholm/psr7'];
        }

        if (!\in_array(McpBundle::class, $this->bundles, true)) {
            return [self::STATUS_ERROR, 'symfony/mcp-bundle', 'Installed but not enabled in config/bundles.php.'];
        }

        return [self::STATUS_OK, 'symfony/mcp-bundle', 'Installed and enabled.'];
    }

    /**
     * @return list<array{string, string, string}>
     */
    private function checkExposure(): array
    {
        try {
            $mcpDashboard = $this->collectionResolver->findMcpDashboard();
            if (null === $mcpDashboard) {
                return [[self::STATUS_ERROR, 'Dashboard', 'No dashboard calls "exposeSelectedCrudsToMcp()" or "exposeAllCrudsToMcp()" in its configureDashboard() method.']];
            }

            $collections = $this->collectionResolver->getCollections();
        } catch (\LogicException $e) {
            return [[self::STATUS_ERROR, 'Exposure', $e->getMessage()]];
        }

        $checks = [[self::STATUS_OK, 'Dashboard', sprintf('%s (%s mode)', $mcpDashboard[0]::class, strtolower($mcpDashboard[1]->name))]];
        $checks[] = [] === $collections
            ? [self::STATUS_ERROR, 'Collections', 'No CRUD controller is exposed. Use #[ExposeToMcp] or Crud::exposeToMcp() in some of them.']
            : [self::STATUS_OK, 'Collections', implode(', ', array_keys($collections))];

        return $checks;
    }

    private function findEndpointPath(): ?string
    {
        // this parameter lists the MCP elements (e.g. tools) that no MCP server exposes
        $unassignedElements = $this->parameters->has('mcp.servers.unassigned') ? $this->parameters->get('mcp.servers.unassigned') : [];
        if (\is_array($unassignedElements) && \in_array(ReadTools::class, $unassignedElements['tools'] ?? [], true)) {
            return null;
        }

        foreach ($this->router->getRouteCollection() as $routeName => $route) {
            if (str_starts_with($routeName, '_mcp_endpoint_')) {
                return $route->getPath();
            }
        }

        return null;
    }

    /**
     * @return list<array{string, string, string}>
     */
    private function checkSecurity(string $endpointPath): array
    {
        if (null === $this->firewallMap) {
            return [[self::STATUS_ERROR, 'Security', 'Symfony SecurityBundle is not enabled. MCP calls are always refused without an authenticated user.']];
        }

        $request = Request::create($endpointPath, 'POST');
        $firewallConfig = $this->firewallMap->getFirewallConfig($request);
        $checks = [];

        if (null === $firewallConfig || !$firewallConfig->isSecurityEnabled()) {
            $checks[] = [self::STATUS_ERROR, 'Firewall', sprintf('No firewall protects "%s".', $endpointPath)];
        } else {
            $checks[] = $firewallConfig->isStateless()
                ? [self::STATUS_OK, 'Firewall', sprintf('"%s" (stateless)', $firewallConfig->getName())]
                : [self::STATUS_WARNING, 'Firewall', sprintf('"%s" is not stateless. Use a dedicated stateless firewall for the MCP endpoint, defined before the firewall of the backend.', $firewallConfig->getName())];
        }

        [$roles] = $this->accessMap?->getPatterns($request) ?? [null];
        $checks[] = null === $roles || [] === $roles
            ? [self::STATUS_ERROR, 'Access control', sprintf('No "access_control" rule requires authentication for "%s". Without it, MCP clients don\'t get the 401 response that starts the OAuth flow.', $endpointPath)]
            : [self::STATUS_OK, 'Access control', implode(', ', array_map(static fn (mixed $role): string => \is_string($role) ? $role : get_debug_type($role), $roles))];

        return $checks;
    }

    /**
     * @return array{string, string, string}
     */
    private function checkRateLimiter(): array
    {
        if (0 === $this->limits['calls_per_minute']) {
            return [self::STATUS_WARNING, 'Rate limit', 'Disabled (easy_admin.mcp.limits.calls_per_minute is 0).'];
        }

        if (!class_exists(RateLimiterFactory::class) || null === $this->rateLimiterFactory) {
            return [self::STATUS_WARNING, 'Rate limit', 'Disabled because symfony/rate-limiter is not installed. Run: composer require symfony/rate-limiter'];
        }

        return [self::STATUS_OK, 'Rate limit', sprintf('%d calls per minute for each user', $this->limits['calls_per_minute'])];
    }
}
