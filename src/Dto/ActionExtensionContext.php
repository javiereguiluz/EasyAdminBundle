<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Dto;

/**
 * Context object passed to action extensions containing minimal required information.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class ActionExtensionContext
{
    private string $crudControllerFqcn;
    private string $entityFqcn;
    private string $pageName;
    private ?string $dashboardFqcn;
    private ?object $user;
    private array $userRoles;

    public function __construct(
        string $crudControllerFqcn,
        string $entityFqcn,
        string $pageName,
        ?string $dashboardFqcn = null,
        ?object $user = null,
        array $userRoles = []
    ) {
        $this->crudControllerFqcn = $crudControllerFqcn;
        $this->entityFqcn = $entityFqcn;
        $this->pageName = $pageName;
        $this->dashboardFqcn = $dashboardFqcn;
        $this->user = $user;
        $this->userRoles = $userRoles;
    }

    public function getCrudControllerFqcn(): string
    {
        return $this->crudControllerFqcn;
    }

    public function getEntityFqcn(): string
    {
        return $this->entityFqcn;
    }

    public function getPageName(): string
    {
        return $this->pageName;
    }

    public function getDashboardFqcn(): ?string
    {
        return $this->dashboardFqcn;
    }

    public function getUser(): ?object
    {
        return $this->user;
    }

    public function getUserRoles(): array
    {
        return $this->userRoles;
    }
}