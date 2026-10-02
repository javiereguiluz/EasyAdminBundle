<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Dto;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Dto\AssetDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\AssetsDto;
use PHPUnit\Framework\TestCase;

class AssetsDtoTest extends TestCase
{
    public function testRepriseAssetsDefaultsToEmptyArray(): void
    {
        $this->assertSame([], (new AssetsDto())->getRepriseAssets());
    }

    public function testAddRepriseAsset(): void
    {
        $assetsDto = new AssetsDto();
        $assetsDto->addRepriseAsset(new AssetDto('admin'));

        $this->assertArrayHasKey('admin', $assetsDto->getRepriseAssets());
    }

    public function testAddRepriseAssetThrowsOnDuplicate(): void
    {
        $assetsDto = new AssetsDto();
        $assetsDto->addRepriseAsset(new AssetDto('admin'));

        $this->expectException(\InvalidArgumentException::class);
        $assetsDto->addRepriseAsset(new AssetDto('admin'));
    }

    public function testLoadedOnFiltersRepriseAssetsByPage(): void
    {
        $assetsDto = new AssetsDto();
        $indexOnly = new AssetDto('index-entry');
        $indexOnly->setLoadedOn(KeyValueStore::new([Crud::PAGE_INDEX => Crud::PAGE_INDEX]));
        $assetsDto->addRepriseAsset($indexOnly);

        $detailOnly = new AssetDto('detail-entry');
        $detailOnly->setLoadedOn(KeyValueStore::new([Crud::PAGE_DETAIL => Crud::PAGE_DETAIL]));
        $assetsDto->addRepriseAsset($detailOnly);

        $filtered = $assetsDto->loadedOn(Crud::PAGE_INDEX);

        $this->assertArrayHasKey('index-entry', $filtered->getRepriseAssets());
        $this->assertArrayNotHasKey('detail-entry', $filtered->getRepriseAssets());
    }

    public function testLoadedOnKeepsIconOptions(): void
    {
        $assetsDto = new AssetsDto();
        $assetsDto->setFontAwesomeCssEnabled(false);

        $assetsDto->setIconFamily('tabler');

        $this->assertFalse($assetsDto->loadedOn(Crud::PAGE_INDEX)->isFontAwesomeCssEnabled());
        $this->assertSame('tabler', $assetsDto->loadedOn(Crud::PAGE_INDEX)->getIconFamily());
    }

    public function testMergeWithMergesRepriseAssets(): void
    {
        $a = new AssetsDto();
        $a->addRepriseAsset(new AssetDto('a-entry'));

        $b = new AssetsDto();
        $b->addRepriseAsset(new AssetDto('b-entry'));

        $a->mergeWith($b);

        $this->assertArrayHasKey('a-entry', $a->getRepriseAssets());
        $this->assertArrayHasKey('b-entry', $a->getRepriseAssets());
    }
}
