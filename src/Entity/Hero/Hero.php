<?php

declare(strict_types=1);

namespace App\Entity\Hero;

use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\HeroTrait;
use App\Enum\Race;
use App\Enum\TrainingType;
use App\Repository\Hero\HeroRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HeroRepository::class)]
#[ORM\Table(name: 'hero')]
class Hero
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

    #[ORM\Column(length: 15, enumType: HeroRole::class, options: ['default' => 'combatant'])]
    private HeroRole $role = HeroRole::Combatant;

    #[ORM\Column(options: ['default' => 1])]
    private int $level = 1;

    #[ORM\Column(options: ['default' => 0])]
    private int $xp = 0;

    #[ORM\Column]
    private int $age;

    #[ORM\Column(options: ['default' => 100])]
    private int $form = 100;

    #[ORM\Column(options: ['default' => 0])]
    private int $fatigue = 0;

    #[ORM\Column(options: ['default' => 50])]
    private int $morale = 50;

    #[ORM\Column(options: ['default' => 0])]
    private int $magicCapacity = 0;

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

    #[ORM\Column(options: ['default' => 0])]
    private int $baseOvr = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $complexRating = 0;

    #[ORM\Column(length: 15, enumType: HeroStatus::class)]
    private HeroStatus $status = HeroStatus::Available;

    #[ORM\Column(length: 30, nullable: true, enumType: HeroTrait::class)]
    private ?HeroTrait $trait = null;

    #[ORM\Column(length: 15, nullable: true, enumType: TrainingType::class)]
    private ?TrainingType $trainingType = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $targetAttribute = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'trainees')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hero $trainer = null;

    /** @var Collection<int, Hero> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'trainer')]
    private Collection $trainees;

    /** @var Collection<int, SchoolMastery> */
    #[ORM\OneToMany(targetEntity: SchoolMastery::class, mappedBy: 'hero', cascade: ['persist'])]
    private Collection $schoolMasteries;

    /** @var Collection<int, HeroSpell> */
    #[ORM\OneToMany(targetEntity: HeroSpell::class, mappedBy: 'hero', cascade: ['persist'])]
    private Collection $heroSpells;

    /** @var Collection<int, WeaponMastery> */
    #[ORM\OneToMany(targetEntity: WeaponMastery::class, mappedBy: 'hero', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $weaponMasteries;

    #[ORM\Column(options: ['default' => 0])]
    private int $matchesPlayed = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $matchesWon = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $combatsFallen = 0;

    public function __construct()
    {
        $this->trainees = new ArrayCollection();
        $this->schoolMasteries = new ArrayCollection();
        $this->weaponMasteries = new ArrayCollection();
        $this->heroSpells = new ArrayCollection();
    }

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

    public function getRole(): HeroRole
    {
        return $this->role;
    }

    public function setRole(HeroRole $role): static
    {
        $this->role = $role;
        if (HeroRole::Trainer === $role) {
            $this->trait = null;
        }

        return $this;
    }

    public function isTrainer(): bool
    {
        return HeroRole::Trainer === $this->role;
    }

    public function isCombatant(): bool
    {
        return HeroRole::Combatant === $this->role;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getXp(): int
    {
        return $this->xp;
    }

    public function setXp(int $xp): static
    {
        $this->xp = $xp;

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

    public function getForm(): int
    {
        return $this->form;
    }

    public function setForm(int $form): static
    {
        $this->form = $form;

        return $this;
    }

    public function getFatigue(): int
    {
        return $this->fatigue;
    }

    public function setFatigue(int $fatigue): static
    {
        $this->fatigue = $fatigue;

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

    public function getMagicCapacity(): int
    {
        return $this->magicCapacity;
    }

    public function setMagicCapacity(int $magicCapacity): static
    {
        $this->magicCapacity = $magicCapacity;

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

    public function getBaseOvr(): int
    {
        return $this->baseOvr;
    }

    public function setBaseOvr(int $baseOvr): static
    {
        $this->baseOvr = $baseOvr;

        return $this;
    }

    public function getComplexRating(): int
    {
        return $this->complexRating;
    }

    public function setComplexRating(int $complexRating): static
    {
        $this->complexRating = $complexRating;

        return $this;
    }

    public function getStatus(): HeroStatus
    {
        return $this->status;
    }

    public function setStatus(HeroStatus $status): static
    {
        $this->status = $status;

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

    /**
     * Zkratka pro kontrolu konkrétního traitu.
     * Bezpečná i pro hrdiny bez traitu (trait = null).
     */
    public function hasTrait(HeroTrait $trait): bool
    {
        return $this->trait === $trait;
    }

    public function getTrainingType(): ?TrainingType
    {
        return $this->trainingType;
    }

    public function setTrainingType(?TrainingType $trainingType): static
    {
        $this->trainingType = $trainingType;

        return $this;
    }

    public function getTargetAttribute(): ?string
    {
        return $this->targetAttribute;
    }

    public function setTargetAttribute(?string $targetAttribute): static
    {
        $this->targetAttribute = $targetAttribute;

        return $this;
    }

    public function getTrainer(): ?Hero
    {
        return $this->trainer;
    }

    public function setTrainer(?Hero $trainer): static
    {
        $this->trainer = $trainer;

        return $this;
    }

    /** @return Collection<int, Hero> */
    public function getTrainees(): Collection
    {
        return $this->trainees;
    }

    /** @return Collection<int, Hero> */
    public function getHeroes(): Collection
    {
        return $this->trainees;
    }

    public function addTrainee(Hero $hero): static
    {
        if (!$this->trainees->contains($hero)) {
            $this->trainees->add($hero);
            $hero->setTrainer($this);
        }

        return $this;
    }

    public function removeTrainee(Hero $hero): static
    {
        if ($this->trainees->removeElement($hero)) {
            if ($hero->getTrainer() === $this) {
                $hero->setTrainer(null);
            }
        }

        return $this;
    }

    public function addHero(Hero $hero): static
    {
        return $this->addTrainee($hero);
    }

    public function removeHero(Hero $hero): static
    {
        return $this->removeTrainee($hero);
    }

    /** @return Collection<int, SchoolMastery> */
    public function getSchoolMasteries(): Collection
    {
        return $this->schoolMasteries;
    }

    /** @return Collection<int, WeaponMastery> */
    public function getWeaponMasteries(): Collection
    {
        return $this->weaponMasteries;
    }

    /** @return Collection<int, HeroSpell> */
    public function getHeroSpells(): Collection
    {
        return $this->heroSpells;
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

    public function getRawStat(string $attribute): int
    {
        return match ($attribute) {
            'str' => $this->str,
            'dex' => $this->dex,
            'kon' => $this->kon,
            'spd' => $this->spd,
            'int' => $this->intel,
            'wil' => $this->wil,
            'cha' => $this->cha,
            'lck' => $this->lck,
            default => throw new \InvalidArgumentException(sprintf('Unknown attribute "%s"', $attribute)),
        };
    }
}
