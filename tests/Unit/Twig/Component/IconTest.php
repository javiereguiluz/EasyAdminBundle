<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Twig\Component;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\IconSet;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\DashboardContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\AssetsDto;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Icon;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\Icons\IconRendererInterface;

class IconTest extends TestCase
{
    /**
     * @dataProvider provideGetInternalIconData
     */
    public function testGetInternalIcon(string $iconName, string $appIconSet): void
    {
        $iconComponent = new Icon($this->getAdminContextProvider($appIconSet));
        $iconComponent->name = $iconName;
        $iconDto = $iconComponent->getIcon();

        $this->assertSame('internal:user', $iconDto->getName());
        $this->assertStringEndsWith('assets/icons/internal/user.svg', $iconDto->getPath());
        $this->assertStringContainsString('(Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2024 Fonticons, Inc.', $iconDto->getSvgContents());
    }

    public static function provideGetInternalIconData(): iterable
    {
        // internal icons used in EasyAdmin UI; we test it with different icon sets to
        // test that the icon set is ignored for internal icons and the result is always the same
        yield ['internal:user', IconSet::Internal];
        yield ['internal:user', IconSet::Custom];
        yield ['internal:user', IconSet::FontAwesome];
    }

    /**
     * @dataProvider provideGetFontAwesomeIconData
     */
    public function testGetFontAwesomeIcon(string $iconName): void
    {
        $iconComponent = new Icon($this->getAdminContextProvider(IconSet::FontAwesome));
        $iconComponent->name = $iconName;
        $iconDto = $iconComponent->getIcon();

        $this->assertSame($iconName, $iconDto->getName());
        $this->assertSame(IconSet::FontAwesome, $iconDto->getIconSet());
        $this->assertStringContainsString('assets/icons/fontawesome/', $iconDto->getPath());
        $this->assertStringStartsWith('<svg class="', $iconDto->getSvgContents());
        $this->assertStringContainsString('aria-hidden="true"', $iconDto->getSvgContents());
        $this->assertMatchesRegularExpression('/ data-prefix="fa[srb]" data-icon="[a-z0-9-]+" /', $iconDto->getSvgContents());

        preg_match('/^<svg class="([^"]*)"/', $iconDto->getSvgContents(), $matches);
        $svgClasses = explode(' ', $matches[1]);
        foreach (preg_split('/\s+/', $iconName) as $originalClass) {
            $this->assertContains($originalClass, $svgClasses);
        }
    }

    public function testGetFontAwesomeIconAttributes(): void
    {
        $iconComponent = new Icon($this->getAdminContextProvider(IconSet::FontAwesome));
        $iconComponent->name = 'fa fa-file-text-o text-danger';
        $svgContents = $iconComponent->getIcon()->getSvgContents();

        $this->assertStringStartsWith('<svg class="fa fa-file-text-o text-danger fa-file-lines" data-prefix="far" data-icon="file-lines" aria-hidden="true" fill="currentColor" ', $svgContents);
    }

    public function testGetFontAwesomeIconEscapesClasses(): void
    {
        $iconComponent = new Icon($this->getAdminContextProvider(IconSet::FontAwesome));
        $iconComponent->name = 'fa-solid fa-user "><script>alert(1)</script>';
        $svgContents = $iconComponent->getIcon()->getSvgContents();

        $this->assertStringNotContainsString('<script>', $svgContents);
        $this->assertStringStartsWith('<svg class="fa-solid fa-user &quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;" ', $svgContents);
    }

    /**
     * @dataProvider provideUnresolvedFontAwesomeIconData
     */
    public function testGetUnresolvedFontAwesomeIcon(string $iconName): void
    {
        $iconComponent = new Icon($this->getAdminContextProvider(IconSet::FontAwesome));
        $iconComponent->name = $iconName;
        $iconDto = $iconComponent->getIcon();

        $this->assertSame($iconName, $iconDto->getName());
        $this->assertTrue($iconDto->isFontAwesomeIconSet());
        $this->assertNull($iconDto->getPath());
        $this->assertNull($iconDto->getSvgContents());
    }

    public static function provideUnresolvedFontAwesomeIconData(): iterable
    {
        yield 'FontAwesome Pro style' => ['fal fa-user'];
        yield 'FontAwesome Pro family' => ['fa-sharp fa-solid fa-user'];
        yield 'FontAwesome kit' => ['fa-kit fa-my-custom-icon'];
        yield 'unknown icon' => ['fa-solid fa-this-icon-does-not-exist'];
    }

    public static function provideGetFontAwesomeIconData(): iterable
    {
        yield ['fa fa-list'];
        yield ['fa-solid fa-list'];
        yield ['fa-list fa-solid'];
        yield ['fa-list fa-solid fa-fw'];
        yield ['fa-list fa-fw fa-solid'];
        yield ['fa-brands fa-twitter'];
        yield ['fa-twitter fa-brands'];
        yield ['fa-twitter fa-brands fa-fw'];
        yield ['fa-twitter fa-fw fa-brands'];
        yield ['fa-clock fa-regular'];
        yield ['fa-regular fa-clock'];
        yield ['fa-regular fa-clock fa-fw'];
        yield ['fa-regular fa-fw fa-clock'];
        yield ['fa-address-card'];
        yield ['fas fa-address-card'];
        yield ['fa-address-card fas'];
        yield ['fa-address-card fas fa-fw'];
        yield ['fa-address-card fa-fw fas'];
        yield ['fas fa-fw fa-address-card'];
        yield ['fas fa-address-card fa-fw'];
        // fontAwesome icons using legacy icon names
        yield ['fa-file-text-o'];
        yield ['fa fa-file-text-o'];
        yield ['far fa-file-text-o'];
        yield ['fas fa-file-text-o'];
        yield ['fa-file-text-o fa'];
        yield ['fa-file-text-o fas'];
        yield ['fa-file-text-o far'];
        yield ['fa-fw fa-file-text-o fa'];
        yield ['fa-fw fa-file-text-o fas'];
        yield ['fa-fw fa-file-text-o far'];
    }

    /**
     * @dataProvider provideGetCustomIconData
     */
    public function testGetCustomIcon(string $iconName): void
    {
        $iconComponent = new Icon($this->getAdminContextProvider(IconSet::Custom));
        $iconComponent->name = $iconName;
        $iconDto = $iconComponent->getIcon();

        $this->assertSame($iconName, $iconDto->getName());
        $this->assertNull($iconDto->getPath());
        $this->assertNull($iconDto->getSvgContents());
    }

    public static function provideGetCustomIconData(): iterable
    {
        yield ['custom:my-icon'];
        yield ['another-custom-prefix:some-other-icon'];
    }

    /**
     * @dataProvider providePrefixedCustomIconData
     */
    public function testDefaultPrefixIsNotAddedToPrefixedIconNames(string $iconName, string $expectedIconName): void
    {
        $iconComponent = new Icon($this->getAdminContextProvider(IconSet::Custom, 'tabler'));
        $iconComponent->name = $iconName;

        $this->assertSame($expectedIconName, $iconComponent->getIcon()->getName());
    }

    public static function providePrefixedCustomIconData(): iterable
    {
        yield ['user', 'tabler:user'];
        yield ['tabler:user', 'tabler:user'];
        yield ['lucide:map-pin', 'lucide:map-pin'];
    }

    public function testIconFamilyRequiresSymfonyUxIcons(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The backend uses the "tabler" icon family (configured with the useIconFamily() method of the Assets class), but Symfony UX Icons is not installed or enabled. Run "composer require symfony/ux-icons symfony/http-client" to install it.');

        $iconComponent = new Icon($this->getAdminContextProvider(IconSet::Custom, 'tabler', 'tabler'));
        $iconComponent->name = 'user';
        $iconComponent->getIcon();
    }

    public function testIconFamilyWithSymfonyUxIcons(): void
    {
        $iconComponent = new Icon($this->getAdminContextProvider(IconSet::Custom, 'tabler', 'tabler'), null, $this->getUxIconRenderer());
        $iconComponent->name = 'user';

        $this->assertSame('tabler:user', $iconComponent->getIcon()->getName());
    }

    public function testCustomIconSetDoesNotRequireSymfonyUxIcons(): void
    {
        $iconComponent = new Icon($this->getAdminContextProvider(IconSet::Custom, 'tabler'));
        $iconComponent->name = 'user';
        $iconDto = $iconComponent->getIcon();

        $this->assertSame('tabler:user', $iconDto->getName());
        $this->assertNull($iconDto->getSvgContents());
    }

    /**
     * @dataProvider provideIconSetCheckersData
     */
    public function testIconSetCheckers(string $appIconSet, bool $isBuiltIn, bool $isFontAwesome): void
    {
        $iconComponent = new Icon($this->getAdminContextProvider($appIconSet));

        $this->assertSame($isBuiltIn, $iconComponent->isBuiltInIconSet());
        $this->assertSame($isFontAwesome, $iconComponent->isFontAwesomeIconSet());
    }

    public static function provideIconSetCheckersData(): iterable
    {
        yield [IconSet::FontAwesome, true, true];
        yield [IconSet::Internal, true, false];
        yield [IconSet::Custom, false, false];
    }

    public function testNullIconName(): void
    {
        $iconComponent = new Icon($this->getAdminContextProvider(IconSet::FontAwesome));
        $iconDto = $iconComponent->getIcon();

        $this->assertSame('', $iconDto->getName());
        $this->assertNull($iconDto->getPath());
        $this->assertNull($iconDto->getSvgContents());
    }

    public function testUnknownInternalIcon(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/The icon "internal:this-does-not-exist" does not exist\. Check the icon name spelling and make sure that the "this-does-not-exist\.svg" file exists in the "assets\/icons\/internal\/" directory of EasyAdmin\./');

        $iconComponent = new Icon($this->getAdminContextProvider(IconSet::Internal));
        $iconComponent->name = 'internal:this-does-not-exist';
        $iconComponent->getIcon();
    }

    /**
     * @dataProvider providePathTraversalInternalIconNames
     */
    public function testInternalIconNameRejectsPathTraversalCharacters(string $iconName): void
    {
        $this->expectException(\RuntimeException::class);

        $iconComponent = new Icon($this->getAdminContextProvider(IconSet::Internal));
        $iconComponent->name = $iconName;
        $iconComponent->getIcon();
    }

    public static function providePathTraversalInternalIconNames(): iterable
    {
        yield 'parent-directory traversal' => ['internal:../../../../../tmp/secret'];
        yield 'leading slash' => ['internal:/etc/passwd'];
        yield 'plain dot-dot' => ['internal:..'];
        yield 'subdirectory access' => ['internal:subdir/icon'];
        yield 'backslash separator' => ['internal:..\\foo'];
        yield 'empty icon name' => ['internal:'];
        yield 'filetypes parent-directory traversal' => ['filetypes:../../../../../tmp/secret'];
        yield 'filetypes leading slash' => ['filetypes:/etc/passwd'];
        yield 'filetypes plain dot-dot' => ['filetypes:..'];
        yield 'filetypes subdirectory access' => ['filetypes:subdir/icon'];
        yield 'filetypes backslash separator' => ['filetypes:..\\foo'];
        yield 'filetypes empty icon name' => ['filetypes:'];
    }

    private function getAdminContextProvider(string $appIconSet, string $defaultIconPrefix = '', ?string $iconFamily = null): AdminContextProvider
    {
        $assetsDto = new AssetsDto();
        $assetsDto->setIconSet($appIconSet);
        $assetsDto->setDefaultIconPrefix($defaultIconPrefix);
        $assetsDto->setIconFamily($iconFamily);

        $adminContext = AdminContext::forTesting(
            dashboardContext: DashboardContext::forTesting(assets: $assetsDto),
        );

        $request = new Request(attributes: [EA::CONTEXT_REQUEST_ATTRIBUTE => $adminContext]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new AdminContextProvider($requestStack);
    }

    private function getUxIconRenderer(): IconRendererInterface
    {
        return new class implements IconRendererInterface {
            public function renderIcon(string $name, array $attributes = []): string
            {
                return '<svg></svg>';
            }
        };
    }
}
