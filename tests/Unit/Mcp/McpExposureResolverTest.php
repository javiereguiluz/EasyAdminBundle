<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\CacheKey;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpExposureMode;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpExposureResolver;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminRouteGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\CustomerCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Fixtures\ProductCrudController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class McpExposureResolverTest extends TestCase
{
    /**
     * @dataProvider provideExposureMatrix
     *
     * @param 'none'|'expose'|'expose_read_only'|'exclude' $attribute
     * @param 'none'|'expose'|'expose_read_only'|'exclude' $method
     * @param 'hidden'|'exposed'|'read_only'               $expected
     */
    public function testExposureMatrix(McpExposureMode $mode, string $attribute, string $method, string $expected): void
    {
        $cache = new ArrayAdapter();
        $cacheItem = $cache->getItem(CacheKey::CRUD_FQCN_TO_MCP_EXPOSURE);
        $cacheItem->set(null === ($attributeExposure = $this->createAttributeExposure($attribute)) ? [] : [ProductCrudController::class => $attributeExposure]);
        $cache->save($cacheItem);

        $crud = Crud::new();
        match ($method) {
            'expose' => $crud->exposeToMcp(),
            'expose_read_only' => $crud->exposeToMcp(readOnly: true),
            'exclude' => $crud->excludeFromMcp(),
            'none' => null,
        };

        $exposure = $this->createResolver($cache)->resolve($mode, ProductCrudController::class, $crud->getAsDto());

        $actual = match (true) {
            null === $exposure => 'hidden',
            $exposure->readOnly => 'read_only',
            default => 'exposed',
        };
        $this->assertSame($expected, $actual);
    }

    public static function provideExposureMatrix(): iterable
    {
        $explicitValues = ['expose' => 'exposed', 'expose_read_only' => 'read_only', 'exclude' => 'hidden'];
        $defaults = [McpExposureMode::Selected->name => 'hidden', McpExposureMode::All->name => 'exposed'];

        foreach ([McpExposureMode::Selected, McpExposureMode::All] as $mode) {
            foreach (['none', 'expose', 'expose_read_only', 'exclude'] as $attribute) {
                foreach (['none', 'expose', 'expose_read_only', 'exclude'] as $method) {
                    // the method wins over the attribute, and both win over the dashboard default
                    $expected = $explicitValues[$method] ?? $explicitValues[$attribute] ?? $defaults[$mode->name];

                    yield sprintf('%s mode, %s attribute, %s method', $mode->name, $attribute, $method) => [$mode, $attribute, $method, $expected];
                }
            }
        }
    }

    public function testAliasIsKeptFromTheEffectiveExposure(): void
    {
        $cache = new ArrayAdapter();
        $cacheItem = $cache->getItem(CacheKey::CRUD_FQCN_TO_MCP_EXPOSURE);
        $cacheItem->set([ProductCrudController::class => ['exposed' => true, 'readOnly' => false, 'alias' => 'from_attribute']]);
        $cache->save($cacheItem);
        $resolver = $this->createResolver($cache);

        $this->assertSame('from_attribute', $resolver->resolve(McpExposureMode::Selected, ProductCrudController::class, Crud::new()->getAsDto())?->alias);
        $this->assertSame('from_method', $resolver->resolve(McpExposureMode::Selected, ProductCrudController::class, Crud::new()->exposeToMcp(alias: 'from_method')->getAsDto())?->alias);
        $this->assertNull($resolver->resolve(McpExposureMode::Selected, ProductCrudController::class, Crud::new()->exposeToMcp()->getAsDto())?->alias);
    }

    public function testMissingCacheIsGeneratedAgain(): void
    {
        // otherwise, #[ExcludeFromMcp] would stop working when the cache item is missing
        $resolver = $this->createResolver(new ArrayAdapter());

        $this->assertNull($resolver->resolve(McpExposureMode::All, CustomerCrudController::class, Crud::new()->getAsDto()));
        $this->assertNotNull($resolver->resolve(McpExposureMode::All, ProductCrudController::class, Crud::new()->getAsDto()));
    }

    private function createResolver(ArrayAdapter $cache): McpExposureResolver
    {
        return new McpExposureResolver($cache, new AdminRouteGenerator([], [new ProductCrudController(), new CustomerCrudController()], $cache));
    }

    /**
     * @return array{exposed: bool, readOnly: bool, alias: string|null}|null
     */
    private function createAttributeExposure(string $type): ?array
    {
        return match ($type) {
            'expose' => ['exposed' => true, 'readOnly' => false, 'alias' => null],
            'expose_read_only' => ['exposed' => true, 'readOnly' => true, 'alias' => null],
            'exclude' => ['exposed' => false, 'readOnly' => false, 'alias' => null],
            default => null,
        };
    }
}
