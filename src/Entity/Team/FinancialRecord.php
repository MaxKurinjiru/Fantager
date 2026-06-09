<?php

declare(strict_types=1);

namespace App\Entity\Team;

use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Repository\Team\FinancialRecordRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FinancialRecordRepository::class)]
#[ORM\Table(name: 'team_financial_record')]
class FinancialRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 30, enumType: FinancialRecordType::class)]
    private FinancialRecordType $type;

    #[ORM\Column(length: 15, enumType: FinancialRecordActor::class)]
    private FinancialRecordActor $actor;

    #[ORM\Column(options: ['default' => 0])]
    private int $goldChange = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $crystalsChange = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $essenceCommonChange = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $essenceUncommonChange = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $essenceRareChange = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $essenceEpicChange = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $essenceLegendaryChange = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $essenceMythicChange = 0;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $context = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getType(): FinancialRecordType
    {
        return $this->type;
    }

    public function setType(FinancialRecordType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getActor(): FinancialRecordActor
    {
        return $this->actor;
    }

    public function setActor(FinancialRecordActor $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function getGoldChange(): int
    {
        return $this->goldChange;
    }

    public function setGoldChange(int $goldChange): static
    {
        $this->goldChange = $goldChange;

        return $this;
    }

    public function getCrystalsChange(): int
    {
        return $this->crystalsChange;
    }

    public function setCrystalsChange(int $crystalsChange): static
    {
        $this->crystalsChange = $crystalsChange;

        return $this;
    }

    public function getEssenceCommonChange(): int
    {
        return $this->essenceCommonChange;
    }

    public function setEssenceCommonChange(int $essenceCommonChange): static
    {
        $this->essenceCommonChange = $essenceCommonChange;

        return $this;
    }

    public function getEssenceUncommonChange(): int
    {
        return $this->essenceUncommonChange;
    }

    public function setEssenceUncommonChange(int $essenceUncommonChange): static
    {
        $this->essenceUncommonChange = $essenceUncommonChange;

        return $this;
    }

    public function getEssenceRareChange(): int
    {
        return $this->essenceRareChange;
    }

    public function setEssenceRareChange(int $essenceRareChange): static
    {
        $this->essenceRareChange = $essenceRareChange;

        return $this;
    }

    public function getEssenceEpicChange(): int
    {
        return $this->essenceEpicChange;
    }

    public function setEssenceEpicChange(int $essenceEpicChange): static
    {
        $this->essenceEpicChange = $essenceEpicChange;

        return $this;
    }

    public function getEssenceLegendaryChange(): int
    {
        return $this->essenceLegendaryChange;
    }

    public function setEssenceLegendaryChange(int $essenceLegendaryChange): static
    {
        $this->essenceLegendaryChange = $essenceLegendaryChange;

        return $this;
    }

    public function getEssenceMythicChange(): int
    {
        return $this->essenceMythicChange;
    }

    public function setEssenceMythicChange(int $essenceMythicChange): static
    {
        $this->essenceMythicChange = $essenceMythicChange;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }

    /** @param array<string, mixed> $context */
    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
