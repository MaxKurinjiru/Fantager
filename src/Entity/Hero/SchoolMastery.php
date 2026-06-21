<?php

declare(strict_types=1);

namespace App\Entity\Hero;

use App\Enum\School;
use App\Repository\Hero\SchoolMasteryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchoolMasteryRepository::class)]
#[ORM\Table(name: 'hero_school_mastery')]
#[ORM\UniqueConstraint(name: 'UNIQ_HERO_SCHOOL', columns: ['hero_id', 'school'])]
class SchoolMastery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'schoolMasteries')]
    #[ORM\JoinColumn(nullable: false)]
    private Hero $hero;

    #[ORM\Column(length: 10, enumType: School::class)]
    private School $school;

    #[ORM\Column(options: ['default' => 1])]
    private int $masteryTier = 1;

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

    public function getSchool(): School
    {
        return $this->school;
    }

    public function setSchool(School $school): static
    {
        $this->school = $school;

        return $this;
    }

    public function getMasteryTier(): int
    {
        return $this->masteryTier;
    }

    public function setMasteryTier(int $masteryTier): static
    {
        $this->masteryTier = $masteryTier;

        return $this;
    }
}
