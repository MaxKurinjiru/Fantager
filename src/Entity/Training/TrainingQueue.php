<?php

declare(strict_types=1);

namespace App\Entity\Training;

use App\Entity\Hero\Hero;
use App\Enum\TrainingStatus;
use App\Enum\TrainingType;
use App\Repository\Training\TrainingQueueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingQueueRepository::class)]
#[ORM\Table(name: 'training_queue')]
class TrainingQueue
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
    private ?Trainer $trainer = null;

    #[ORM\Column(length: 15, enumType: TrainingStatus::class)]
    private TrainingStatus $status = TrainingStatus::Pending;

    #[ORM\Column(nullable: true)]
    private ?int $statGain = null;

    #[ORM\Column]
    private \DateTimeImmutable $executeAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

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

    public function getTrainer(): ?Trainer
    {
        return $this->trainer;
    }

    public function setTrainer(?Trainer $trainer): static
    {
        $this->trainer = $trainer;

        return $this;
    }

    public function getStatus(): TrainingStatus
    {
        return $this->status;
    }

    public function setStatus(TrainingStatus $status): static
    {
        $this->status = $status;

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

    public function getExecuteAt(): \DateTimeImmutable
    {
        return $this->executeAt;
    }

    public function setExecuteAt(\DateTimeImmutable $executeAt): static
    {
        $this->executeAt = $executeAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }
}
