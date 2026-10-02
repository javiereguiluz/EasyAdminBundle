<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Customization\Dashboard;

use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\CustomizationApp\Controller\Dashboard\IconFamilyTestDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\CustomizationApp\Controller\DemoEntityCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\CustomizationApp\Kernel;

/**
 * Tests for Assets::useIconFamily(). The icons are read from the local
 * assets/icons/ directory of the test app (downloading them is disabled).
 */
class IconFamilyTest extends AbstractCrudTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function getControllerFqcn(): string
    {
        return DemoEntityCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return IconFamilyTestDashboardController::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->followRedirects();
    }

    public function testIconNamesUseTheIconFamily(): void
    {
        $crawler = $this->client->request('GET', $this->generateIndexUrl());

        static::assertResponseIsSuccessful();
        static::assertSame('tabler', $crawler->filter('body')->attr('data-ea-icon-prefix'));

        $menuIcons = $crawler->filter('.ea-sidebar-item-icon span.icon svg');
        static::assertCount(2, $menuIcons);
        foreach ($menuIcons as $menuIcon) {
            // 'user' and 'tabler:user' render the same icon from the tabler/user.svg file
            static::assertStringContainsString('M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0', $menuIcon->ownerDocument->saveHTML($menuIcon));
            static::assertSame('true', $menuIcon->getAttribute('aria-hidden'));
        }
    }

    public function testInternalIconsDoNotUseTheIconFamily(): void
    {
        $crawler = $this->client->request('GET', $this->generateIndexUrl());

        static::assertStringContainsString('Font Awesome Free', $crawler->filter('span.icon.content-search-icon')->html());
    }
}
