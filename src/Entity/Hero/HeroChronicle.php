<?php

declare(strict_types=1);

namespace App\Entity\Hero;

use App\Entity\Team\Team;
use App\Enum\HeroChronicleEventType;
use App\Repository\Hero\HeroChronicleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HeroChronicleRepository::class)]
#[ORM\Table(name: 'hero_chronicle')]
#[ORM\Index(name: 'IDX_HERO_TYPE', columns: ['hero_id', 'type'])]
#[ORM\Index(name: 'IDX_HERO_ORIGINAL', columns: ['original_hero_id'])]
#[ORM\Index(name: 'IDX_HERO_CREATED_AT', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class HeroChronicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hero $hero = null;

    #[ORM\Column(nullable: true)]
    private ?int $originalHeroId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Team $team = null;

    #[ORM\Column(length: 30, enumType: HeroChronicleEventType::class)]
    private HeroChronicleEventType $type;

    #[ORM\Column(length: 255)]
    private string $subjectKey;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $subjectParams = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHero(): ?Hero
    {
        return $this->hero;
    }

    public function setHero(?Hero $hero): static
    {
        $this->hero = $hero;

        return $this;
    }

    public function getOriginalHeroId(): ?int
    {
        return $this->originalHeroId;
    }

    public function setOriginalHeroId(?int $originalHeroId): static
    {
        $this->originalHeroId = $originalHeroId;

        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getType(): HeroChronicleEventType
    {
        return $this->type;
    }

    public function setType(HeroChronicleEventType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSubjectKey(): string
    {
        return $this->subjectKey;
    }

    public function setSubjectKey(string $subjectKey): static
    {
        $this->subjectKey = $subjectKey;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getSubjectParams(): array
    {
        return $this->subjectParams;
    }

    /** @param array<string, mixed> $subjectParams */
    public function setSubjectParams(array $subjectParams): static
    {
        $this->subjectParams = $subjectParams;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return $this->data;
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function syncOriginalHeroId(): void
    {
        if (null === $this->originalHeroId && null !== $this->hero) {
            $this->originalHeroId = $this->hero->getId();
        }
    }
}
