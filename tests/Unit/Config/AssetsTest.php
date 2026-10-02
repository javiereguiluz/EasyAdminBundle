<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Config;

use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\IconFamily;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\IconSet;
use PHPUnit\Framework\TestCase;

class AssetsTest extends TestCase
{
    public function testDefaultIconSet(): void
    {
        $assetsConfig = Assets::new();

        $this->assertSame(IconSet::FontAwesome, $assetsConfig->getAsDto()->getIconSet());
        $this->assertSame('', $assetsConfig->getAsDto()->getDefaultIconPrefix());
    }

    public function testCustomIconSet(): void
    {
        $assetsConfig = Assets::new();
        $assetsConfig->useCustomIconSet();

        $this->assertSame(IconSet::Custom, $assetsConfig->getAsDto()->getIconSet());
        $this->assertSame('', $assetsConfig->getAsDto()->getDefaultIconPrefix());
    }

    public function testCustomIconSetWithDefaultPrefix(): void
    {
        $assetsConfig = Assets::new();
        $assetsConfig->useCustomIconSet('some-prefix');

        $this->assertSame(IconSet::Custom, $assetsConfig->getAsDto()->getIconSet());
        $this->assertSame('some-prefix', $assetsConfig->getAsDto()->getDefaultIconPrefix());
    }

    /**
     * @dataProvider provideIconFamilies
     */
    public function testUseIconFamily(IconFamily|string $family, string $expectedPrefix): void
    {
        $assetsDto = Assets::new()->useIconFamily($family)->getAsDto();

        $this->assertSame(IconSet::Custom, $assetsDto->getIconSet());
        $this->assertSame($expectedPrefix, $assetsDto->getDefaultIconPrefix());
        $this->assertSame($expectedPrefix, $assetsDto->getIconFamily());
    }

    public static function provideIconFamilies(): iterable
    {
        yield [IconFamily::Tabler, 'tabler'];
        yield [IconFamily::Phosphor, 'ph'];
        yield [IconFamily::MaterialSymbols, 'material-symbols'];
        yield ['mdi', 'mdi'];
        yield [' simple-icons ', 'simple-icons'];
    }

    /**
     * @dataProvider provideFontAwesomeIconFamily
     */
    public function testUseFontAwesomeIconFamilyRestoresTheDefaultIcons(IconFamily|string $family): void
    {
        $assetsDto = Assets::new()->useIconFamily(IconFamily::Lucide)->useIconFamily($family)->getAsDto();

        $this->assertSame(IconSet::FontAwesome, $assetsDto->getIconSet());
        $this->assertSame('', $assetsDto->getDefaultIconPrefix());
        $this->assertNull($assetsDto->getIconFamily());
    }

    public static function provideFontAwesomeIconFamily(): iterable
    {
        yield [IconFamily::FontAwesome];
        yield ['fontawesome'];
    }

    public function testUseCustomIconSetResetsIconFamily(): void
    {
        $assetsDto = Assets::new()->useIconFamily(IconFamily::Lucide)->useCustomIconSet('tabler')->getAsDto();

        $this->assertSame('tabler', $assetsDto->getDefaultIconPrefix());
        $this->assertNull($assetsDto->getIconFamily());
    }

    /**
     * @dataProvider provideInvalidIconFamilies
     */
    public function testUseIconFamilyWithInvalidPrefix(string $family): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Assets::new()->useIconFamily($family);
    }

    public static function provideInvalidIconFamilies(): iterable
    {
        yield [''];
        yield ['tabler:user'];
        yield ['font awesome'];
        yield ['Tabler'];
        yield ['../icons'];
    }

    public function testFontAwesomeCssIsEnabledByDefault(): void
    {
        $this->assertTrue(Assets::new()->getAsDto()->isFontAwesomeCssEnabled());
    }

    public function testDisableFontAwesomeCss(): void
    {
        $this->assertFalse(Assets::new()->disableFontAwesomeCss()->getAsDto()->isFontAwesomeCssEnabled());
        $this->assertTrue(Assets::new()->disableFontAwesomeCss()->disableFontAwesomeCss(false)->getAsDto()->isFontAwesomeCssEnabled());
    }

    public function testAddRepriseEntryThrowsWhenRepriseNotInstalled(): void
    {
        // Symfony Reprise is not a dependency of this package, so the guard must throw.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('composer require symfony/reprise');

        Assets::new()->addRepriseEntry('admin');
    }
}
