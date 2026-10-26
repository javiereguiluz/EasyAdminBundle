<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Config;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\Font;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\GrayScale;
use EasyCorp\Bundle\EasyAdminBundle\Config\Theme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ThemeTest extends TestCase
{
    #[DataProvider('provideValidColors')]
    public function testPrimaryColorAcceptsValidFormats(string $color): void
    {
        $themeDto = Theme::new()->primaryColor($color)->getAsDto();

        $this->assertSame($color, $themeDto->getPrimaryColor());
    }

    public static function provideValidColors(): iterable
    {
        yield ['#abc'];
        yield ['#15803d'];
        yield ['rgb(21, 128, 61)'];
        yield ['rgb(21 128 61)'];
        yield ['hsl(146, 72%, 29%)'];
        yield ['hsl(146 72% 29%)'];
        yield ['hsl(146deg 72% 29%)'];
        yield ['oklch(0.6 0.2 150)'];
        yield ['oklch(60% 0.2 150deg)'];
    }

    #[DataProvider('provideInvalidColors')]
    public function testPrimaryColorRejectsInvalidValues(string $color): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Theme::new()->primaryColor($color);
    }

    public static function provideInvalidColors(): iterable
    {
        yield 'CSS injection' => ['red;} body{display:none'];
        yield 'url' => ['url(x)'];
        yield 'var' => ['var(--x)'];
        yield 'hex with payload' => ['#fff;}'];
        yield '5-digit hex' => ['#12345'];
        yield 'hex with alpha' => ['#ffffff00'];
        yield 'named color' => ['red'];
        yield 'rgb out of range' => ['rgb(300, 0, 0)'];
        yield 'rgb with alpha' => ['rgb(0, 0, 0, 0.5)'];
        yield 'hsl with alpha' => ['hsl(0 0% 0% / 50%)'];
        yield 'oklch with commas' => ['oklch(0.6, 0.2, 150)'];
        yield 'expression' => ['expression(alert(1))'];
        yield 'empty' => [''];
    }

    public function testPrimaryColorComputesForeground(): void
    {
        $this->assertSame('#fff', Theme::new()->primaryColor('#15803d')->getAsDto()->getPrimaryForeground());
        $this->assertSame('#000', Theme::new()->primaryColor('#facc15')->getAsDto()->getPrimaryForeground());
    }

    public function testPrimaryColorDarkVariant(): void
    {
        $themeDto = Theme::new()->primaryColor('#1e3a8a', dark: '#facc15')->getAsDto();

        $this->assertSame('#1e3a8a', $themeDto->getPrimaryColor());
        $this->assertSame('#fff', $themeDto->getPrimaryForeground());
        $this->assertSame('#facc15', $themeDto->getDarkPrimaryColor());
        $this->assertSame('#000', $themeDto->getDarkPrimaryForeground());
    }

    public function testInvalidDarkPrimaryColorIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Theme::new()->primaryColor('#15803d', dark: 'red;}');
    }

    #[DataProvider('provideRadiusPresets')]
    public function testRadiusPresets(string $preset, string $resolvedValue): void
    {
        $this->assertSame($resolvedValue, Theme::new()->radius($preset)->getAsDto()->getRadius());
    }

    public static function provideRadiusPresets(): iterable
    {
        yield ['none', '0'];
        yield ['xs', '0.125rem'];
        yield ['sm', '0.1875rem'];
        yield ['md', '0.25rem'];
        yield ['lg', '0.375rem'];
        yield ['xl', '0.5rem'];
    }

    #[DataProvider('provideSpacingPresets')]
    public function testSpacingPresets(string $preset, string $resolvedValue): void
    {
        $this->assertSame($resolvedValue, Theme::new()->spacing($preset)->getAsDto()->getSpacing());
    }

    public static function provideSpacingPresets(): iterable
    {
        yield ['xs', '0.09375rem'];
        yield ['sm', '0.109375rem'];
        yield ['md', '0.125rem'];
        yield ['lg', '0.140625rem'];
        yield ['xl', '0.15625rem'];
    }

    #[DataProvider('provideValidLengths')]
    public function testCustomLengths(string $length): void
    {
        $this->assertSame($length, Theme::new()->radius($length)->getAsDto()->getRadius());
        $this->assertSame($length, Theme::new()->spacing($length)->getAsDto()->getSpacing());
    }

    public static function provideValidLengths(): iterable
    {
        yield ['0'];
        yield ['10px'];
        yield ['0.5rem'];
        yield ['.5rem'];
    }

    #[DataProvider('provideInvalidLengths')]
    public function testInvalidLengthsAreRejected(string $length): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Theme::new()->radius($length);
    }

    public static function provideInvalidLengths(): iterable
    {
        yield 'em unit' => ['2em'];
        yield 'negative' => ['-1px'];
        yield 'no unit' => ['99'];
        yield 'CSS injection' => ['1rem;color:red'];
        yield 'percentage' => ['50%'];
    }

    public function testSpacingHasNoNonePreset(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Theme::new()->spacing('none');
    }

    #[DataProvider('provideGrayScales')]
    public function testGrays(string $grayScale, string $rampPrefix): void
    {
        $cssVariables = Theme::new()->grays($grayScale)->getAsDto()->getCssVariables();

        $this->assertSame(sprintf('var(%s500)', $rampPrefix), $cssVariables['common']['--gray-500']);
        $this->assertCount(11, $cssVariables['common']);
        $this->assertSame([], $cssVariables['dark']);
    }

    public static function provideGrayScales(): iterable
    {
        yield [GrayScale::NEUTRAL, '--true-gray-'];
        yield [GrayScale::STONE, '--warm-gray-'];
        yield [GrayScale::ZINC, '--neutral-gray-'];
        yield [GrayScale::GRAY, '--cool-gray-'];
        yield [GrayScale::SLATE, '--blue-gray-'];
    }

    public function testGraysDarkVariant(): void
    {
        $cssVariables = Theme::new()->grays(GrayScale::ZINC, dark: GrayScale::STONE)->getAsDto()->getCssVariables();

        $this->assertSame('var(--neutral-gray-500)', $cssVariables['common']['--gray-500']);
        $this->assertSame('var(--warm-gray-500)', $cssVariables['dark']['--gray-500']);
        $this->assertCount(11, $cssVariables['common']);
        $this->assertCount(11, $cssVariables['dark']);
    }

    public function testInvalidGraysAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Theme::new()->grays('mauve');
    }

    public function testInvalidDarkGraysAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Theme::new()->grays(GrayScale::ZINC, dark: 'mauve');
    }

    public function testEmptyThemeHasNoCssVariables(): void
    {
        $themeDto = Theme::new()->getAsDto();

        $this->assertFalse($themeDto->hasCssVariables());
        $this->assertSame(['common' => [], 'dark' => []], $themeDto->getCssVariables());
    }

    public function testCssVariablesOfFullyConfiguredTheme(): void
    {
        $themeDto = Theme::new()
            ->primaryColor('#15803d', dark: 'oklch(0.6 0.2 150)')
            ->radius('0.5rem')
            ->spacing('md')
            ->grays(GrayScale::ZINC, dark: GrayScale::STONE)
            ->getAsDto();

        $cssVariables = $themeDto->getCssVariables();

        $this->assertTrue($themeDto->hasCssVariables());
        $this->assertSame('#15803d', $cssVariables['common']['--ea-primary']);
        $this->assertSame('#fff', $cssVariables['common']['--ea-primary-foreground']);
        $this->assertSame('0.5rem', $cssVariables['common']['--ea-radius']);
        $this->assertSame('0.125rem', $cssVariables['common']['--ea-spacing']);
        $this->assertSame('var(--neutral-gray-50)', $cssVariables['common']['--gray-50']);
        $this->assertSame('var(--neutral-gray-950)', $cssVariables['common']['--gray-950']);
        $this->assertSame('oklch(0.6 0.2 150)', $cssVariables['dark']['--ea-primary']);
        $this->assertSame('#000', $cssVariables['dark']['--ea-primary-foreground']);
        $this->assertSame('var(--warm-gray-50)', $cssVariables['dark']['--gray-50']);
        $this->assertSame('var(--warm-gray-950)', $cssVariables['dark']['--gray-950']);
    }

    public function testCssVariablesWithoutDarkPrimary(): void
    {
        $cssVariables = Theme::new()->primaryColor('#15803d')->getAsDto()->getCssVariables();

        $this->assertSame([], $cssVariables['dark']);
    }

    public function testMethodsAreFluent(): void
    {
        $theme = Theme::new();

        $this->assertSame($theme, $theme->primaryColor('#15803d'));
        $this->assertSame($theme, $theme->radius('md'));
        $this->assertSame($theme, $theme->spacing('md'));
        $this->assertSame($theme, $theme->grays(GrayScale::NEUTRAL));
        $this->assertSame($theme, $theme->font(Font::Inter));
    }

    public function testDefaultFontsEmitNoCssVariablesAndPreloadIbmPlexSans(): void
    {
        $themeDto = Theme::new()->getAsDto();

        $this->assertNull($themeDto->getBaseFont());
        $this->assertNull($themeDto->getMonospaceFont());
        $this->assertSame(['fonts/ibm-plex-sans-latin-wght-normal.woff2'], $themeDto->getFontPreloadPaths());
        $this->assertNull($themeDto->getRemoteFontStylesheetUrl());
        $this->assertFalse($themeDto->hasCssVariables());
    }

    #[DataProvider('provideBundledBaseFonts')]
    public function testBundledBaseFont(Font $font, string $fontFamily, string $fileName): void
    {
        $themeDto = Theme::new()->font(base: $font)->getAsDto();
        $cssVariables = $themeDto->getCssVariables()['common'];

        $this->assertSame($font, $themeDto->getBaseFont());
        $this->assertSame(sprintf('"%1$s", "%1$s Fallback", var(--font-family-system-sans-serif)', $fontFamily), $cssVariables['--font-family-sans-serif']);
        $this->assertArrayNotHasKey('--font-family-monospace', $cssVariables);
        $this->assertSame([sprintf('fonts/%s-latin-wght-normal.woff2', $fileName)], $themeDto->getFontPreloadPaths());
        $this->assertNull($themeDto->getRemoteFontStylesheetUrl());
    }

    public static function provideBundledBaseFonts(): iterable
    {
        yield [Font::Inter, 'Inter Variable', 'inter'];
        yield [Font::IbmPlexSans, 'IBM Plex Sans Variable', 'ibm-plex-sans'];
        yield [Font::Geist, 'Geist Variable', 'geist'];
    }

    public function testBundledMonospaceFont(): void
    {
        $themeDto = Theme::new()->font(monospace: Font::JetBrainsMono)->getAsDto();
        $cssVariables = $themeDto->getCssVariables()['common'];

        $this->assertNull($themeDto->getBaseFont());
        $this->assertSame('"JetBrains Mono Variable", "JetBrains Mono Variable Fallback", var(--font-family-system-monospace)', $cssVariables['--font-family-monospace']);
        $this->assertArrayNotHasKey('--font-family-sans-serif', $cssVariables);
        // the monospace font is never preloaded (most pages don't use it)
        $this->assertSame(['fonts/ibm-plex-sans-latin-wght-normal.woff2'], $themeDto->getFontPreloadPaths());
    }

    public function testSystemFonts(): void
    {
        $themeDto = Theme::new()->font(base: Font::System, monospace: Font::System)->getAsDto();

        $this->assertSame([
            '--font-family-sans-serif' => 'var(--font-family-system-sans-serif)',
            '--font-family-monospace' => 'var(--font-family-system-monospace)',
        ], $themeDto->getCssVariables()['common']);
        $this->assertSame([], $themeDto->getFontPreloadPaths());
        $this->assertNull($themeDto->getRemoteFontStylesheetUrl());
    }

    public function testRemoteFonts(): void
    {
        $themeDto = Theme::new()->font(base: 'Open Sans', monospace: 'Fira Code')->getAsDto();
        $cssVariables = $themeDto->getCssVariables()['common'];

        $this->assertSame('Open Sans', $themeDto->getBaseFont());
        $this->assertSame('"Open Sans", var(--font-family-system-sans-serif)', $cssVariables['--font-family-sans-serif']);
        $this->assertSame('"Fira Code", var(--font-family-system-monospace)', $cssVariables['--font-family-monospace']);
        $this->assertSame([], $themeDto->getFontPreloadPaths());
        $this->assertSame('https://fonts.bunny.net/css2?family=Open+Sans:wght@400;500;600&family=Fira+Code:wght@400;500;600&display=swap', $themeDto->getRemoteFontStylesheetUrl());
    }

    public function testRemoteMonospaceFontKeepsTheDefaultBaseFont(): void
    {
        $themeDto = Theme::new()->font(monospace: 'Fira Code')->getAsDto();

        $this->assertSame(['fonts/ibm-plex-sans-latin-wght-normal.woff2'], $themeDto->getFontPreloadPaths());
        $this->assertSame('https://fonts.bunny.net/css2?family=Fira+Code:wght@400;500;600&display=swap', $themeDto->getRemoteFontStylesheetUrl());
    }

    #[DataProvider('provideBundledFontNames')]
    public function testRemoteFontNamesOfBundledFontsUseTheBundledFont(string $fontName, Font $font): void
    {
        $themeDto = Theme::new()->font(base: $fontName)->getAsDto();

        $this->assertSame($font, $themeDto->getBaseFont());
        $this->assertNull($themeDto->getRemoteFontStylesheetUrl());
    }

    public static function provideBundledFontNames(): iterable
    {
        yield ['Inter', Font::Inter];
        yield ['inter', Font::Inter];
        yield [' IBM  Plex  Sans ', Font::IbmPlexSans];
        yield ['GEIST', Font::Geist];
    }

    #[DataProvider('provideInvalidRemoteFontNames')]
    public function testInvalidRemoteFontNamesAreRejected(string $fontName): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a valid Google Fonts family name');

        Theme::new()->font(base: $fontName);
    }

    public static function provideInvalidRemoteFontNames(): iterable
    {
        yield 'CSS injection' => ["Inter'); }"];
        yield 'HTML injection' => ['Inter</style>'];
        yield 'quotes' => ['"Open Sans"'];
        yield 'URL injection' => ['Open Sans&family=Roboto'];
        yield 'URL encoding' => ['Open%20Sans'];
        yield 'CSS fallback' => ['Open Sans, sans-serif'];
        yield 'new line' => ["Open\nSans"];
        yield 'non-ASCII' => ['Ópen Sans'];
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 65)];
    }

    public function testInvalidRemoteMonospaceFontNamesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('given as the monospace font');

        Theme::new()->font(monospace: 'Fira Code;}');
    }

    public function testMonospaceFontCantBeTheBaseFont(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The Font::JetBrainsMono font can\'t be used as the base font');

        Theme::new()->font(base: Font::JetBrainsMono);
    }

    #[DataProvider('provideNonMonospaceFonts')]
    public function testNonMonospaceFontsCantBeTheMonospaceFont(Font|string $font): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('can\'t be used as the monospace font');

        Theme::new()->font(monospace: $font);
    }

    public static function provideNonMonospaceFonts(): iterable
    {
        yield [Font::Inter];
        yield [Font::IbmPlexSans];
        yield [Font::Geist];
        // the names of bundled fonts are resolved to the bundled fonts
        yield ['Geist'];
    }
}
