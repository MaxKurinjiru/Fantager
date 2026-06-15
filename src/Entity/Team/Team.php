<?php

declare(strict_types=1);

namespace App\Entity\Team;

use App\Entity\Auth\User;
use App\Entity\Kingdom\Kingdom;
use App\Repository\Team\TeamRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\Table(name: 'team')]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'team')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Kingdom $kingdom;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emblem = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $colors = null;

    #[ORM\Column(options: ['default' => 50])]
    private int $morale = 50;

    #[ORM\Column(options: ['default' => 0])]
    private int $reputation = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $chemistry = 0;

    #[ORM\Column(options: ['default' => 350])]
    private int $fanBase = 350;

    #[ORM\Column(options: ['default' => 0])]
    private int $gold = 0;


    #[ORM\Column(options: ['default' => 0])]
    private int $essenceCommon = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $essenceUncommon = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $essenceRare = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $essenceEpic = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $essenceLegendary = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $essenceMythic = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $isNpc = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSummonAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $summonsThisCycle = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getKingdom(): Kingdom
    {
        return $this->kingdom;
    }

    public function setKingdom(Kingdom $kingdom): static
    {
        $this->kingdom = $kingdom;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmblem(): ?string
    {
        return $this->emblem;
    }

    public function setEmblem(?string $emblem): static
    {
        $this->emblem = $emblem;

        return $this;
    }

    public function getColors(): ?array
    {
        return $this->colors;
    }

    public function setColors(?array $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    public function getMorale(): int
    {
        return $this->morale;
    }

    public function setMorale(int $morale): static
    {
        $this->morale = $morale;

        return $this;
    }

    public function getReputation(): int
    {
        return $this->reputation;
    }

    public function setReputation(int $reputation): static
    {
        $this->reputation = $reputation;

        return $this;
    }

    public function getChemistry(): int
    {
        return $this->chemistry;
    }

    public function setChemistry(int $chemistry): static
    {
        $this->chemistry = $chemistry;

        return $this;
    }

    public function getFanBase(): int
    {
        return $this->fanBase;
    }

    public function setFanBase(int $fanBase): static
    {
        $this->fanBase = $fanBase;

        return $this;
    }

    public function getGold(): int
    {
        return $this->gold;
    }

    public function setGold(int $gold): static
    {
        $this->gold = $gold;

        return $this;
    }

    public function getEssenceCommon(): int
    {
        return $this->essenceCommon;
    }

    public function setEssenceCommon(int $essenceCommon): static
    {
        $this->essenceCommon = $essenceCommon;

        return $this;
    }

    public function getEssenceUncommon(): int
    {
        return $this->essenceUncommon;
    }

    public function setEssenceUncommon(int $essenceUncommon): static
    {
        $this->essenceUncommon = $essenceUncommon;

        return $this;
    }

    public function getEssenceRare(): int
    {
        return $this->essenceRare;
    }

    public function setEssenceRare(int $essenceRare): static
    {
        $this->essenceRare = $essenceRare;

        return $this;
    }

    public function getEssenceEpic(): int
    {
        return $this->essenceEpic;
    }

    public function setEssenceEpic(int $essenceEpic): static
    {
        $this->essenceEpic = $essenceEpic;

        return $this;
    }

    public function getEssenceLegendary(): int
    {
        return $this->essenceLegendary;
    }

    public function setEssenceLegendary(int $essenceLegendary): static
    {
        $this->essenceLegendary = $essenceLegendary;

        return $this;
    }

    public function getEssenceMythic(): int
    {
        return $this->essenceMythic;
    }

    public function setEssenceMythic(int $essenceMythic): static
    {
        $this->essenceMythic = $essenceMythic;

        return $this;
    }

    public function isNpc(): bool
    {
        return $this->isNpc;
    }

    public function setIsNpc(bool $isNpc): static
    {
        $this->isNpc = $isNpc;

        return $this;
    }

    public function getLastSummonAt(): ?\DateTimeImmutable
    {
        return $this->lastSummonAt;
    }

    public function setLastSummonAt(?\DateTimeImmutable $lastSummonAt): static
    {
        $this->lastSummonAt = $lastSummonAt;

        return $this;
    }

    public function getSummonsThisCycle(): int
    {
        return $this->summonsThisCycle;
    }

    public function setSummonsThisCycle(int $summonsThisCycle): static
    {
        $this->summonsThisCycle = $summonsThisCycle;

        return $this;
    }
}
