<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Config;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\IconFamily;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\IconSet;
use EasyCorp\Bundle\EasyAdminBundle\Dto\AssetDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\AssetsDto;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final readonly class Assets
{
    private function __construct(private AssetsDto $dto)
    {
    }

    public static function new(): self
    {
        $dto = new AssetsDto();

        return new self($dto);
    }

    public function addWebpackEncoreEntry(Asset|string $entryNameOrAsset): self
    {
        if (!class_exists('Symfony\\WebpackEncoreBundle\\WebpackEncoreBundle')) {
            throw new \RuntimeException(sprintf('You are trying to add a Webpack Encore entry called "%s" but WebpackEncoreBundle is not installed in your project. Try running "composer require symfony/webpack-encore-bundle"', $entryNameOrAsset));
        }

        if (\is_string($entryNameOrAsset)) {
            $this->dto->addWebpackEncoreAsset(new AssetDto($entryNameOrAsset));
        } else {
            $this->dto->addWebpackEncoreAsset($entryNameOrAsset->getAsDto());
        }

        return $this;
    }

    public function addRepriseEntry(Asset|string $entryNameOrAsset): self
    {
        if (!class_exists('Symfony\\Reprise\\RepriseBundle')) {
            throw new \RuntimeException(sprintf('You are trying to add a Reprise entry called "%s" but Symfony Reprise is not installed in your project. Try running "composer require symfony/reprise"', $entryNameOrAsset));
        }

        if (\is_string($entryNameOrAsset)) {
            $this->dto->addRepriseAsset(new AssetDto($entryNameOrAsset));
        } else {
            $this->dto->addRepriseAsset($entryNameOrAsset->getAsDto());
        }

        return $this;
    }

    public function addAssetMapperEntry(Asset|string ...$entryNameOrAsset): self
    {
        if (!class_exists('Symfony\\Component\\AssetMapper\\AssetMapper')) {
            $names = array_map(static fn (Asset|string $nameOrAsset) => \is_string($nameOrAsset) ? '"'.$nameOrAsset.'"' : '"'.$nameOrAsset->getAsDto()->getValue().'"', $entryNameOrAsset);

            throw new \RuntimeException(sprintf('You are trying to add '.(1 === \count($names) ? 'an AssetMapper entry called %s' : ' some AssetMapper entries (%s)').' but the AssetMapper component is not installed in your project. Try running "composer require symfony/asset-mapper"', implode(', ', $names)));
        }

        foreach ($entryNameOrAsset as $nameOrAsset) {
            if (\is_string($nameOrAsset)) {
                $this->dto->addAssetMapperAsset(new AssetDto($nameOrAsset));
            } else {
                $this->dto->addAssetMapperAsset($nameOrAsset->getAsDto());
            }
        }

        return $this;
    }

    public function addCssFile(Asset|string $pathOrAsset): self
    {
        if (\is_string($pathOrAsset)) {
            $this->dto->addCssAsset(new AssetDto($pathOrAsset));
        } else {
            $this->dto->addCssAsset($pathOrAsset->getAsDto());
        }

        return $this;
    }

    public function addJsFile(Asset|string $pathOrAsset): self
    {
        if (\is_string($pathOrAsset)) {
            $this->dto->addJsAsset(new AssetDto($pathOrAsset));
        } else {
            $this->dto->addJsAsset($pathOrAsset->getAsDto());
        }

        return $this;
    }

    public function addHtmlContentToHead(string $htmlContent): self
    {
        $this->dto->addHtmlContentToHead($htmlContent);

        return $this;
    }

    public function addHtmlContentToBody(string $htmlContent): self
    {
        $this->dto->addHtmlContentToBody($htmlContent);

        return $this;
    }

    public function useCustomIconSet(string $defaultIconPrefix = ''): self
    {
        if (str_contains($defaultIconPrefix, ':') || str_contains(trim($defaultIconPrefix), ' ')) {
            throw new \InvalidArgumentException(sprintf('The default icon prefix cannot contain spaces or the ":" character ("%s" given).', $defaultIconPrefix));
        }

        $this->dto->setIconSet(IconSet::Custom);
        $this->dto->setDefaultIconPrefix(trim($defaultIconPrefix));
        $this->dto->setIconFamily(null);

        return $this;
    }

    /**
     * Use this to display the icons of your backend with any icon set supported by
     * Symfony UX Icons (e.g. ->useIconFamily(IconFamily::Tabler) or ->useIconFamily('mdi')).
     * Icon names without a prefix (e.g. 'user') are looked up in that icon set;
     * names with a prefix (e.g. 'lucide:user') are used "as is".
     *
     * @param IconFamily|string $family a value of the IconFamily enum or any icon set prefix supported by Iconify
     */
    public function useIconFamily(IconFamily|string $family): self
    {
        if (IconFamily::FontAwesome === $family || IconFamily::FontAwesome->value === $family) {
            $this->dto->setIconSet(IconSet::FontAwesome);
            $this->dto->setDefaultIconPrefix('');
            $this->dto->setIconFamily(null);

            return $this;
        }

        $prefix = $family instanceof IconFamily ? $family->value : trim($family);
        if (1 !== preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/D', $prefix)) {
            throw new \InvalidArgumentException(sprintf('The icon family must be a value of the "%s" enum or a valid icon set prefix (e.g. "tabler", "mdi"), but "%s" was given.', IconFamily::class, $prefix));
        }

        $this->dto->setIconSet(IconSet::Custom);
        $this->dto->setDefaultIconPrefix($prefix);
        $this->dto->setIconFamily($prefix);

        return $this;
    }

    /**
     * EasyAdmin renders FontAwesome icons as inline SVG, so the FontAwesome CSS and
     * webfonts are only needed when your own templates include FontAwesome icons with
     * HTML elements (e.g. <i class="fa-solid fa-user"></i>) or use FontAwesome Pro icons.
     */
    public function disableFontAwesomeCss(bool $disable = true): self
    {
        $this->dto->setFontAwesomeCssEnabled(!$disable);

        return $this;
    }

    public function getAsDto(): AssetsDto
    {
        return $this->dto;
    }
}
