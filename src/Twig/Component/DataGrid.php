<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Twig\Component;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Orm\EntityPaginatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option\Size;

/**
 * Renders a full data grid (a <table> built on top of the Table/* primitives).
 *
 * It supports two mutually exclusive modes:
 *
 *  - EasyAdmin mode: pass an "entities" collection (and optionally a "paginator"
 *    for the footer). Cells are rendered through each field's own template, so it
 *    reuses the whole EasyAdmin field pipeline. This mode requires a live EasyAdmin
 *    admin context, and it must be the context of the same CRUD controller index
 *    action that processed the entities: the sort state and URLs, the per-row
 *    actions and the pagination URLs are all resolved from that context. To embed
 *    a grid in other pages (e.g. a grid of comments inside a blog post page),
 *    render it through its own index request instead of inlining it.
 *  - Raw mode: pass "rows" + "columns" to render a grid outside of EasyAdmin, from
 *    plain PHP data (no AdminContext, DTOs or field templates involved).
 *
 * The raw-mode "columns" prop is a list of column definitions where each column is
 * either a string (used as both the row key and, humanized, the header label) or a
 * map with any of these keys:
 *
 *     ['name' => 'email', 'label' => 'E-mail', 'sortable' => true,
 *      'sortUrl' => '/?sort=email', 'sortDirection' => 'asc',
 *      'align' => 'right', 'cssClass' => 'text-muted']
 *
 * The raw-mode "rows" prop is a list of rows, where each row is either an
 * associative array or an object; cell values are read with attribute(row, name)
 * and rendered escaped.
 */
final class DataGrid
{
    /** EasyAdmin mode: the paginator used to render the footer (optional). */
    public ?EntityPaginatorInterface $paginator = null;

    /**
     * EasyAdmin mode: the processed entities (an EntityCollection) whose rows are rendered.
     *
     * @var iterable<EntityDto>
     */
    public iterable $entities = [];

    /**
     * Raw mode: the rows to render (each an associative array or an object).
     *
     * @var iterable<int, mixed>|null
     */
    public ?iterable $rows = null;

    /**
     * Raw mode: the column definitions (each a string or a map; see normalizedColumns()).
     *
     * @var iterable<int, mixed>|null
     */
    public ?iterable $columns = null;

    /** Whether to render a trailing column with each row's actions (EasyAdmin mode only). */
    public bool $withActions = false;

    /** Shared size token (density). 'md' is the base size and emits no class. */
    public ?Size $size = null;

    /** The message shown when there are no rows/entities (null falls back to a translated default). */
    public ?string $noResultsMessage = null;

    public function __construct(
        private readonly ?AdminContextProvider $adminContextProvider = null,
    ) {
    }

    /**
     * @param iterable<EntityDto>       $entities
     * @param iterable<int, mixed>|null $rows
     * @param iterable<int, mixed>|null $columns
     */
    public function mount(
        ?EntityPaginatorInterface $paginator = null,
        iterable $entities = [],
        ?iterable $rows = null,
        ?iterable $columns = null,
        bool $withActions = false,
        string|Size|null $size = null,
        ?string $noResultsMessage = null,
    ): void {
        $isRawMode = null !== $rows || null !== $columns;

        if ($isRawMode && (null !== $paginator || [] !== $entities)) {
            throw new \InvalidArgumentException('The DataGrid component cannot combine the EasyAdmin mode (the "paginator"/"entities" props) with the raw mode (the "rows"/"columns" props); pass only one of the two sets of props.');
        }

        if ($isRawMode && (null === $rows || null === $columns)) {
            throw new \InvalidArgumentException('The raw mode of the DataGrid component requires passing both the "rows" and the "columns" props.');
        }

        // TwigComponent "consumes" the props that match a mount() argument, so they
        // must be assigned to the public properties by hand.
        $this->paginator = $paginator;
        $this->entities = $entities;
        $this->rows = $rows;
        $this->columns = $columns;
        $this->withActions = $withActions;
        $this->size = ($size instanceof Size || null === $size) ? $size : Size::tryFrom($size);
        $this->noResultsMessage = $noResultsMessage;
    }

    public function isRawMode(): bool
    {
        return null !== $this->rows || null !== $this->columns;
    }

    /**
     * The admin context that the EasyAdmin mode renders with; it fails with an
     * explicit message when there is none (a null context would otherwise surface
     * as an obscure "attribute on null" Twig error deep inside the template).
     */
    public function context(): AdminContextInterface
    {
        $context = $this->adminContextProvider?->getContext();

        if (null === $context) {
            throw new \LogicException('The DataGrid component is being rendered in EasyAdmin mode (the "entities"/"paginator" props) but there is no EasyAdmin admin context available in the current request. The EasyAdmin mode only works in the backend pages of the related CRUD controller; to render a grid from arbitrary data anywhere, use the raw mode (the "rows" + "columns" props) instead.');
        }

        return $context;
    }

    /**
     * Normalizes the raw-mode "columns" prop into a uniform list of maps so the
     * template can consume them without caring whether each column was passed as a
     * plain string or as a partial map.
     *
     * It is deliberately NOT named after the "columns" prop: TwigComponent resolves
     * "this.columns" to the public property, so the template calls
     * "this.normalizedColumns" to reach this method instead.
     *
     * @return array<int, array{name: string, label: string, sortable: bool, sortUrl: ?string, sortDirection: ?string, align: ?string, cssClass: ?string}>
     */
    public function normalizedColumns(): array
    {
        $normalized = [];
        foreach ($this->columns ?? [] as $column) {
            if (\is_string($column)) {
                $column = ['name' => $column];
            }

            if (!\is_array($column)) {
                throw new \InvalidArgumentException(sprintf('Each column of the DataGrid component must be a string or an array, but one of the given columns is of type "%s".', get_debug_type($column)));
            }

            $name = $column['name'] ?? null;
            if (!\is_string($name) || '' === $name) {
                throw new \InvalidArgumentException('Each column of the DataGrid component must define a non-empty name, either as the column string itself or as the "name" key of the column map.');
            }

            $normalized[] = [
                'name' => $name,
                'label' => $column['label'] ?? ucfirst(str_replace(['_', '-', '.'], ' ', $name)),
                'sortable' => (bool) ($column['sortable'] ?? false),
                'sortUrl' => $column['sortUrl'] ?? null,
                'sortDirection' => $column['sortDirection'] ?? null,
                'align' => $column['align'] ?? null,
                'cssClass' => $column['cssClass'] ?? null,
            ];
        }

        return $normalized;
    }
}
