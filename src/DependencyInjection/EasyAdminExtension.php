<?php

namespace EasyCorp\Bundle\EasyAdminBundle\DependencyInjection;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Action\ActionsExtensionInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpDoctorCheckInterface;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Read\McpCollectionExtensionInterface;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Value\McpValueNormalizerInterface;
use Mcp\Capability\Attribute\McpTool;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class EasyAdminExtension extends Extension implements PrependExtensionInterface
{
    public const TAG_CRUD_CONTROLLER = 'ea.crud_controller';
    public const TAG_DASHBOARD_CONTROLLER = 'ea.dashboard_controller';
    public const TAG_ADMIN_ROUTE_CONTROLLER = 'ea.admin_route_controller';
    public const TAG_FIELD_CONFIGURATOR = 'ea.field_configurator';
    public const TAG_FILTER_CONFIGURATOR = 'ea.filter_configurator';
    public const TAG_ACTIONS_EXTENSION = 'ea.actions_extension';
    public const TAG_MCP_VALUE_NORMALIZER = 'ea.mcp_value_normalizer';
    public const TAG_MCP_COLLECTION_EXTENSION = 'ea.mcp_collection_extension';
    public const TAG_MCP_DOCTOR_CHECK = 'ea.mcp_doctor_check';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(AdminRoute::class,
            // @phpstan-ignore-next-line argument.type The reflection subtypes specify where the attribute can be used
            static function (Definition $definition, AdminRoute $attribute, \ReflectionClass|\ReflectionMethod $reflection): void {
                $definition->addTag(self::TAG_ADMIN_ROUTE_CONTROLLER);
            });

        $container->registerForAutoconfiguration(DashboardControllerInterface::class)
            ->addTag(self::TAG_DASHBOARD_CONTROLLER);

        $container->registerForAutoconfiguration(CrudControllerInterface::class)
            ->addTag(self::TAG_CRUD_CONTROLLER);

        $container->registerForAutoconfiguration(FieldConfiguratorInterface::class)
            ->addTag(self::TAG_FIELD_CONFIGURATOR);

        $container->registerForAutoconfiguration(FilterConfiguratorInterface::class)
            ->addTag(self::TAG_FILTER_CONFIGURATOR);

        $container->registerForAutoconfiguration(ActionsExtensionInterface::class)
            ->addTag(self::TAG_ACTIONS_EXTENSION);

        $container->registerForAutoconfiguration(McpValueNormalizerInterface::class)
            ->addTag(self::TAG_MCP_VALUE_NORMALIZER);
        $container->registerForAutoconfiguration(McpCollectionExtensionInterface::class)
            ->addTag(self::TAG_MCP_COLLECTION_EXTENSION);
        $container->registerForAutoconfiguration(McpDoctorCheckInterface::class)
            ->addTag(self::TAG_MCP_DOCTOR_CHECK);

        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('easyadmin.mcp.limits', $config['mcp']['limits']);
        foreach ($config['mcp']['limits'] as $name => $value) {
            $container->setParameter('easyadmin.mcp.limits.'.$name, $value);
        }

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        if ($this->isMcpServerEnabled($container)) {
            $loader->load('services_mcp.php');
            $this->registerMcpRateLimiter($container, $config['mcp']['limits']['calls_per_minute']);
        }
    }

    public function prepend(ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('twig_component', [
            'defaults' => [
                'EasyCorp\\Bundle\\EasyAdminBundle\\Twig\\Component\\' => [
                    'template_directory' => '@EasyAdmin/components/',
                    'name_prefix' => 'ea',
                ],
            ],
        ]);

        /** @var string $projectDir */
        $projectDir = $builder->getParameter('kernel.project_dir');

        $bundleTemplatesOverrideDir = $projectDir.'/templates/bundles/EasyAdminBundle/';
        $builder->prependExtensionConfig('twig', [
            'paths' => is_dir($bundleTemplatesOverrideDir)
                ? [
                    'templates/bundles/EasyAdminBundle/' => 'ea',
                    \dirname(__DIR__).'/../templates/' => 'ea',
                ]
                : [
                    \dirname(__DIR__).'/../templates/' => 'ea',
                ],
        ]);
    }

    private function isMcpServerEnabled(ContainerBuilder $container): bool
    {
        /** @var array<string, class-string> $bundles */
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];

        return class_exists(McpTool::class) && class_exists(McpBundle::class) && \in_array(McpBundle::class, $bundles, true);
    }

    private function registerMcpRateLimiter(ContainerBuilder $container, int $callsPerMinute): void
    {
        if (0 === $callsPerMinute || !class_exists(RateLimiterFactory::class)) {
            return;
        }

        $container->register('easyadmin.mcp.rate_limiter', RateLimiterFactory::class)
            ->setArguments([
                ['id' => 'easyadmin_mcp', 'policy' => 'sliding_window', 'limit' => $callsPerMinute, 'interval' => '1 minute'],
                (new Definition(CacheStorage::class))->setArguments([new Reference('cache.app')]),
            ]);
    }
}
