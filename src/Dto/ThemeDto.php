<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Dto;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\Font;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final readonly class ThemeDto
{
    private const GRAY_STEPS = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];

    // the base font used when Theme::font() doesn't set one; its CSS variables are
    // the defaults of variables-theme.css, so only its preload is emitted
    private const DEFAULT_BASE_FONT = Font::IbmPlexSans;

    // the names of the font files defined in fonts.css
    private const BUNDLED_FONT_FILE_PREFIXES = [
        'Inter' => 'inter',
        'IbmPlexSans' => 'ibm-plex-sans',
        'Geist' => 'geist',
        'JetBrainsMono' => 'jetbrains-mono',
    ];

    // used by the pages rendered without a dashboard (e.g. the login page)
    public const DEFAULT_FONT_PRELOAD_PATH = 'fonts/'.self::BUNDLED_FONT_FILE_PREFIXES[self::DEFAULT_BASE_FONT->name].'-latin-wght-normal.woff2';

    // the weights defined by the --font-weight-* CSS variables; Bunny Fonts serves
    // static font files, so the stylesheet only lists the weights used by the theme
    private const REMOTE_FONT_WEIGHTS = '400;500;600;700';

    public function __construct(
        private ?string $primaryColor = null,
        private ?string $primaryForeground = null,
        private ?string $darkPrimaryColor = null,
        private ?string $darkPrimaryForeground = null,
        private ?string $radius = null,
        private ?string $spacing = null,
        private ?string $grayRampPrefix = null,
        private ?string $darkGrayRampPrefix = null,
        private Font|string|null $baseFont = null,
        private Font|string|null $monospaceFont = null,
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

    public function getBaseFont(): Font|string|null
    {
        return $this->baseFont;
    }

    public function getMonospaceFont(): Font|string|null
    {
        return $this->monospaceFont;
    }

    /**
     * The asset paths (in the EasyAdmin asset package) of the font files that
     * the browser should start downloading as soon as possible: only the latin
     * subset of the bundled base font, which is needed by every page. The other
     * subsets and the monospace font are only downloaded when a page uses them.
     *
     * @return list<string>
     */
    public function getFontPreloadPaths(): array
    {
        $baseFont = $this->baseFont ?? self::DEFAULT_BASE_FONT;
        if (!$baseFont instanceof Font || Font::System === $baseFont) {
            return [];
        }

        return [sprintf('fonts/%s-latin-wght-normal.woff2', self::BUNDLED_FONT_FILE_PREFIXES[$baseFont->name])];
    }

    /**
     * The URL of the Bunny Fonts stylesheet that loads the Google Fonts
     * families configured in Theme::font(), or null when all fonts are bundled.
     */
    public function getRemoteFontStylesheetUrl(): ?string
    {
        $families = [];
        foreach ([$this->baseFont, $this->monospaceFont] as $font) {
            if (\is_string($font)) {
                $families[] = sprintf('family=%s:wght@%s', str_replace(' ', '+', $font), self::REMOTE_FONT_WEIGHTS);
            }
        }

        if ([] === $families) {
            return null;
        }

        return sprintf('https://fonts.bunny.net/css2?%s&display=swap', implode('&', array_unique($families)));
    }

    /**
     * The CSS variable overrides to emit in the backend <head>, grouped by
     * target selector: 'common' variables apply to both color schemes
     * (':root, .ea-dark-scheme') and 'dark' variables only to '.ea-dark-scheme'.
     *
     * @return array{common: array<string, string>, dark: array<string, string>}
     */
    public function getCssVariables(): array
    {
        $common = [];
        $dark = [];

        if (null !== $this->primaryColor) {
            $common['--ea-primary'] = $this->primaryColor;
            $common['--ea-primary-foreground'] = $this->primaryForeground;
        }

        if (null !== $this->darkPrimaryColor) {
            $dark['--ea-primary'] = $this->darkPrimaryColor;
            $dark['--ea-primary-foreground'] = $this->darkPrimaryForeground;
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

        if (null !== $this->baseFont) {
            $common['--font-family-sans-serif'] = self::getFontFamily($this->baseFont, '--font-family-system-sans-serif');
        }

        if (null !== $this->monospaceFont) {
            $common['--font-family-monospace'] = self::getFontFamily($this->monospaceFont, '--font-family-system-monospace');
        }

        return ['common' => $common, 'dark' => $dark];
    }

    public function hasCssVariables(): bool
    {
        $cssVariables = $this->getCssVariables();

        return [] !== $cssVariables['common'] || [] !== $cssVariables['dark'];
    }

    private static function getFontFamily(Font|string $font, string $systemFontsVariable): string
    {
        if (Font::System === $font) {
            return sprintf('var(%s)', $systemFontsVariable);
        }

        // bundled fonts are followed by their metric-matched fallback (see fonts.css)
        if ($font instanceof Font) {
            return sprintf('"%1$s Variable", "%1$s Variable Fallback", var(%2$s)', $font->value, $systemFontsVariable);
        }

        return sprintf('"%s", var(%s)', $font, $systemFontsVariable);
    }
}
