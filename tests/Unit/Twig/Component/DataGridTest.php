<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Twig\Component;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Orm\EntityPaginatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Twig\Component\DataGrid;
use EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option\Size;
use PHPUnit\Framework\TestCase;

class DataGridTest extends TestCase
{
    public function testMountInEasyAdminModeAssignsProps(): void
    {
        $paginator = $this->createMock(EntityPaginatorInterface::class);
        $entities = ['entity1', 'entity2'];

        $dataGrid = new DataGrid();
        $dataGrid->mount(paginator: $paginator, entities: $entities, withActions: true);

        $this->assertSame($paginator, $dataGrid->paginator);
        $this->assertSame($entities, $dataGrid->entities);
        $this->assertTrue($dataGrid->withActions);
        $this->assertFalse($dataGrid->isRawMode());
    }

    public function testMountWithoutAnyPropUsesEasyAdminMode(): void
    {
        $dataGrid = new DataGrid();
        $dataGrid->mount();

        $this->assertNull($dataGrid->paginator);
        $this->assertSame([], $dataGrid->entities);
        $this->assertFalse($dataGrid->isRawMode());
    }

    /**
     * @dataProvider provideSizeValues
     */
    public function testMountResolvesSize(string|Size|null $size, ?Size $expectedSize): void
    {
        $dataGrid = new DataGrid();
        $dataGrid->mount(size: $size);

        $this->assertSame($expectedSize, $dataGrid->size);
    }

    public static function provideSizeValues(): iterable
    {
        yield 'null' => [null, null];
        yield 'string' => ['sm', Size::Small];
        yield 'enum' => [Size::Large, Size::Large];
        yield 'unknown string falls back to null' => ['nope', null];
    }

    public function testMountThrowsWhenMixingBothModes(): void
    {
        $paginator = $this->createMock(EntityPaginatorInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot combine/');

        (new DataGrid())->mount(paginator: $paginator, rows: [['a' => 1]], columns: ['a']);
    }

    public function testMountThrowsWhenMixingEntitiesWithRawMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot combine/');

        (new DataGrid())->mount(entities: ['entity1'], rows: [['a' => 1]], columns: ['a']);
    }

    public function testMountThrowsWhenRawModeIsIncomplete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/requires passing both/');

        // "rows" without "columns" is an incomplete raw-mode configuration
        (new DataGrid())->mount(rows: [['a' => 1]]);
    }

    public function testMountInRawModeAssignsProps(): void
    {
        $rows = [['a' => 1], ['a' => 2]];
        $columns = ['a'];

        $dataGrid = new DataGrid();
        $dataGrid->mount(rows: $rows, columns: $columns, noResultsMessage: 'Nothing here');

        $this->assertSame($rows, $dataGrid->rows);
        $this->assertSame($columns, $dataGrid->columns);
        $this->assertSame('Nothing here', $dataGrid->noResultsMessage);
        $this->assertTrue($dataGrid->isRawMode());
    }

    public function testNormalizedColumnsFromString(): void
    {
        $dataGrid = new DataGrid();
        $dataGrid->mount(rows: [], columns: ['created_at']);

        $this->assertSame([[
            'name' => 'created_at',
            'label' => 'Created at',
            'sortable' => false,
            'sortUrl' => null,
            'sortDirection' => null,
            'align' => null,
            'cssClass' => null,
        ]], $dataGrid->normalizedColumns());
    }

    public function testNormalizedColumnsFromMap(): void
    {
        $dataGrid = new DataGrid();
        $dataGrid->mount(rows: [], columns: [[
            'name' => 'email',
            'label' => 'E-mail',
            'sortable' => true,
            'sortUrl' => '/?sort=email',
            'sortDirection' => 'asc',
            'align' => 'right',
            'cssClass' => 'text-muted',
        ]]);

        $this->assertSame([[
            'name' => 'email',
            'label' => 'E-mail',
            'sortable' => true,
            'sortUrl' => '/?sort=email',
            'sortDirection' => 'asc',
            'align' => 'right',
            'cssClass' => 'text-muted',
        ]], $dataGrid->normalizedColumns());
    }

    public function testNormalizedColumnsMapDefaultsLabelToHumanizedName(): void
    {
        $dataGrid = new DataGrid();
        $dataGrid->mount(rows: [], columns: [['name' => 'first-name']]);

        $columns = $dataGrid->normalizedColumns();
        $this->assertSame('First name', $columns[0]['label']);
        $this->assertFalse($columns[0]['sortable']);
    }

    public function testNormalizedColumnsSupportsMixedStringAndMap(): void
    {
        $dataGrid = new DataGrid();
        $dataGrid->mount(rows: [], columns: ['id', ['name' => 'email', 'sortable' => true]]);

        $columns = $dataGrid->normalizedColumns();
        $this->assertCount(2, $columns);
        $this->assertSame('id', $columns[0]['name']);
        $this->assertSame('Id', $columns[0]['label']);
        $this->assertSame('email', $columns[1]['name']);
        $this->assertTrue($columns[1]['sortable']);
    }

    public function testNormalizedColumnsRejectsColumnsWithoutName(): void
    {
        $dataGrid = new DataGrid();
        $dataGrid->mount(rows: [], columns: [['label' => 'No name']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/non-empty name/');

        $dataGrid->normalizedColumns();
    }

    public function testNormalizedColumnsRejectsColumnsOfWrongType(): void
    {
        $dataGrid = new DataGrid();
        $dataGrid->mount(rows: [], columns: [42]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be a string or an array/');

        $dataGrid->normalizedColumns();
    }
}
