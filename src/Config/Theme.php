<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Config;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\GrayScale;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\ThemeColor;
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

    // the shadcn/ui color themes: each value is [light color, light foreground,
    // dark color, dark foreground]; the chromatic colors use the Tailwind 700
    // shade in the light scheme and the 800 shade in the dark one (yellow and
    // lime use lighter shades with dark text), and 'neutral' is almost black in
    // the light scheme and almost white in the dark one
    private const PRIMARY_COLOR_PRESETS = [
        ThemeColor::NEUTRAL => ['oklch(0.205 0 0)', 'oklch(0.985 0 0)', 'oklch(0.922 0 0)', 'oklch(0.205 0 0)'],
        ThemeColor::RED => ['oklch(0.505 0.213 27.518)', 'oklch(0.971 0.013 17.38)', 'oklch(0.444 0.177 26.899)', 'oklch(0.971 0.013 17.38)'],
        ThemeColor::ORANGE => ['oklch(0.553 0.195 38.402)', 'oklch(0.98 0.016 73.684)', 'oklch(0.47 0.157 37.304)', 'oklch(0.98 0.016 73.684)'],
        ThemeColor::AMBER => ['oklch(0.555 0.163 48.998)', 'oklch(0.987 0.022 95.277)', 'oklch(0.473 0.137 46.201)', 'oklch(0.987 0.022 95.277)'],
        ThemeColor::YELLOW => ['oklch(0.852 0.199 91.936)', 'oklch(0.421 0.095 57.708)', 'oklch(0.795 0.184 86.047)', 'oklch(0.421 0.095 57.708)'],
        ThemeColor::LIME => ['oklch(0.841 0.238 128.85)', 'oklch(0.405 0.101 131.063)', 'oklch(0.768 0.233 130.85)', 'oklch(0.405 0.101 131.063)'],
        ThemeColor::GREEN => ['oklch(0.527 0.154 150.069)', 'oklch(0.982 0.018 155.826)', 'oklch(0.448 0.119 151.328)', 'oklch(0.982 0.018 155.826)'],
        ThemeColor::EMERALD => ['oklch(0.508 0.118 165.612)', 'oklch(0.979 0.021 166.113)', 'oklch(0.432 0.095 166.913)', 'oklch(0.979 0.021 166.113)'],
        ThemeColor::TEAL => ['oklch(0.511 0.096 186.391)', 'oklch(0.984 0.014 180.72)', 'oklch(0.437 0.078 188.216)', 'oklch(0.984 0.014 180.72)'],
        ThemeColor::CYAN => ['oklch(0.52 0.105 223.128)', 'oklch(0.984 0.019 200.873)', 'oklch(0.45 0.085 224.283)', 'oklch(0.984 0.019 200.873)'],
        ThemeColor::SKY => ['oklch(0.5 0.134 242.749)', 'oklch(0.977 0.013 236.62)', 'oklch(0.443 0.11 240.79)', 'oklch(0.977 0.013 236.62)'],
        ThemeColor::BLUE => ['oklch(0.488 0.243 264.376)', 'oklch(0.97 0.014 254.604)', 'oklch(0.424 0.199 265.638)', 'oklch(0.97 0.014 254.604)'],
        ThemeColor::INDIGO => ['oklch(0.457 0.24 277.023)', 'oklch(0.962 0.018 272.314)', 'oklch(0.398 0.195 277.366)', 'oklch(0.962 0.018 272.314)'],
        ThemeColor::VIOLET => ['oklch(0.491 0.27 292.581)', 'oklch(0.969 0.016 293.756)', 'oklch(0.432 0.232 292.759)', 'oklch(0.969 0.016 293.756)'],
        ThemeColor::PURPLE => ['oklch(0.496 0.265 301.924)', 'oklch(0.977 0.014 308.299)', 'oklch(0.438 0.218 303.724)', 'oklch(0.977 0.014 308.299)'],
        ThemeColor::FUCHSIA => ['oklch(0.518 0.253 323.949)', 'oklch(0.977 0.017 320.058)', 'oklch(0.452 0.211 324.591)', 'oklch(0.977 0.017 320.058)'],
        ThemeColor::PINK => ['oklch(0.525 0.223 3.958)', 'oklch(0.971 0.014 343.198)', 'oklch(0.459 0.187 3.815)', 'oklch(0.971 0.014 343.198)'],
        ThemeColor::ROSE => ['oklch(0.514 0.222 16.935)', 'oklch(0.969 0.015 12.422)', 'oklch(0.455 0.188 13.697)', 'oklch(0.969 0.015 12.422)'],
    ];

    private ?string $primaryColor = null;
    private ?string $primaryForeground = null;
    private ?string $darkPrimaryColor = null;
    private ?string $darkPrimaryForeground = null;
    private ?string $radius = null;
    private ?string $spacing = null;
    private ?string $grayRampPrefix = null;
    private ?string $darkGrayRampPrefix = null;
    private ?string $primaryOnSurface = null;

    private function __construct()
    {
    }

    public static function new(): self
    {
        return new self();
    }

    /**
     * Sets the accent color of the backend. Use one of the ThemeColor constants
     * ("neutral", "blue", "emerald", etc.) to apply a predefined color tuned for
     * both color schemes, or any color in the hexadecimal ("#15803d"), "rgb()",
     * "hsl()" or "oklch()" formats, without an alpha channel.
     *
     * The optional $dark argument defines a different primary color for the dark
     * color scheme (a good dark variant is usually retuned, not just lightened).
     * If not set, predefined colors use their own dark variant and custom colors
     * use the same color in both schemes. The color of the text/icons displayed
     * on top of primary-colored elements is computed automatically for custom
     * colors and predefined for the ThemeColor ones.
     */
    public function primaryColor(string $color, ?string $dark = null): self
    {
        $preset = self::PRIMARY_COLOR_PRESETS[$color] ?? null;

        if (null === $preset) {
            $this->primaryColor = self::validateColor($color);
            $this->primaryForeground = ThemeColorCalculator::foregroundFor($this->primaryColor);
        } else {
            [$this->primaryColor, $this->primaryForeground] = $preset;
        }

        // a light primary (e.g. yellow) is unreadable as text on the light
        // surfaces of the backend (active menu item, sorted column, etc.), so
        // those elements use the dark color displayed on top of it instead
        $this->primaryOnSurface = '#000' === ThemeColorCalculator::foregroundFor($this->primaryColor) ? $this->primaryForeground : null;

        if (null === $dark) {
            [$this->darkPrimaryColor, $this->darkPrimaryForeground] = null === $preset ? [null, null] : [$preset[2], $preset[3]];

            return $this;
        }

        $darkPreset = self::PRIMARY_COLOR_PRESETS[$dark] ?? null;
        if (null === $darkPreset) {
            $this->darkPrimaryColor = self::validateColor($dark);
            $this->darkPrimaryForeground = ThemeColorCalculator::foregroundFor($this->darkPrimaryColor);
        } else {
            [$this->darkPrimaryColor, $this->darkPrimaryForeground] = [$darkPreset[2], $darkPreset[3]];
        }

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
     * "gray" or "slate" (cool). The default look uses "neutral" in both the
     * light and dark schemes.
     */
    public function grays(string $grayScale, ?string $dark = null): self
    {
        $this->grayRampPrefix = self::validateGrayScale($grayScale);
        $this->darkGrayRampPrefix = null === $dark ? null : self::validateGrayScale($dark);

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
            $this->primaryOnSurface,
        );
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
    private static function validateColor(string $color): string
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

        throw new \InvalidArgumentException(sprintf('The "%s" value given to the primaryColor() method is not a valid color. Use the hexadecimal ("#15803d"), "rgb()", "hsl()" or "oklch()" formats, without an alpha channel, or one of these predefined colors: "%s".', $color, implode('", "', array_keys(self::PRIMARY_COLOR_PRESETS))));
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
