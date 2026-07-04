<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Component;

use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\AbstractFieldFunctionalTest;

/**
 * Rendering tests for the <twig:ea:Table> primitives (Table, Header, Body, Footer,
 * Row, HeaderCell, Cell, EmptyRow).
 */
class TableTest extends AbstractFieldFunctionalTest
{
    private function render(string $template, array $context = []): string
    {
        return static::getContainer()->get('twig')->createTemplate($template)->render($context);
    }

    public function testTableRendersBaseClassAndSlot(): void
    {
        $html = $this->render('<twig:ea:Table>rows</twig:ea:Table>');

        self::assertStringContainsString('<table class="ea-table">', $html);
        self::assertStringContainsString('rows', $html);
        self::assertStringContainsString('</table>', $html);
    }

    public function testTableSizeAddsDensityClass(): void
    {
        self::assertStringContainsString('class="ea-table ea-table-sm"', $this->render('<twig:ea:Table size="sm"></twig:ea:Table>'));
        self::assertStringContainsString('class="ea-table ea-table-lg"', $this->render('<twig:ea:Table size="lg"></twig:ea:Table>'));
        // "md" is the base size and emits no extra class
        self::assertStringContainsString('class="ea-table"', $this->render('<twig:ea:Table size="md"></twig:ea:Table>'));
    }

    public function testStructuralWrappersRenderSemanticTags(): void
    {
        self::assertStringContainsString('<thead>', $this->render('<twig:ea:Table:Header></twig:ea:Table:Header>'));
        self::assertStringContainsString('<tbody>', $this->render('<twig:ea:Table:Body></twig:ea:Table:Body>'));
        self::assertStringContainsString('<tfoot>', $this->render('<twig:ea:Table:Footer></twig:ea:Table:Footer>'));
    }

    public function testRowPassesThroughAttributesAndSelectableHook(): void
    {
        $plain = $this->render('<twig:ea:Table:Row data-id="7">c</twig:ea:Table:Row>');
        self::assertStringContainsString('data-id="7"', $plain);
        self::assertStringNotContainsString('ea-table-row-selectable', $plain);

        $selectable = $this->render('<twig:ea:Table:Row selectable="true">c</twig:ea:Table:Row>');
        self::assertStringContainsString('ea-table-row-selectable', $selectable);
    }

    public function testHeaderCellNotSortableRendersSpan(): void
    {
        $html = $this->render('<twig:ea:Table:HeaderCell column="name">Name</twig:ea:Table:HeaderCell>');

        self::assertStringContainsString('data-column="name"', $html);
        self::assertStringContainsString('<span>Name</span>', $html);
        self::assertStringNotContainsString('<a ', $html);
    }

    public function testHeaderCellSortableRendersLinkAndDirectionIcon(): void
    {
        // the sort direction icons are rendered as inline SVGs, so we assert on the
        // link + icon wrapper and that each direction produces a different icon
        $ascending = $this->render('<twig:ea:Table:HeaderCell column="name" sortable="true" sortDirection="asc" sortUrl="/sort?dir=desc">Name</twig:ea:Table:HeaderCell>');
        self::assertStringContainsString('<a href="/sort?dir=desc">', $ascending);
        self::assertStringContainsString('Name', $ascending);
        self::assertStringContainsString('class="icon"', $ascending);

        $descending = $this->render('<twig:ea:Table:HeaderCell column="name" sortable="true" sortDirection="DESC" sortUrl="/sort">Name</twig:ea:Table:HeaderCell>');
        $unsorted = $this->render('<twig:ea:Table:HeaderCell column="name" sortable="true" sortUrl="/sort">Name</twig:ea:Table:HeaderCell>');

        // ascending, descending and unsorted must each render a distinct sort icon
        self::assertNotSame($ascending, $descending);
        self::assertNotSame($ascending, $unsorted);
        self::assertNotSame($descending, $unsorted);
    }

    public function testHeaderCellEmitsScopeAndAriaSort(): void
    {
        $unsorted = $this->render('<twig:ea:Table:HeaderCell column="name">Name</twig:ea:Table:HeaderCell>');
        self::assertStringContainsString('scope="col"', $unsorted);
        self::assertStringNotContainsString('aria-sort', $unsorted);

        $ascending = $this->render('<twig:ea:Table:HeaderCell column="name" sortable="true" sortDirection="asc" sortUrl="/sort">Name</twig:ea:Table:HeaderCell>');
        self::assertStringContainsString('aria-sort="ascending"', $ascending);

        // the direction is normalized, so SortOrder-style uppercase values work too
        $descending = $this->render('<twig:ea:Table:HeaderCell column="name" sortable="true" sortDirection="DESC" sortUrl="/sort">Name</twig:ea:Table:HeaderCell>');
        self::assertStringContainsString('aria-sort="descending"', $descending);
    }

    public function testCellRendersDataAttributesAndSlot(): void
    {
        $html = $this->render('<twig:ea:Table:Cell column="id" label="ID">42</twig:ea:Table:Cell>');

        self::assertStringContainsString('data-column="id"', $html);
        self::assertStringContainsString('data-label="ID"', $html);
        self::assertStringContainsString('42', $html);
    }

    public function testEmptyRowRendersMessageWithColspan(): void
    {
        $html = $this->render('<twig:ea:Table:EmptyRow colspan="5" message="No results" />');

        self::assertStringContainsString('class="ea-table-empty-row"', $html);
        self::assertStringContainsString('colspan="5"', $html);
        self::assertStringContainsString('No results', $html);
    }

    public function testPrimitivesComposeIntoAFullTable(): void
    {
        $template = <<<'TWIG'
            <twig:ea:Table>
                <twig:ea:Table:Header>
                    <twig:ea:Table:Row>
                        <twig:ea:Table:HeaderCell column="name">Name</twig:ea:Table:HeaderCell>
                    </twig:ea:Table:Row>
                </twig:ea:Table:Header>
                <twig:ea:Table:Body>
                    <twig:ea:Table:Row data-id="1">
                        <twig:ea:Table:Cell column="name">Jane</twig:ea:Table:Cell>
                    </twig:ea:Table:Row>
                </twig:ea:Table:Body>
            </twig:ea:Table>
            TWIG;

        $html = $this->render($template);

        self::assertStringContainsString('<table class="ea-table">', $html);
        self::assertStringContainsString('<thead>', $html);
        self::assertStringContainsString('<th data-column="name" scope="col"><span>Name</span></th>', $html);
        self::assertStringContainsString('<tbody>', $html);
        self::assertStringContainsString('data-id="1"', $html);
        self::assertStringContainsString('<td data-column="name">Jane</td>', $html);
    }
}
