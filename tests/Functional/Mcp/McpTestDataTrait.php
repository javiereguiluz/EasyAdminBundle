<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Mcp;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity\Organization;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity\Tag;

/**
 * Creates the database of the MCP test app with two organizations ("Organization A"
 * and "Organization B"), two products in each of them and three tags.
 */
trait McpTestDataTrait
{
    /**
     * @return array<string, int> product ids indexed by product name
     */
    private static function createTestData(EntityManagerInterface $entityManager): array
    {
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $tags = [new Tag('Tag 1'), new Tag('Tag 2'), new Tag('Tag 3')];
        foreach ($tags as $tag) {
            $entityManager->persist($tag);
        }

        $products = [];
        foreach (['A', 'B'] as $organizationSuffix) {
            $organization = new Organization('Organization '.$organizationSuffix);
            $entityManager->persist($organization);
            foreach ([1, 2] as $productNumber) {
                $product = new Product(sprintf('Product %s%d', $organizationSuffix, $productNumber), $organization);
                $product->description = sprintf('Description of product %s%d', $organizationSuffix, $productNumber);
                $product->priceInCents = 1000 * $productNumber + 50;
                $product->active = 1 === $productNumber;
                $product->createdAt = new \DateTimeImmutable(sprintf('2026-01-0%d 10:30:00', $productNumber), new \DateTimeZone('UTC'));
                $product->status = 1 === $productNumber ? 'published' : 'draft';
                $product->secretNote = sprintf('Secret %s%d', $organizationSuffix, $productNumber);
                $product->cardNumber = '411111111111'.$productNumber.'234';
                $product->internalCode = sprintf('CODE-%s%d', $organizationSuffix, $productNumber);
                foreach (\array_slice($tags, 0, $productNumber + 1) as $tag) {
                    $product->tags->add($tag);
                }
                $entityManager->persist($product);
                $products[] = $product;
            }
        }
        $entityManager->flush();
        $productIds = [];
        foreach ($products as $product) {
            $productIds[$product->getName()] = $product->getId();
        }
        // records must be loaded from the database, as in a real request
        $entityManager->clear();

        return $productIds;
    }
}
