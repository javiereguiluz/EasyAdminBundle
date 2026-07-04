<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Component;

use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\AbstractFieldFunctionalTest;

/**
 * Rendering tests for the raw (non-EasyAdmin) mode of the <twig:ea:DataGrid> component,
 * i.e. rendering a standalone grid from plain "rows" + "columns" with no AdminContext.
 */
class DataGridTest extends AbstractFieldFunctionalTest
{
    private function render(string $template, array $context = []): string
    {
        return static::getContainer()->get('twig')->createTemplate($template)->render($context);
    }

    public function testRawModeUsesBaselineTableClassWithoutLegacyDatagridClass(): void
    {
        $html = $this->render(
            '<twig:ea:DataGrid :rows="rows" :columns="columns" />',
            ['rows' => [['id' => 1]], 'columns' => ['id']]
        );

        // raw mode opts into the clean baseline styling, so it must NOT add the legacy "datagrid" class
        self::assertStringContainsString('<table class="ea-table">', $html);
        self::assertStringNotContainsString('datagrid', $html);
    }

    public function testRawModeRendersHeaderLabelsFromStringsAndMaps(): void
    {
        $html = $this->render(
            '<twig:ea:DataGrid :rows="rows" :columns="columns" />',
            [
                'rows' => [],
                // string column -> humanized label; map column -> its own label
                'columns' => ['created_at', ['name' => 'email', 'label' => 'E-mail']],
            ]
        );

        self::assertStringContainsString('data-column="created_at"', $html);
        self::assertStringContainsString('Created at', $html);
        self::assertStringContainsString('data-column="email"', $html);
        self::assertStringContainsString('E-mail', $html);
    }

    public function testRawModeRendersSortLinkOnlyForSortableColumnsWithUrl(): void
    {
        $html = $this->render(
            '<twig:ea:DataGrid :rows="rows" :columns="columns" />',
            [
                'rows' => [],
                'columns' => [
                    'id',
                    ['name' => 'email', 'sortable' => true, 'sortUrl' => '/?sort=email'],
                ],
            ]
        );

        // sortable column with a URL becomes a link...
        self::assertStringContainsString('<a href="/?sort=email">', $html);
        // ...while the plain column stays a non-clickable span
        self::assertStringContainsString('<span>Id</span>', $html);
    }

    public function testRawModeReadsCellValuesFromArraysAndObjects(): void
    {
        $html = $this->render(
            '<twig:ea:DataGrid :rows="rows" :columns="columns" />',
            [
                'columns' => ['name', 'email'],
                'rows' => [
                    ['name' => 'Ann', 'email' => 'ann@example.com'],
                    (object) ['name' => 'Cy', 'email' => 'cy@example.com'],
                ],
            ]
        );

        self::assertStringContainsString('<td data-column="name" data-label="Name">Ann</td>', $html);
        self::assertStringContainsString('ann@example.com', $html);
        // the object row is read with attribute(row, name) too
        self::assertStringContainsString('<td data-column="name" data-label="Name">Cy</td>', $html);
        self::assertStringContainsString('cy@example.com', $html);
    }

    public function testRawModeEscapesCellValues(): void
    {
        $html = $this->render(
            '<twig:ea:DataGrid :rows="rows" :columns="columns" />',
            [
                'columns' => ['name'],
                'rows' => [['name' => '<b>bold</b>']],
            ]
        );

        self::assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>bold</b>', $html);
    }

    public function testRawModeAppliesAlignAndCssClassToCells(): void
    {
        $html = $this->render(
            '<twig:ea:DataGrid :rows="rows" :columns="columns" />',
            [
                'columns' => [['name' => 'price', 'align' => 'right', 'cssClass' => 'fw-bold']],
                'rows' => [['price' => 42]],
            ]
        );

        // the header and the cell both get the alignment + custom class...
        self::assertStringContainsString('<th data-column="price" scope="col" class="text-right fw-bold">', $html);
        self::assertStringContainsString('<td data-column="price" data-label="Price" class="text-right fw-bold">42</td>', $html);
    }

    public function testRawModeDoesNotEmitEmptyClassAttribute(): void
    {
        $html = $this->render(
            '<twig:ea:DataGrid :rows="rows" :columns="columns" />',
            ['columns' => ['id'], 'rows' => [['id' => 1]]]
        );

        // a column without align/cssClass must not leave a dangling empty class attribute
        self::assertStringNotContainsString('class=""', $html);
        self::assertStringNotContainsString('class=" "', $html);
    }

    public function testRawModeAppliesSizeDensityClass(): void
    {
        $html = $this->render(
            '<twig:ea:DataGrid :rows="[]" :columns="[\'id\']" size="sm" />'
        );

        self::assertStringContainsString('class="ea-table ea-table-sm"', $html);
    }

    public function testRawModeRendersCustomEmptyMessageWhenThereAreNoRows(): void
    {
        $html = $this->render(
            '<twig:ea:DataGrid :rows="rows" :columns="columns" noResultsMessage="Nothing to see here" />',
            ['rows' => [], 'columns' => ['id', 'name']]
        );

        self::assertStringContainsString('class="ea-table-empty-row"', $html);
        self::assertStringContainsString('Nothing to see here', $html);
        // colspan spans every column
        self::assertStringContainsString('colspan="2"', $html);
    }

    public function testRawModeRendersEmptyCellWhenARowLacksAColumnKey(): void
    {
        $html = $this->render(
            '<twig:ea:DataGrid :rows="rows" :columns="columns" />',
            [
                'columns' => ['name', 'email'],
                // the second column is missing on purpose: it must render as an empty
                // cell instead of erroring under strict_variables
                'rows' => [['name' => 'Ann']],
            ]
        );

        self::assertStringContainsString('<td data-column="name" data-label="Name">Ann</td>', $html);
        self::assertStringContainsString('<td data-column="email" data-label="Email"></td>', $html);
    }

    public function testEasyAdminModeFailsWithAClearMessageOutsideEasyAdmin(): void
    {
        try {
            // passing no props at all selects the EasyAdmin mode, which cannot work
            // in this render because there is no admin context available
            $this->render('<twig:ea:DataGrid />');

            self::fail('Rendering the EasyAdmin mode outside EasyAdmin should fail.');
        } catch (\Throwable $e) {
            do {
                if (str_contains($e->getMessage(), 'no EasyAdmin admin context available')) {
                    $this->addToAssertionCount(1);

                    return;
                }
            } while (null !== $e = $e->getPrevious());

            self::fail('The failure does not explain that the admin context is missing.');
        }
    }
}
