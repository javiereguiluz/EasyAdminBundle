<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Dto;

use EasyCorp\Bundle\EasyAdminBundle\Util\ThemeColorCalculator;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final readonly class ThemeDto
{
    private const GRAY_STEPS = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];
    // the SVG icons of checked checkboxes and radio buttons (the same as Bootstrap's)
    // drawn with the primary foreground color ('%s' is the hexadecimal color)
    private const CHECK_ICON_SVG = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'><path fill='none' stroke='%s' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='m6 10 3 3 6-6'/></svg>";
    private const RADIO_ICON_SVG = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'><circle r='2' fill='%s'/></svg>";

    public function __construct(
        private ?string $primaryColor = null,
        private ?string $primaryForeground = null,
        private ?string $darkPrimaryColor = null,
        private ?string $darkPrimaryForeground = null,
        private ?string $radius = null,
        private ?string $spacing = null,
        private ?string $grayRampPrefix = null,
        private ?string $darkGrayRampPrefix = null,
        private ?string $primaryOnSurface = null,
    ) {
    }

    public function getPrimaryColor(): ?string
    {
        return $this->primaryColor;
    }

    public function getPrimaryForeground(): ?string
    {
        return $this->primaryForeground;
    }

    public function getDarkPrimaryColor(): ?string
    {
        return $this->darkPrimaryColor;
    }

    public function getDarkPrimaryForeground(): ?string
    {
        return $this->darkPrimaryForeground;
    }

    public function getPrimaryOnSurface(): ?string
    {
        return $this->primaryOnSurface;
    }

    public function getRadius(): ?string
    {
        return $this->radius;
    }

    public function getSpacing(): ?string
    {
        return $this->spacing;
    }

    public function getGrayRampPrefix(): ?string
    {
        return $this->grayRampPrefix;
    }

    public function getDarkGrayRampPrefix(): ?string
    {
        return $this->darkGrayRampPrefix;
    }

    /**
     * The CSS variable overrides to emit in the backend <head>, grouped by
     * target selector: 'common' variables apply to both color schemes
     * (':root, .ea-dark-scheme'), 'light' variables only to the light scheme
     * (':root', because the dark scheme redefines them on '.ea-dark-scheme')
     * and 'dark' variables only to '.ea-dark-scheme'.
     *
     * @return array{common: array<string, string>, light: array<string, string>, dark: array<string, string>}
     */
    public function getCssVariables(): array
    {
        $common = [];
        $light = [];
        $dark = [];

        if (null !== $this->primaryColor) {
            $common['--ea-primary'] = $this->primaryColor;
            $common['--ea-primary-foreground'] = $this->primaryForeground;
            $common += self::checkIcons($this->primaryForeground);
        }

        if (null !== $this->primaryOnSurface) {
            $light['--ea-primary-on-surface'] = $this->primaryOnSurface;
        }

        if (null !== $this->darkPrimaryColor) {
            $dark['--ea-primary'] = $this->darkPrimaryColor;
            $dark['--ea-primary-foreground'] = $this->darkPrimaryForeground;
            $dark += self::checkIcons($this->darkPrimaryForeground);
        }

        if (null !== $this->radius) {
            $common['--ea-radius'] = $this->radius;
        }

        if (null !== $this->spacing) {
            $common['--ea-spacing'] = $this->spacing;
        }

        if (null !== $this->grayRampPrefix) {
            foreach (self::GRAY_STEPS as $step) {
                $common['--gray-'.$step] = sprintf('var(%s%s)', $this->grayRampPrefix, $step);
            }
        }

        if (null !== $this->darkGrayRampPrefix) {
            foreach (self::GRAY_STEPS as $step) {
                $dark['--gray-'.$step] = sprintf('var(%s%s)', $this->darkGrayRampPrefix, $step);
            }
        }

        return ['common' => $common, 'light' => $light, 'dark' => $dark];
    }

    public function hasCssVariables(): bool
    {
        $cssVariables = $this->getCssVariables();

        return [] !== $cssVariables['common'] || [] !== $cssVariables['light'] || [] !== $cssVariables['dark'];
    }

    /**
     * @return array<string, string>
     */
    private static function checkIcons(?string $foregroundColor): array
    {
        if (null === $foregroundColor) {
            return [];
        }

        $hexColor = ThemeColorCalculator::toHex($foregroundColor);
        // fully URL-encoded and unquoted, so the value has no quotes that the
        // HTML escaping of the template would break inside the <style> element
        $dataUri = static fn (string $svg): string => 'url(data:image/svg+xml,'.rawurlencode(sprintf($svg, $hexColor)).')';

        return [
            '--ea-primary-foreground-check-icon' => $dataUri(self::CHECK_ICON_SVG),
            '--ea-primary-foreground-radio-icon' => $dataUri(self::RADIO_ICON_SVG),
        ];
    }
}
