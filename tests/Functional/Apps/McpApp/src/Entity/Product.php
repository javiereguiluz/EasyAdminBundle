<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Organization $organization;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $description = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $priceInCents = null;

    #[ORM\Column(type: 'boolean')]
    public bool $active = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    public ?string $status = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $secretNote = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    public ?string $cardNumber = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    public ?string $internalCode = null;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    public Collection $tags;

    public function __construct(string $name, Organization $organization)
    {
        $this->name = $name;
        $this->organization = $organization;
        $this->tags = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }
}
