<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpCrudExposure;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpExposureMode;
use PHPUnit\Framework\TestCase;

class McpExposureConfigTest extends TestCase
{
    public function testDashboardWithoutExposureMethodHasMcpOff(): void
    {
        $this->assertNull(Dashboard::new()->getAsDto()->getMcpExposureMode());
    }

    public function testDashboardExposureModes(): void
    {
        $this->assertSame(McpExposureMode::Selected, Dashboard::new()->exposeSelectedCrudsToMcp()->getAsDto()->getMcpExposureMode());
        $this->assertSame(McpExposureMode::All, Dashboard::new()->exposeAllCrudsToMcp()->getAsDto()->getMcpExposureMode());
        $this->assertSame(McpExposureMode::All, Dashboard::new()->exposeAllCrudsToMcp()->exposeAllCrudsToMcp()->getAsDto()->getMcpExposureMode());
    }

    public function testDashboardCannotCallBothExposureMethods(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The dashboard cannot call both "exposeSelectedCrudsToMcp()" and "exposeAllCrudsToMcp()". Remove one of them (the last call was "exposeAllCrudsToMcp()").');

        Dashboard::new()->exposeSelectedCrudsToMcp()->exposeAllCrudsToMcp();
    }

    public function testCrudExposure(): void
    {
        $this->assertNull(Crud::new()->getAsDto()->getMcpExposure());

        $exposure = Crud::new()->exposeToMcp(readOnly: true, alias: 'products')->getAsDto()->getMcpExposure();
        $this->assertNotNull($exposure);
        $this->assertTrue($exposure->exposed);
        $this->assertTrue($exposure->readOnly);
        $this->assertSame('products', $exposure->alias);

        $this->assertFalse(Crud::new()->excludeFromMcp()->getAsDto()->getMcpExposure()?->exposed);
    }

    public function testCrudLastExposeCallWins(): void
    {
        $exposure = Crud::new()->exposeToMcp(readOnly: true)->exposeToMcp(alias: 'products')->getAsDto()->getMcpExposure();

        $this->assertNotNull($exposure);
        $this->assertFalse($exposure->readOnly);
        $this->assertSame('products', $exposure->alias);
    }

    public function testCrudOverridesTheExposureSetInTheDashboard(): void
    {
        // the same Crud object is passed to the dashboard and the CRUD controller configureCrud() methods
        $this->assertFalse(Crud::new()->exposeToMcp()->excludeFromMcp()->getAsDto()->getMcpExposure()?->exposed);
        $this->assertTrue(Crud::new()->excludeFromMcp()->exposeToMcp()->getAsDto()->getMcpExposure()?->exposed);
    }

    /**
     * @testWith ["Products"]
     *           ["1products"]
     *           ["product-list"]
     *           [""]
     *           ["a_very_long_alias_that_goes_beyond_the_sixty_four_characters_limit"]
     */
    public function testInvalidAlias(string $alias): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The MCP alias "%s" is not valid.', $alias));

        McpCrudExposure::exposed(alias: $alias);
    }
}
