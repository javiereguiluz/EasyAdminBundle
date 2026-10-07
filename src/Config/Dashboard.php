<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Config;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\ColorScheme;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextDirection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\DashboardDto;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpExposureMode;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class Dashboard
{
    private DashboardDto $dto;

    private function __construct(DashboardDto $dashboardDto)
    {
        $this->dto = $dashboardDto;
    }

    public static function new(): self
    {
        $dto = new DashboardDto();

        return new self($dto);
    }

    public function setFaviconPath(string $path): self
    {
        $this->dto->setFaviconPath($path);

        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->dto->setTitle($title);

        return $this;
    }

    public function setTranslationDomain(string $translationDomain): self
    {
        $this->dto->setTranslationDomain($translationDomain);

        return $this;
    }

    public function setTextDirection(string $direction): self
    {
        if (!\in_array($direction, [TextDirection::LTR, TextDirection::RTL], true)) {
            throw new \InvalidArgumentException(sprintf('The "%s" value given to the textDirection option is not valid. It can only be "%s" or "%s"', $direction, TextDirection::LTR, TextDirection::RTL));
        }

        $this->dto->setTextDirection($direction);

        return $this;
    }

    public function renderContentMaximized(bool $maximized = true): self
    {
        $this->dto->setContentWidth($maximized ? Crud::LAYOUT_CONTENT_FULL : Crud::LAYOUT_CONTENT_DEFAULT);

        return $this;
    }

    public function renderSidebarMinimized(bool $minimized = true): self
    {
        $this->dto->setSidebarWidth($minimized ? Crud::LAYOUT_SIDEBAR_COMPACT : Crud::LAYOUT_SIDEBAR_DEFAULT);

        return $this;
    }

    public function generateRelativeUrls(bool $relativeUrls = true): self
    {
        $this->dto->setAbsoluteUrls(!$relativeUrls);

        return $this;
    }

    public function disableDarkMode(bool $disableDarkMode = true): self
    {
        $this->dto->setEnableDarkMode(!$disableDarkMode);

        return $this;
    }

    public function setDefaultColorScheme(string $colorScheme): self
    {
        if (!\in_array($colorScheme, [ColorScheme::LIGHT, ColorScheme::DARK, ColorScheme::AUTO], true)) {
            throw new \InvalidArgumentException(sprintf('The "%s" value given to the colorScheme option is not valid. It can only be "%s", "%s" or "%s"', $colorScheme, ColorScheme::LIGHT, ColorScheme::DARK, ColorScheme::AUTO));
        }

        $this->dto->setDefaultColorScheme($colorScheme);

        return $this;
    }

    /**
     * @param array<Locale|string> $locales
     */
    public function setLocales(array $locales): self
    {
        $localeDtos = [];
        foreach ($locales as $key => $value) {
            $locale = match (true) {
                $value instanceof Locale => $value,
                \is_string($key) => Locale::new($key, (string) $value),
                default => Locale::new((string) $value),
            };

            $localeDtos[] = $locale->getAsDto();
        }

        $this->dto->setLocales($localeDtos);

        return $this;
    }

    public function setTheme(Theme $theme): self
    {
        $this->dto->setTheme($theme->getAsDto());

        return $this;
    }

    public function useEntityTranslations(bool $useEntityTranslations = true): self
    {
        $this->dto->setUseEntityTranslations($useEntityTranslations);

        return $this;
    }

    /**
     * Exposes to MCP clients only the CRUD controllers marked with
     * Crud::exposeToMcp() or #[ExposeToMcp].
     *
     * @experimental
     */
    public function exposeSelectedCrudsToMcp(): self
    {
        $this->setMcpExposureMode(McpExposureMode::Selected, __FUNCTION__);

        return $this;
    }

    /**
     * Exposes to MCP clients all CRUD controllers except those marked with
     * Crud::excludeFromMcp() or #[ExcludeFromMcp].
     *
     * @experimental
     */
    public function exposeAllCrudsToMcp(): self
    {
        $this->setMcpExposureMode(McpExposureMode::All, __FUNCTION__);

        return $this;
    }

    public function getAsDto(): DashboardDto
    {
        return $this->dto;
    }

    private function setMcpExposureMode(McpExposureMode $mode, string $methodName): void
    {
        $currentMode = $this->dto->getMcpExposureMode();
        if (null !== $currentMode && $mode !== $currentMode) {
            throw new \LogicException(sprintf('The dashboard cannot call both "exposeSelectedCrudsToMcp()" and "exposeAllCrudsToMcp()". Remove one of them (the last call was "%s()").', $methodName));
        }

        $this->dto->setMcpExposureMode($mode);
    }
}
