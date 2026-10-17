<?php

declare(strict_types=1);

namespace App\Entity\Kingdom;

use App\Enum\PacePreset;
use App\Repository\Kingdom\KingdomRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KingdomRepository::class)]
#[ORM\Table(name: 'kingdom')]
class Kingdom
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 5)]
    private string $language;

    #[ORM\Column(length: 50)]
    private string $timezone;

    #[ORM\Column(length: 20, enumType: PacePreset::class, options: ['default' => 'classic'])]
    private PacePreset $pacePreset = PacePreset::Classic;

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, options: ['default' => '1.00'])]
    private string $trainingSpeed = '1.00';

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, options: ['default' => '1.00'])]
    private string $recoverySpeed = '1.00';

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, options: ['default' => '1.00'])]
    private string $agingSpeed = '1.00';

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, options: ['default' => '1.00'])]
    private string $constructionSpeed = '1.00';

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, options: ['default' => '1.00'])]
    private string $economyScale = '1.00';

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, options: ['default' => '1.00'])]
    private string $fanPace = '1.00';

    #[ORM\Column(options: ['default' => 1])]
    private int $summonCap = 1;

    #[ORM\Column(options: ['default' => 77])]
    private int $seasonLength = 77;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $leagueTiersConfig = [];

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, options: ['default' => '1.00'])]
    private string $xpModifier = '1.00';

    #[ORM\Column(options: ['default' => 0])]
    private int $royalTreasuryGold = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getPacePreset(): PacePreset
    {
        return $this->pacePreset;
    }

    public function setPacePreset(PacePreset $pacePreset): static
    {
        $this->pacePreset = $pacePreset;

        return $this;
    }

    public function getTrainingSpeed(): string
    {
        return $this->trainingSpeed;
    }

    public function setTrainingSpeed(string $trainingSpeed): static
    {
        $this->trainingSpeed = $trainingSpeed;

        return $this;
    }

    public function getRecoverySpeed(): string
    {
        return $this->recoverySpeed;
    }

    public function setRecoverySpeed(string $recoverySpeed): static
    {
        $this->recoverySpeed = $recoverySpeed;

        return $this;
    }

    public function getAgingSpeed(): string
    {
        return $this->agingSpeed;
    }

    public function setAgingSpeed(string $agingSpeed): static
    {
        $this->agingSpeed = $agingSpeed;

        return $this;
    }

    public function getConstructionSpeed(): string
    {
        return $this->constructionSpeed;
    }

    public function setConstructionSpeed(string $constructionSpeed): static
    {
        $this->constructionSpeed = $constructionSpeed;

        return $this;
    }

    public function getEconomyScale(): string
    {
        return $this->economyScale;
    }

    public function setEconomyScale(string $economyScale): static
    {
        $this->economyScale = $economyScale;

        return $this;
    }

    public function getFanPace(): string
    {
        return $this->fanPace;
    }

    public function setFanPace(string $fanPace): static
    {
        $this->fanPace = $fanPace;

        return $this;
    }

    public function getSummonCap(): int
    {
        return $this->summonCap;
    }

    public function setSummonCap(int $summonCap): static
    {
        $this->summonCap = max(1, $summonCap);

        return $this;
    }

    public function getSeasonLength(): int
    {
        return $this->seasonLength;
    }

    public function setSeasonLength(int $seasonLength): static
    {
        $this->seasonLength = $seasonLength;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getLeagueTiersConfig(): array
    {
        return $this->leagueTiersConfig;
    }

    /** @param array<string, mixed> $leagueTiersConfig */
    public function setLeagueTiersConfig(array $leagueTiersConfig): static
    {
        $this->leagueTiersConfig = $leagueTiersConfig;

        return $this;
    }

    public function getXpModifier(): string
    {
        return $this->xpModifier;
    }

    public function setXpModifier(string $xpModifier): static
    {
        $this->xpModifier = $xpModifier;

        return $this;
    }

    public function getRoyalTreasuryGold(): int
    {
        return $this->royalTreasuryGold;
    }

    public function setRoyalTreasuryGold(int $royalTreasuryGold): static
    {
        $this->royalTreasuryGold = max(0, $royalTreasuryGold);

        return $this;
    }
}
