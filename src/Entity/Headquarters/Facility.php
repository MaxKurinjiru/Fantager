<?php

declare(strict_types=1);

namespace App\Entity\Headquarters;

use App\Enum\FacilityType;
use App\Repository\Headquarters\FacilityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FacilityRepository::class)]
#[ORM\Table(name: 'headquarters_facility')]
#[ORM\UniqueConstraint(name: 'UNIQ_HQ_TYPE', columns: ['headquarters_id', 'type'])]
class Facility
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'facilities')]
    #[ORM\JoinColumn(nullable: false)]
    private Headquarters $headquarters;

    #[ORM\Column(length: 30, enumType: FacilityType::class)]
    private FacilityType $type;

    #[ORM\Column(options: ['default' => 1])]
    private int $level = 1;

    /** @var array<string, mixed> Any dynamic metadata associated with this facility (e.g. {"race_theme": "orc"}) */
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHeadquarters(): Headquarters
    {
        return $this->headquarters;
    }

    public function setHeadquarters(Headquarters $headquarters): static
    {
        $this->headquarters = $headquarters;

        return $this;
    }

    public function getType(): FacilityType
    {
        return $this->type;
    }

    public function setType(FacilityType $type): static
    {
        $this->type = $type;

        return $this;
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

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /** @return array<string, float|int> */
    public function getPassiveBonuses(): array
    {
        return $this->getPassiveBonusesAtLevel($this->level);
    }

    /** @return array<string, float|int> */
    public function getPassiveBonusesAtLevel(int $level): array
    {
        $bonuses = $this->type->getPassiveBonuses($level);

        foreach ($this->metadata as $key => $value) {
            if (is_numeric($value)) {
                $bonuses[$key] = (float) $value;
            }
        }

        return $bonuses;
    }

    /**
     * @param array<string, float|int|string> $passiveBonuses
     *
     * @deprecated Use setMetadata() instead
     */
    public function setPassiveBonuses(array $passiveBonuses): static
    {
        foreach ($passiveBonuses as $key => $value) {
            $this->metadata[$key] = $value;
        }

        return $this;
    }
}
