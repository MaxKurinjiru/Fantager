<?php

declare(strict_types=1);

namespace App\Entity\Kingdom;

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

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, options: ['default' => '1.00'])]
    private string $gameSpeed = '1.00';

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2, options: ['default' => '10.00'])]
    private string $marketplaceTaxRate = '10.00';

    #[ORM\Column(options: ['default' => 28])]
    private int $seasonLength = 28;

    #[ORM\Column(type: 'json')]
    private array $leagueTiersConfig = [];

    #[ORM\Column(options: ['default' => 100])]
    private int $levelCap = 100;

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

    public function getGameSpeed(): string
    {
        return $this->gameSpeed;
    }

    public function setGameSpeed(string $gameSpeed): static
    {
        $this->gameSpeed = $gameSpeed;

        return $this;
    }

    public function getMarketplaceTaxRate(): string
    {
        return $this->marketplaceTaxRate;
    }

    public function setMarketplaceTaxRate(string $marketplaceTaxRate): static
    {
        $this->marketplaceTaxRate = $marketplaceTaxRate;

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

    public function getLeagueTiersConfig(): array
    {
        return $this->leagueTiersConfig;
    }

    public function setLeagueTiersConfig(array $leagueTiersConfig): static
    {
        $this->leagueTiersConfig = $leagueTiersConfig;

        return $this;
    }

    public function getLevelCap(): int
    {
        return $this->levelCap;
    }

    public function setLevelCap(int $levelCap): static
    {
        $this->levelCap = $levelCap;

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
