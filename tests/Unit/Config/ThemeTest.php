<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Config;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\GrayScale;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\ThemeColor;
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
        yield 'CSS named color' => ['tomato'];
        yield 'predefined color in other case' => ['Blue'];
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

    public function testPredefinedPrimaryColorSetsBothColorSchemes(): void
    {
        $themeDto = Theme::new()->primaryColor(ThemeColor::BLUE)->getAsDto();

        $this->assertSame('oklch(0.488 0.243 264.376)', $themeDto->getPrimaryColor());
        $this->assertSame('oklch(0.97 0.014 254.604)', $themeDto->getPrimaryForeground());
        $this->assertSame('oklch(0.424 0.199 265.638)', $themeDto->getDarkPrimaryColor());
        $this->assertSame('oklch(0.97 0.014 254.604)', $themeDto->getDarkPrimaryForeground());
    }

    public function testPredefinedPrimaryColorWithCustomDarkVariant(): void
    {
        $themeDto = Theme::new()->primaryColor(ThemeColor::EMERALD, dark: '#facc15')->getAsDto();

        $this->assertSame('oklch(0.508 0.118 165.612)', $themeDto->getPrimaryColor());
        $this->assertSame('#facc15', $themeDto->getDarkPrimaryColor());
        $this->assertSame('#000', $themeDto->getDarkPrimaryForeground());
    }

    public function testCustomPrimaryColorWithPredefinedDarkVariant(): void
    {
        $themeDto = Theme::new()->primaryColor('#15803d', dark: ThemeColor::NEUTRAL)->getAsDto();

        $this->assertSame('#15803d', $themeDto->getPrimaryColor());
        $this->assertSame('oklch(0.922 0 0)', $themeDto->getDarkPrimaryColor());
        $this->assertSame('oklch(0.205 0 0)', $themeDto->getDarkPrimaryForeground());
    }

    public function testEveryPredefinedPrimaryColorIsSupported(): void
    {
        $colors = (new \ReflectionClass(ThemeColor::class))->getConstants();

        $this->assertCount(18, $colors);
        foreach ($colors as $color) {
            $cssVariables = Theme::new()->primaryColor($color)->getAsDto()->getCssVariables();

            $this->assertStringStartsWith('oklch(', $cssVariables['common']['--ea-primary']);
            $this->assertStringStartsWith('oklch(', $cssVariables['dark']['--ea-primary']);
        }
    }

    #[DataProvider('provideLightPrimaryColors')]
    public function testLightPrimaryColorUsesItsForegroundAsTextInTheLightScheme(string $color, string $expectedTextColor): void
    {
        $cssVariables = Theme::new()->primaryColor($color)->getAsDto()->getCssVariables();

        $this->assertSame(['--ea-primary-on-surface' => $expectedTextColor], $cssVariables['light']);
    }

    public static function provideLightPrimaryColors(): iterable
    {
        yield [ThemeColor::YELLOW, 'oklch(0.421 0.095 57.708)'];
        yield [ThemeColor::LIME, 'oklch(0.405 0.101 131.063)'];
        yield ['#facc15', '#000'];
    }

    public function testDarkPrimaryColorIsUsedAsTextInTheLightScheme(): void
    {
        $this->assertSame([], Theme::new()->primaryColor(ThemeColor::BLUE)->getAsDto()->getCssVariables()['light']);
        $this->assertSame([], Theme::new()->primaryColor('#15803d')->getAsDto()->getCssVariables()['light']);
    }

    public function testPrimaryColorDrawsTheCheckIconsWithItsForeground(): void
    {
        $cssVariables = Theme::new()->primaryColor(ThemeColor::YELLOW, dark: '#1e3a8a')->getAsDto()->getCssVariables();

        // the light foreground of yellow is #733e0a and the dark one (for #1e3a8a) is #fff
        $this->assertStringContainsString('%23733e0a', $cssVariables['common']['--ea-primary-foreground-check-icon']);
        $this->assertStringContainsString('%23733e0a', $cssVariables['common']['--ea-primary-foreground-radio-icon']);
        $this->assertStringContainsString('%23ffffff', $cssVariables['dark']['--ea-primary-foreground-check-icon']);
        // the value is emitted in an HTML template, so it must not contain characters that HTML escaping changes
        $this->assertDoesNotMatchRegularExpression('/[\'"<>&]/', $cssVariables['common']['--ea-primary-foreground-check-icon']);
    }

    public function testCustomPrimaryColorUsesTheSameColorInBothSchemes(): void
    {
        $cssVariables = Theme::new()->primaryColor('#15803d')->getAsDto()->getCssVariables();

        $this->assertSame('#15803d', $cssVariables['common']['--ea-primary']);
        $this->assertArrayNotHasKey('--ea-primary', $cssVariables['dark']);
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
        $this->assertSame(['common' => [], 'light' => [], 'dark' => []], $themeDto->getCssVariables());
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
    }
}
