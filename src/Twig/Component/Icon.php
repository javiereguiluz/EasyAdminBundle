<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Twig\Component;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\IconSet;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\IconDto;
use EasyCorp\Bundle\EasyAdminBundle\Icon\FontAwesomeIconResolver;
use EasyCorp\Bundle\EasyAdminBundle\Icon\ResolvedFontAwesomeIcon;
use Symfony\UX\Icons\IconRendererInterface;

class Icon
{
    private const FILETYPES_ICON_PREFIX = 'filetypes';
    private const ICONS_DIR = __DIR__.'/../../../assets/icons';

    public ?string $name = null;
    private ?string $iconSet = null;

    /** @var array<string, array{path: string, contents: string}> */
    private static array $internalIconCache = [];
    private readonly FontAwesomeIconResolver $fontAwesomeIconResolver;

    public function __construct(
        private readonly AdminContextProviderInterface $adminContextProvider,
        ?FontAwesomeIconResolver $fontAwesomeIconResolver = null,
        // only used to check that Symfony UX Icons is enabled; icons are rendered with its ux_icon() Twig function
        private readonly ?IconRendererInterface $uxIconRenderer = null,
    ) {
        $this->fontAwesomeIconResolver = $fontAwesomeIconResolver ?? new FontAwesomeIconResolver();
    }

    public function isBuiltInIconSet(): bool
    {
        return IconSet::Custom !== $this->getIconSet();
    }

    public function isFontAwesomeIconSet(): bool
    {
        return IconSet::FontAwesome === $this->getIconSet();
    }

    public function getIcon(): IconDto
    {
        return $this->getIconDto(trim($this->name ?? ''), $this->getIconSet());
    }

    private function getIconSet(): string
    {
        return $this->iconSet ?? ($this->iconSet = $this->adminContextProvider->getContext()?->getAssets()->getIconSet() ?? IconSet::FontAwesome);
    }

    private function getDefaultIconPrefix(): string
    {
        return $this->adminContextProvider->getContext()?->getAssets()->getDefaultIconPrefix() ?? '';
    }

    private function getIconFamily(): ?string
    {
        return $this->adminContextProvider->getContext()?->getAssets()->getIconFamily();
    }

    private function getIconDto(string $iconName, string $iconSet): IconDto
    {
        if (str_starts_with($iconName, IconSet::Internal.':') || str_starts_with($iconName, self::FILETYPES_ICON_PREFIX.':')) {
            return $this->getInternalIcon($iconName);
        }

        if (IconSet::FontAwesome === $iconSet && null !== $fontAwesomeIcon = $this->fontAwesomeIconResolver->resolve($iconName)) {
            return IconDto::new(name: $iconName, path: $fontAwesomeIcon->path, svgContents: $this->getFontAwesomeSvg($fontAwesomeIcon), iconSet: IconSet::FontAwesome);
        }

        if (IconSet::Custom === $iconSet && '' !== $iconName && null === $this->uxIconRenderer && null !== $iconFamily = $this->getIconFamily()) {
            throw new \LogicException(sprintf('The backend uses the "%s" icon family (configured with the useIconFamily() method of the Assets class), but Symfony UX Icons is not installed or enabled. Run "composer require symfony/ux-icons symfony/http-client" to install it.', $iconFamily));
        }

        if (!str_contains($iconName, ':') && '' !== $defaultIconPrefix = $this->getDefaultIconPrefix()) {
            $iconName = sprintf('%s:%s', $defaultIconPrefix, $iconName);
        }

        return IconDto::new(name: $iconName, iconSet: $iconSet);
    }

    /**
     * Adds the same attributes that FontAwesome adds when rendering icons as SVG with
     * JavaScript, and keeps the original CSS classes so the CSS selectors and the
     * FontAwesome modifier classes (e.g. 'fa-fw', 'fa-spin') keep working.
     */
    private function getFontAwesomeSvg(ResolvedFontAwesomeIcon $icon): string
    {
        $attributes = sprintf(
            'class="%s" data-prefix="%s" data-icon="%s" aria-hidden="true"',
            htmlspecialchars(implode(' ', $icon->classes), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
            $icon->style->getPrefix(),
            $icon->name,
        );

        return preg_replace('/^<svg /', '<svg '.$attributes.' ', $icon->svgContents, 1);
    }

    private function getInternalIcon(string $internalIconName): IconDto
    {
        // the same internal icons are rendered many times per page (e.g. once per
        // row on CRUD index pages), so cache the SVG file contents to avoid
        // reading the same files from disk repeatedly
        if (null === $cachedIcon = self::$internalIconCache[$internalIconName] ?? null) {
            [$iconPrefix, $iconName] = explode(':', $internalIconName, 2);
            if (1 !== preg_match('/^[a-zA-Z0-9_-]+$/D', $iconName)) {
                throw new \RuntimeException(sprintf('The icon "%s" does not exist. Check the icon name spelling and make sure that the "%s.svg" file exists in the "assets/icons/%s/" directory of EasyAdmin.', $internalIconName, $iconName, $iconPrefix));
            }

            $iconFilePath = sprintf('%s/%s/%s.svg', self::ICONS_DIR, $iconPrefix, $iconName);
            $content = @file_get_contents($iconFilePath);
            if (!\is_string($content)) {
                throw new \RuntimeException(sprintf('The icon "%s" does not exist. Check the icon name spelling and make sure that the "%s.svg" file exists in the "assets/icons/%s/" directory of EasyAdmin.', $internalIconName, $iconName, $iconPrefix));
            }

            $cachedIcon = self::$internalIconCache[$internalIconName] = ['path' => $iconFilePath, 'contents' => $content];
        }

        return IconDto::new(name: $internalIconName, path: $cachedIcon['path'], svgContents: $cachedIcon['contents'], iconSet: IconSet::Internal);
    }
}
