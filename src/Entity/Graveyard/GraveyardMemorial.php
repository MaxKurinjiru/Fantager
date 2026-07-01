<?php

declare(strict_types=1);

namespace App\Entity\Graveyard;

use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\HeroTrait;
use App\Enum\MemorialCause;
use App\Enum\Race;
use App\Repository\Graveyard\GraveyardMemorialRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GraveyardMemorialRepository::class)]
#[ORM\Table(name: 'graveyard')]
class GraveyardMemorial
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 10, enumType: Race::class)]
    private Race $race;

    #[ORM\Column(length: 15, enumType: HeroRole::class)]
    private HeroRole $roleAtDeparture;

    #[ORM\Column(length: 20, enumType: MemorialCause::class)]
    private MemorialCause $cause;

    #[ORM\Column]
    private int $age;

    #[ORM\Column(nullable: true)]
    private ?int $finalLevel = null;

    /** @var array<string, int> */
    #[ORM\Column(type: 'json')]
    private array $finalStats = [];

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $departedAt;

    #[ORM\Column(nullable: true)]
    private ?int $originalHeroId = null;

    #[ORM\Column(length: 30, nullable: true, enumType: HeroTrait::class)]
    private ?HeroTrait $trait = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $matchesPlayed = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $matchesWon = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $combatsFallen = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function setTeam(Team $team): static
    {
        $this->team = $team;

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

    public function getRace(): Race
    {
        return $this->race;
    }

    public function setRace(Race $race): static
    {
        $this->race = $race;

        return $this;
    }

    public function getRoleAtDeparture(): HeroRole
    {
        return $this->roleAtDeparture;
    }

    public function setRoleAtDeparture(HeroRole $roleAtDeparture): static
    {
        $this->roleAtDeparture = $roleAtDeparture;

        return $this;
    }

    public function getCause(): MemorialCause
    {
        return $this->cause;
    }

    public function setCause(MemorialCause $cause): static
    {
        $this->cause = $cause;

        return $this;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function getFinalLevel(): ?int
    {
        return $this->finalLevel;
    }

    public function setFinalLevel(?int $finalLevel): static
    {
        $this->finalLevel = $finalLevel;

        return $this;
    }

    /** @return array<string, int> */
    public function getFinalStats(): array
    {
        return $this->finalStats;
    }

    /** @param array<string, int> $finalStats */
    public function setFinalStats(array $finalStats): static
    {
        $this->finalStats = $finalStats;

        return $this;
    }

    public function getDepartedAt(): \DateTimeImmutable
    {
        return $this->departedAt;
    }

    public function setDepartedAt(\DateTimeImmutable $departedAt): static
    {
        $this->departedAt = $departedAt;

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

    public function getTrait(): ?HeroTrait
    {
        return $this->trait;
    }

    public function setTrait(?HeroTrait $trait): static
    {
        $this->trait = $trait;

        return $this;
    }

    public function getMatchesPlayed(): int
    {
        return $this->matchesPlayed;
    }

    public function setMatchesPlayed(int $matchesPlayed): static
    {
        $this->matchesPlayed = $matchesPlayed;

        return $this;
    }

    public function getMatchesWon(): int
    {
        return $this->matchesWon;
    }

    public function setMatchesWon(int $matchesWon): static
    {
        $this->matchesWon = $matchesWon;

        return $this;
    }

    public function getCombatsFallen(): int
    {
        return $this->combatsFallen;
    }

    public function setCombatsFallen(int $combatsFallen): static
    {
        $this->combatsFallen = $combatsFallen;

        return $this;
    }
}
