<?php

declare(strict_types=1);

namespace App\Entity\Training;

use App\Entity\Team\Team;
use App\Enum\Race;
use App\Enum\TrainerStatus;
use App\Repository\Training\TrainerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainerRepository::class)]
#[ORM\Table(name: 'trainer')]
class Trainer
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

    #[ORM\Column]
    private int $str;

    #[ORM\Column]
    private int $dex;

    #[ORM\Column]
    private int $kon;

    #[ORM\Column]
    private int $spd;

    #[ORM\Column(name: 'intel')]
    private int $intel;

    #[ORM\Column]
    private int $wil;

    #[ORM\Column]
    private int $cha;

    #[ORM\Column]
    private int $lck;

    #[ORM\Column]
    private int $age;

    #[ORM\Column]
    private int $deathExpectation;

    #[ORM\Column(length: 10, enumType: TrainerStatus::class)]
    private TrainerStatus $status = TrainerStatus::Active;

    #[ORM\Column(nullable: true)]
    private ?int $originalHeroId = null;

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

    public function getStr(): int
    {
        return (int) floor($this->str / 10);
    }

    public function getStrRaw(): int
    {
        return $this->str;
    }

    public function setStr(int $str): static
    {
        $this->str = $str;

        return $this;
    }

    public function setStrRaw(int $str): static
    {
        $this->str = $str;

        return $this;
    }

    public function getDex(): int
    {
        return (int) floor($this->dex / 10);
    }

    public function getDexRaw(): int
    {
        return $this->dex;
    }

    public function setDex(int $dex): static
    {
        $this->dex = $dex;

        return $this;
    }

    public function setDexRaw(int $dex): static
    {
        $this->dex = $dex;

        return $this;
    }

    public function getKon(): int
    {
        return (int) floor($this->kon / 10);
    }

    public function getKonRaw(): int
    {
        return $this->kon;
    }

    public function setKon(int $kon): static
    {
        $this->kon = $kon;

        return $this;
    }

    public function setKonRaw(int $kon): static
    {
        $this->kon = $kon;

        return $this;
    }

    public function getSpd(): int
    {
        return (int) floor($this->spd / 10);
    }

    public function getSpdRaw(): int
    {
        return $this->spd;
    }

    public function setSpd(int $spd): static
    {
        $this->spd = $spd;

        return $this;
    }

    public function setSpdRaw(int $spd): static
    {
        $this->spd = $spd;

        return $this;
    }

    public function getIntel(): int
    {
        return (int) floor($this->intel / 10);
    }

    public function getIntelRaw(): int
    {
        return $this->intel;
    }

    public function setIntel(int $intel): static
    {
        $this->intel = $intel;

        return $this;
    }

    public function setIntelRaw(int $intel): static
    {
        $this->intel = $intel;

        return $this;
    }

    public function getWil(): int
    {
        return (int) floor($this->wil / 10);
    }

    public function getWilRaw(): int
    {
        return $this->wil;
    }

    public function setWil(int $wil): static
    {
        $this->wil = $wil;

        return $this;
    }

    public function setWilRaw(int $wil): static
    {
        $this->wil = $wil;

        return $this;
    }

    public function getCha(): int
    {
        return (int) floor($this->cha / 10);
    }

    public function getChaRaw(): int
    {
        return $this->cha;
    }

    public function setCha(int $cha): static
    {
        $this->cha = $cha;

        return $this;
    }

    public function setChaRaw(int $cha): static
    {
        $this->cha = $cha;

        return $this;
    }

    public function getLck(): int
    {
        return (int) floor($this->lck / 10);
    }

    public function getLckRaw(): int
    {
        return $this->lck;
    }

    public function setLck(int $lck): static
    {
        $this->lck = $lck;

        return $this;
    }

    public function setLckRaw(int $lck): static
    {
        $this->lck = $lck;

        return $this;
    }

    public function getAge(): int
    {
        return (int) floor($this->age / 10);
    }

    public function getAgeRaw(): int
    {
        return $this->age;
    }

    public function setAge(int $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function setAgeRaw(int $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function getDeathExpectation(): int
    {
        return $this->deathExpectation;
    }

    public function setDeathExpectation(int $deathExpectation): static
    {
        $this->deathExpectation = $deathExpectation;

        return $this;
    }

    public function getStatus(): TrainerStatus
    {
        return $this->status;
    }

    public function setStatus(TrainerStatus $status): static
    {
        $this->status = $status;

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
}
