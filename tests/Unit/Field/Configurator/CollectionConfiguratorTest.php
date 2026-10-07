<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Field\Configurator;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Configurator\CollectionConfigurator;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Controller\NestedCrudForm\ProjectIssueNestedCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Controller\NestedCrudForm\ProjectWithNestedIssuesCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Controller\ProjectDomain\ProjectCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Entity\ProjectDomain\Project;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Field\AbstractFieldTest;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class CollectionConfiguratorTest extends AbstractFieldTest
{
    private EntityDto $projectDto;

    protected function setUp(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->projectDto = new EntityDto(Project::class, $entityManager->getClassMetadata(Project::class));

        /** @var CollectionConfigurator $collectionConfigurator */
        $collectionConfigurator = static::getContainer()->get(CollectionConfigurator::class);
        $this->configurator = $collectionConfigurator;
    }

    protected function getEntityDto(): EntityDto
    {
        return $this->projectDto;
    }

    /**
     * @dataProvider fields
     */
    public function test(FieldInterface $field): void
    {
        $field = $this->configure($field);
        $this->assertSame(CollectionType::class, $field->getFormType());
    }

    public static function fields(): \Generator
    {
        yield [CollectionField::new('projectIssues')];
        yield [CollectionField::new('favouriteProjectOf')];
        yield [CollectionField::new('projectTags')];
        yield [CollectionField::new('metaData')];
    }

    public function testNestedCollections(): void
    {
        $field = CollectionField::new('leadDeveloper.issues');
        $field->setCustomOption(CollectionField::OPTION_ENTRY_USES_CRUD_FORM, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'The "leadDeveloper.issues" collection field of "EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Controller\ProjectDomain\ProjectCrudController" wants to render its entries using an EasyAdmin CRUD form. However, no CRUD form was found related to this field. You can either create a CRUD controller for the entity "EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\DefaultApp\Entity\ProjectDomain\ProjectIssue" or pass the CRUD controller to use as the first argument of the "useEntryCrudForm()" method.'
        );

        $this->configure($field, pageName: Crud::PAGE_EDIT, controllerFqcn: ProjectCrudController::class);
    }

    /**
     * @dataProvider failsOnOptionEntryUsesCrudFormIfPropertyIsNotAssociation
     */
    public function testFailsOnOptionEntryUsesCrudFormIfPropertyIsNotAssociation(FieldInterface $field): void
    {
        $field->setCustomOption(CollectionField::OPTION_ENTRY_USES_CRUD_FORM, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'The "%s" collection field of "%s" cannot use the "useEntryCrudForm()" method because it is not a Doctrine association.',
            $field->getAsDto()->getProperty(),
            ProjectCrudController::class,
        ));

        $this->configure($field, pageName: Crud::PAGE_EDIT, controllerFqcn: ProjectCrudController::class);
    }

    public static function failsOnOptionEntryUsesCrudFormIfPropertyIsNotAssociation(): \Generator
    {
        yield [TextField::new('name')];
        yield [TextField::new('price')];
        yield [TextField::new('price.currency')];
    }

    /**
     * @dataProvider failsOnOptionEntryUsesCrudFormIfOptionEntryTypeIsUsed
     */
    public function testFailsOnOptionEntryUsesCrudFormIfOptionEntryTypeIsUsed(FieldInterface $field): void
    {
        $field->setCustomOption(CollectionField::OPTION_ENTRY_USES_CRUD_FORM, true)
            ->setCustomOption(CollectionField::OPTION_ENTRY_TYPE, 'foo')
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'The "%s" collection field of "%s" can render its entries using a Symfony Form (via the "setEntryType()" method) or using an EasyAdmin CRUD Form (via the "useEntryCrudForm()" method) but you cannot use both methods at the same time. Remove one of those two methods.',
            $field->getAsDto()->getProperty(),
            ProjectCrudController::class,
        ));

        $this->configure($field, pageName: Crud::PAGE_EDIT, controllerFqcn: ProjectCrudController::class);
    }

    public static function failsOnOptionEntryUsesCrudFormIfOptionEntryTypeIsUsed(): \Generator
    {
        yield [CollectionField::new('projectIssues')];
        yield [CollectionField::new('favouriteProjectOf')];
        yield [CollectionField::new('projectTags')];
    }

    /**
     * @dataProvider failsOnOptionRenderAsEmbeddedCrudFormIfNoCrudControllerCanBeFound
     */
    public function testFailsOnOptionRenderAsEmbeddedCrudFormIfNoCrudControllerCanBeFound(FieldInterface $field): void
    {
        $field->getAsDto()->setDoctrineMetadata((array) $this->projectDto->getClassMetadata()->getAssociationMapping($field->getAsDto()->getProperty()));
        $field->setCustomOption(CollectionField::OPTION_ENTRY_USES_CRUD_FORM, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('The "%s" collection field of "%s" wants to render its entries using an EasyAdmin CRUD form. However, no CRUD form was found related to this field. You can either create a CRUD controller for the entity "%s" or pass the CRUD controller to use as the first argument of the "useEntryCrudForm()" method.',
            $field->getAsDto()->getProperty(),
            ProjectCrudController::class,
            $field->getAsDto()->getDoctrineMetadata()->get('targetEntity'),
        ));

        $this->configure($field, pageName: Crud::PAGE_EDIT, controllerFqcn: ProjectCrudController::class);
    }

    public static function failsOnOptionRenderAsEmbeddedCrudFormIfNoCrudControllerCanBeFound(): \Generator
    {
        yield [CollectionField::new('projectIssues')];
        yield [CollectionField::new('favouriteProjectOf')];
        yield [CollectionField::new('projectTags')];
    }

    /**
     * Regression test for #7613: when a user supplies a formatValue() callable, the
     * (string)-casting loop inside formatCollection() must be skipped; otherwise an
     * item whose __toString() returns invalid UTF-8 makes Symfony String's truncate()
     * throw and the whole page returns a 500, even though the user's callable would
     * have overwritten the formatted value anyway.
     */
    public function testFormatCollectionShortCircuitsWhenFormatValueCallableIsSet(): void
    {
        $field = CollectionField::new('projectIssues');

        // force the (string)-casting code path by simulating an 'array' doctrine type
        // (otherwise formatCollection() short-circuits earlier on association fields).
        $field->getAsDto()->setDoctrineMetadata(['type' => 'array']);

        $invalidUtf8Item = new class implements \Stringable {
            public function __toString(): string
            {
                return "valid\xC3\x28more";
            }
        };
        $field->setValue([$invalidUtf8Item]);
        $field->formatValue(static fn ($coll): int => is_countable($coll) ? \count($coll) : 0);

        // the user-provided callable is applied later by CommonPostConfigurator (not
        // exercised here), so this assertion checks the configurator's own early return.
        $fieldDto = $this->configure($field);

        $this->assertSame(1, $fieldDto->getFormattedValue());
    }

    public function testEntryCrudFormSwapsTheContextOfTheCurrentRequest(): void
    {
        $context = $this->getAdminContext(Crud::PAGE_EDIT, 'en', Action::EDIT, ProjectWithNestedIssuesCrudController::class);
        $subRequest = $context->getRequest();
        $subRequest->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);

        // getAdminContext() reboots the kernel, so services must be fetched after it
        /** @var CollectionConfigurator $configurator */
        $configurator = static::getContainer()->get(CollectionConfigurator::class);
        /** @var RequestStack $requestStack */
        $requestStack = static::getContainer()->get('request_stack');

        $mainRequest = new Request();
        $requestStack->push($mainRequest);
        $requestStack->push($subRequest);

        try {
            $fieldDto = CollectionField::new('projectIssues')
                ->useEntryCrudForm(ProjectIssueNestedCrudController::class)
                ->getAsDto();
            $fieldDto->setFieldFqcn(CollectionField::class);

            // the embedded controller throws when its context doesn't hold the entry entity
            $configurator->configure($fieldDto, $this->getEntityDto(), $context);
        } finally {
            $requestStack->pop();
            $requestStack->pop();
        }

        $this->assertSame(CollectionType::class, $fieldDto->getFormType());
        $this->assertSame($context, $subRequest->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE));
        $this->assertFalse($mainRequest->attributes->has(EA::CONTEXT_REQUEST_ATTRIBUTE));
    }
}
