<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Config;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\Font;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\GrayScale;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ThemeDto;
use EasyCorp\Bundle\EasyAdminBundle\Util\ThemeColorCalculator;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class Theme
{
    private const RADIUS_PRESETS = [
        'none' => '0',
        'xs' => '0.125rem',
        'sm' => '0.1875rem',
        'md' => '0.25rem',
        'lg' => '0.375rem',
        'xl' => '0.5rem',
    ];

    // deliberately a narrow band (±25% around the default) and without a 'none'
    // preset: the spacing unit scales every padding, margin, gap and control
    // size of the backend linearly, so small changes already resize the whole UI
    private const SPACING_PRESETS = [
        'xs' => '0.09375rem',
        'sm' => '0.109375rem',
        'md' => '0.125rem',
        'lg' => '0.140625rem',
        'xl' => '0.15625rem',
    ];

    // the public names are the Tailwind/shadcn gray scale names; the values are
    // the prefixes of the color ramps defined in color-palette.css, whose legacy
    // names don't match ('neutral-gray' is zinc, NOT the 'neutral' scale)
    private const GRAY_RAMPS = [
        GrayScale::NEUTRAL => '--true-gray-',
        GrayScale::STONE => '--warm-gray-',
        GrayScale::ZINC => '--neutral-gray-',
        GrayScale::GRAY => '--cool-gray-',
        GrayScale::SLATE => '--blue-gray-',
    ];

    private ?string $primaryColor = null;
    private ?string $primaryForeground = null;
    private ?string $darkPrimaryColor = null;
    private ?string $darkPrimaryForeground = null;
    private ?string $radius = null;
    private ?string $spacing = null;
    private ?string $grayRampPrefix = null;
    private ?string $darkGrayRampPrefix = null;
    private Font|string|null $baseFont = null;
    private Font|string|null $monospaceFont = null;

    private function __construct()
    {
    }

    public static function new(): self
    {
        return new self();
    }

    /**
     * Sets the accent color of the backend. The optional $dark argument defines
     * a different primary color for the dark color scheme (a good dark variant
     * is usually retuned, not just lightened; if not set, the same color is
     * used in both schemes).
     *
     * Colors can use the hexadecimal ("#15803d"), "rgb()", "hsl()" or "oklch()"
     * formats, without an alpha channel. The color of the text/icons displayed
     * on top of primary-colored elements is computed automatically based on the
     * luminance of this color.
     */
    public function primaryColor(string $color, ?string $dark = null): self
    {
        $this->primaryColor = self::validateColor($color, 'primaryColor');
        $this->primaryForeground = ThemeColorCalculator::foregroundFor($this->primaryColor);

        $this->darkPrimaryColor = null === $dark ? null : self::validateColor($dark, 'primaryColor');
        $this->darkPrimaryForeground = null === $this->darkPrimaryColor ? null : ThemeColorCalculator::foregroundFor($this->darkPrimaryColor);

        return $this;
    }

    /**
     * Sets the base border radius from which all the border radius values of
     * the backend are derived. It accepts a CSS length in "px" or "rem" units
     * (e.g. "0.5rem") or one of these presets: "none", "xs", "sm", "md"
     * (the default look), "lg", "xl".
     */
    public function radius(string $value): self
    {
        $this->radius = self::RADIUS_PRESETS[$value] ?? self::validateLength($value, 'radius', array_keys(self::RADIUS_PRESETS));

        return $this;
    }

    /**
     * Sets the base spacing unit from which all the paddings, margins, gaps and
     * control sizes of the backend are derived; it defines the density of the
     * whole interface. It accepts a CSS length in "px" or "rem" units or one of
     * these presets: "xs", "sm", "md" (the default look), "lg", "xl".
     */
    public function spacing(string $value): self
    {
        $this->spacing = self::SPACING_PRESETS[$value] ?? self::validateLength($value, 'spacing', array_keys(self::SPACING_PRESETS));

        return $this;
    }

    /**
     * Sets the gray color scale used by all the neutral surfaces, borders and
     * text colors of the backend. The optional $dark argument defines a
     * different gray scale for the dark color scheme (if not set, the same
     * scale is used in both schemes).
     *
     * Use one of the GrayScale constants: "neutral", "stone" (warm), "zinc",
     * "gray" or "slate" (cool). The default look uses "slate" in the light
     * scheme and "neutral" in the dark scheme.
     */
    public function grays(string $grayScale, ?string $dark = null): self
    {
        $this->grayRampPrefix = self::validateGrayScale($grayScale);
        $this->darkGrayRampPrefix = null === $dark ? null : self::validateGrayScale($dark);

        return $this;
    }

    /**
     * Sets the fonts of the backend: $base is used for all the text and
     * $monospace for code (e.g. the contents of code fields). By default, the
     * backend uses IBM Plex Sans for text and the monospace fonts of the
     * operating system for code (pass Font::System to use the fonts of the
     * operating system for text too).
     *
     * A Font enum case selects a font bundled with EasyAdmin, served by your
     * own application. A string is a Google Fonts family name, served through
     * Bunny Fonts (https://fonts.bunny.net), a privacy-friendly mirror of
     * Google Fonts; if the string is the name of a bundled font (e.g. 'Inter'),
     * the bundled font is used instead. A null value keeps the default font.
     *
     * To use any other font (e.g. a font of your company), load it in a CSS file
     * added with addCssFile() and override the --font-family-sans-serif and
     * --font-family-monospace CSS variables.
     */
    public function font(Font|string|null $base = null, Font|string|null $monospace = null): self
    {
        $this->baseFont = null === $base ? null : self::validateFont($base, monospace: false);
        $this->monospaceFont = null === $monospace ? null : self::validateFont($monospace, monospace: true);

        return $this;
    }

    public function getAsDto(): ThemeDto
    {
        return new ThemeDto(
            $this->primaryColor,
            $this->primaryForeground,
            $this->darkPrimaryColor,
            $this->darkPrimaryForeground,
            $this->radius,
            $this->spacing,
            $this->grayRampPrefix,
            $this->darkGrayRampPrefix,
            $this->baseFont,
            $this->monospaceFont,
        );
    }

    // remote font names are emitted verbatim in a URL and inside an inline
    // <style> element, so this validation is a security boundary against CSS and
    // URL injection: only letters, digits and single spaces are accepted, which
    // is enough for every Google Fonts family name
    private static function validateFont(Font|string $font, bool $monospace): Font|string
    {
        $argumentName = $monospace ? 'monospace' : 'base';

        if (\is_string($font)) {
            $fontName = trim($font);
            foreach (Font::cases() as $bundledFont) {
                if (0 === strcasecmp($bundledFont->value, preg_replace('/\s+/', ' ', $fontName))) {
                    $font = $bundledFont;
                    break;
                }
            }

            if (\is_string($font)) {
                if (1 !== preg_match('/^[A-Za-z0-9]+(?: [A-Za-z0-9]+)*$/', $fontName) || \strlen($fontName) > 64) {
                    throw new \InvalidArgumentException(sprintf('The "%s" value given as the %s font of the font() method is not a valid Google Fonts family name. Use the name of the font as displayed on fonts.google.com, which only contains letters, digits and spaces (e.g. "Open Sans").', $font, $argumentName));
                }

                return $fontName;
            }
        }

        if ($monospace && !\in_array($font, [Font::System, Font::JetBrainsMono], true)) {
            throw new \InvalidArgumentException(sprintf('The Font::%s font can\'t be used as the monospace font of the font() method because it\'s not a monospace font. Use Font::JetBrainsMono, Font::System or the name of a monospace font of Google Fonts (e.g. "Fira Code").', $font->name));
        }

        if (!$monospace && Font::JetBrainsMono === $font) {
            throw new \InvalidArgumentException('The Font::JetBrainsMono font can\'t be used as the base font of the font() method because it\'s a monospace font. Use it as the monospace font instead (e.g. font(monospace: Font::JetBrainsMono)).');
        }

        return $font;
    }

    private static function validateGrayScale(string $grayScale): string
    {
        if (!isset(self::GRAY_RAMPS[$grayScale])) {
            throw new \InvalidArgumentException(sprintf('The "%s" value given to the grays() method is not valid. It can only be one of: "%s".', $grayScale, implode('", "', array_keys(self::GRAY_RAMPS))));
        }

        return self::GRAY_RAMPS[$grayScale];
    }

    // the returned values are emitted verbatim inside an inline <style> element,
    // so this validation is a security boundary against CSS injection, not just
    // a usability check: only strictly anchored color grammars are accepted
    private static function validateColor(string $color, string $optionName): string
    {
        $color = trim($color);

        if (1 === preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return $color;
        }

        if (1 === preg_match('/^rgb\(\s*(\d{1,3})\s*(?:,\s*|\s+)(\d{1,3})\s*(?:,\s*|\s+)(\d{1,3})\s*\)$/i', $color, $matches)
            && (int) $matches[1] <= 255 && (int) $matches[2] <= 255 && (int) $matches[3] <= 255) {
            return $color;
        }

        if (1 === preg_match('/^hsl\(\s*(\d{1,3}(?:\.\d+)?)(?:deg)?\s*(?:,\s*|\s+)(\d{1,3}(?:\.\d+)?)%\s*(?:,\s*|\s+)(\d{1,3}(?:\.\d+)?)%\s*\)$/i', $color, $matches)
            && (float) $matches[1] <= 360 && (float) $matches[2] <= 100 && (float) $matches[3] <= 100) {
            return $color;
        }

        if (1 === preg_match('/^oklch\(\s*(\d{1,3}(?:\.\d+)?%|[01](?:\.\d+)?|\.\d+)\s+(\d(?:\.\d+)?|\.\d+)\s+(\d{1,3}(?:\.\d+)?)(?:deg)?\s*\)$/i', $color, $matches)) {
            $lightness = str_ends_with($matches[1], '%') ? (float) $matches[1] / 100 : (float) $matches[1];
            if ($lightness <= 1 && (float) $matches[2] <= 0.5 && (float) $matches[3] <= 360) {
                return $color;
            }
        }

        throw new \InvalidArgumentException(sprintf('The "%s" value given to the %s option is not a valid color. Use the hexadecimal ("#15803d"), "rgb()", "hsl()" or "oklch()" formats, without an alpha channel.', $color, $optionName));
    }

    /**
     * @param list<string> $presetNames
     */
    private static function validateLength(string $value, string $optionName, array $presetNames): string
    {
        if (1 === preg_match('/^(?:0|(?:\d+|\d*\.\d+)(?:px|rem))$/', $value)) {
            return $value;
        }

        throw new \InvalidArgumentException(sprintf('The "%s" value given to the %s option is not valid. Use a CSS length in "px" or "rem" units (e.g. "0.5rem") or one of these presets: "%s".', $value, $optionName, implode('", "', $presetNames)));
    }
}
