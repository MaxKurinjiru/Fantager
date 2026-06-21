<?php

declare(strict_types=1);

namespace App\Entity\Hero;

use App\Enum\TrainingType;
use App\Repository\Hero\HeroTrainingHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HeroTrainingHistoryRepository::class)]
#[ORM\Table(name: 'hero_training_history')]
class HeroTrainingHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Hero $hero;

    #[ORM\Column(length: 15, enumType: TrainingType::class)]
    private TrainingType $trainingType;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $targetAttribute = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Hero $trainer = null;

    #[ORM\Column(nullable: true)]
    private ?int $statGain = null;

    #[ORM\Column]
    private \DateTimeImmutable $completedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHero(): Hero
    {
        return $this->hero;
    }

    public function setHero(Hero $hero): static
    {
        $this->hero = $hero;

        return $this;
    }

    public function getTrainingType(): TrainingType
    {
        return $this->trainingType;
    }

    public function setTrainingType(TrainingType $trainingType): static
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

    public function getStatGain(): ?int
    {
        return $this->statGain;
    }

    public function setStatGain(?int $statGain): static
    {
        $this->statGain = $statGain;

        return $this;
    }

    public function getCompletedAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }
}
