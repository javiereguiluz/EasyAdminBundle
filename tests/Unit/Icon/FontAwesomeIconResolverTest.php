<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Icon;

use EasyCorp\Bundle\EasyAdminBundle\Icon\FontAwesomeIconResolver;
use EasyCorp\Bundle\EasyAdminBundle\Icon\FontAwesomeStyle;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Twig\Component\IconTest;
use PHPUnit\Framework\TestCase;

class FontAwesomeIconResolverTest extends TestCase
{
    /**
     * @dataProvider provideResolvedIcons
     */
    public function testResolve(string $classes, FontAwesomeStyle $expectedStyle, string $expectedName): void
    {
        $icon = (new FontAwesomeIconResolver())->resolve($classes);

        $this->assertNotNull($icon);
        $this->assertSame($expectedStyle, $icon->style);
        $this->assertSame($expectedName, $icon->name);
        $this->assertStringEndsWith(sprintf('assets/icons/fontawesome/%s/%s.svg', $expectedStyle->value, $expectedName), $icon->path);
        $this->assertStringStartsWith('<svg fill="currentColor" ', $icon->svgContents);
        $this->assertStringContainsString('Font Awesome Free', $icon->svgContents);
    }

    public static function provideResolvedIcons(): iterable
    {
        yield 'v6 name with v6 style' => ['fa-solid fa-user', FontAwesomeStyle::Solid, 'user'];
        yield 'v6 name with v5 style' => ['fas fa-user', FontAwesomeStyle::Solid, 'user'];
        yield 'v6 name with v4 style' => ['fa fa-user', FontAwesomeStyle::Solid, 'user'];
        yield 'name without style' => ['fa-user', FontAwesomeStyle::Solid, 'user'];
        yield 'family class' => ['fa-classic fa-regular fa-user', FontAwesomeStyle::Regular, 'user'];
        yield 'regular style' => ['far fa-clock', FontAwesomeStyle::Regular, 'clock'];
        yield 'brands style' => ['fab fa-github', FontAwesomeStyle::Brands, 'github'];
        yield 'any token order' => ['fa-fw fa-github fa-brands', FontAwesomeStyle::Brands, 'github'];
        yield 'extra whitespace' => ["  fa-solid \t fa-user  ", FontAwesomeStyle::Solid, 'user'];
        yield 'v5 alias' => ['fa fa-cog', FontAwesomeStyle::Solid, 'gear'];
        yield 'v5 alias without style' => ['fa-info-circle', FontAwesomeStyle::Solid, 'circle-info'];
        yield 'v5 alias in regular style' => ['far fa-file-alt', FontAwesomeStyle::Regular, 'file-lines'];
        yield 'v5 alias in solid style' => ['fas fa-home', FontAwesomeStyle::Solid, 'house'];
        yield 'v4 name that switches to regular' => ['fa fa-file-text-o', FontAwesomeStyle::Regular, 'file-lines'];
        yield 'v4 name without style' => ['fa-file-text-o', FontAwesomeStyle::Regular, 'file-lines'];
        yield 'v4 name with explicit style' => ['fas fa-file-text-o', FontAwesomeStyle::Solid, 'file-lines'];
        yield 'v4 brand name' => ['fa fa-github', FontAwesomeStyle::Brands, 'github'];
        yield 'v4 name kept in regular style' => ['fa fa-credit-card', FontAwesomeStyle::Regular, 'credit-card'];
        yield 'v6 name not affected by v4 names' => ['fa-solid fa-credit-card', FontAwesomeStyle::Solid, 'credit-card'];
        yield 'modifier classes' => ['fa-solid fa-spinner fa-spin fa-2x fa-rotate-90 fa-stack-1x', FontAwesomeStyle::Solid, 'spinner'];
        yield 'other classes' => ['fa-solid fa-user text-danger me-1', FontAwesomeStyle::Solid, 'user'];
    }

    /**
     * @dataProvider provideIconTestFontAwesomeNames
     */
    public function testResolveAllNamesSupportedByIconComponent(string $classes): void
    {
        $this->assertNotNull((new FontAwesomeIconResolver())->resolve($classes));
    }

    public static function provideIconTestFontAwesomeNames(): iterable
    {
        return IconTest::provideGetFontAwesomeIconData();
    }

    public function testResolvedClassesKeepOriginalClassesAndAddCanonicalName(): void
    {
        $icon = (new FontAwesomeIconResolver())->resolve('fa fa-cog fa-fw text-muted');

        $this->assertSame(['fa', 'fa-cog', 'fa-fw', 'text-muted', 'fa-gear'], $icon->classes);
    }

    public function testResolvedClassesDoNotDuplicateCanonicalName(): void
    {
        $icon = (new FontAwesomeIconResolver())->resolve('fa-solid fa-user');

        $this->assertSame(['fa-solid', 'fa-user'], $icon->classes);
    }

    /**
     * @dataProvider provideUnresolvedIcons
     */
    public function testUnresolvedIcons(string $classes): void
    {
        $this->assertNull((new FontAwesomeIconResolver())->resolve($classes));
    }

    public static function provideUnresolvedIcons(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'only style' => ['fa-solid'];
        yield 'only modifiers' => ['fa fa-fw fa-2x'];
        yield 'unknown name' => ['fa-solid fa-this-icon-does-not-exist'];
        yield 'two names' => ['fa-solid fa-user fa-house'];
        yield 'conflicting styles' => ['fa-solid fa-regular fa-user'];
        yield 'no cross-style fallback' => ['fa-regular fa-house'];
        yield 'brand in solid style' => ['fa-solid fa-github'];
        yield 'not a FontAwesome name' => ['user'];
        yield 'icon name with prefix' => ['tabler:user'];
        yield 'pro light' => ['fal fa-user'];
        yield 'pro light v6' => ['fa-light fa-user'];
        yield 'pro thin' => ['fa-thin fa-user'];
        yield 'pro thin v5' => ['fat fa-user'];
        yield 'pro duotone' => ['fad fa-user'];
        yield 'pro duotone v6' => ['fa-duotone fa-user'];
        yield 'pro duotone with style' => ['fa-duotone-regular fa-user'];
        yield 'pro sharp' => ['fa-sharp fa-solid fa-user'];
        yield 'pro sharp v5' => ['fass fa-user'];
        yield 'pro sharp solid' => ['fa-sharp-solid fa-user'];
        yield 'kit' => ['fak fa-my-icon'];
        yield 'kit v6' => ['fa-kit fa-my-icon'];
        yield 'path traversal' => ['fa-solid fa-../../../../composer'];
        yield 'path traversal without style' => ['fa-../internal/user'];
        yield 'slash' => ['fa-solid fa-solid/user'];
        yield 'null byte' => ["fa-solid fa-us\0er"];
    }
}
