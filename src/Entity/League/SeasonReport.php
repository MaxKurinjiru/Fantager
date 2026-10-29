<?php

declare(strict_types=1);

namespace App\Entity\League;

use App\Entity\Kingdom\Kingdom;
use App\Repository\League\SeasonReportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeasonReportRepository::class)]
#[ORM\Table(name: 'season_report')]
#[ORM\UniqueConstraint(name: 'UNIQ_SEASON_REPORT_SEASON', columns: ['season_id'])]
class SeasonReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Kingdom $kingdom;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LeagueSeason $season;

    #[ORM\Column]
    private int $seasonNumber;

    #[ORM\Column]
    private \DateTimeImmutable $generatedAt;

    #[ORM\Column(nullable: true)]
    private ?int $medianGold = null;

    #[ORM\Column(nullable: true)]
    private ?float $medianCombatantAge = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $combatDeathCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $marketVolume = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $crisisCount = 0;

    #[ORM\Column(nullable: true)]
    private ?float $meanKillScore = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSeason(): LeagueSeason
    {
        return $this->season;
    }

    public function setSeason(LeagueSeason $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getSeasonNumber(): int
    {
        return $this->seasonNumber;
    }

    public function setSeasonNumber(int $seasonNumber): static
    {
        $this->seasonNumber = $seasonNumber;

        return $this;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeImmutable $generatedAt): static
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    public function getMedianGold(): ?int
    {
        return $this->medianGold;
    }

    public function setMedianGold(?int $medianGold): static
    {
        $this->medianGold = $medianGold;

        return $this;
    }

    public function getMedianCombatantAge(): ?float
    {
        return $this->medianCombatantAge;
    }

    public function setMedianCombatantAge(?float $medianCombatantAge): static
    {
        $this->medianCombatantAge = $medianCombatantAge;

        return $this;
    }

    public function getCombatDeathCount(): int
    {
        return $this->combatDeathCount;
    }

    public function setCombatDeathCount(int $combatDeathCount): static
    {
        $this->combatDeathCount = $combatDeathCount;

        return $this;
    }

    public function getMarketVolume(): int
    {
        return $this->marketVolume;
    }

    public function setMarketVolume(int $marketVolume): static
    {
        $this->marketVolume = $marketVolume;

        return $this;
    }

    public function getCrisisCount(): int
    {
        return $this->crisisCount;
    }

    public function setCrisisCount(int $crisisCount): static
    {
        $this->crisisCount = $crisisCount;

        return $this;
    }

    public function getMeanKillScore(): ?float
    {
        return $this->meanKillScore;
    }

    public function setMeanKillScore(?float $meanKillScore): static
    {
        $this->meanKillScore = $meanKillScore;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<string, mixed> $payload */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }
}
