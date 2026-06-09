<?php

declare(strict_types=1);

namespace App\Tests\Entity\Team;

use App\Entity\Team\FinancialRecord;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use PHPUnit\Framework\TestCase;

class FinancialRecordTest extends TestCase
{
    public function testFinancialRecordProperties(): void
    {
        $team = $this->createMock(Team::class);
        $record = new FinancialRecord();

        $record->setTeam($team);
        $record->setType(FinancialRecordType::LeagueReward);
        $record->setActor(FinancialRecordActor::System);
        $record->setGoldChange(500);
        $record->setCrystalsChange(10);
        $record->setEssenceCommonChange(5);
        $record->setEssenceUncommonChange(-2);
        $record->setEssenceRareChange(0);
        $record->setEssenceEpicChange(0);
        $record->setEssenceLegendaryChange(0);
        $record->setEssenceMythicChange(0);
        
        $context = ['battle_id' => 42];
        $record->setContext($context);

        $createdAt = new \DateTimeImmutable('2026-06-04 12:00:00');
        $record->setCreatedAt($createdAt);

        $this->assertSame($team, $record->getTeam());
        $this->assertSame(FinancialRecordType::LeagueReward, $record->getType());
        $this->assertSame(FinancialRecordActor::System, $record->getActor());
        $this->assertSame(500, $record->getGoldChange());
        $this->assertSame(10, $record->getCrystalsChange());
        $this->assertSame(5, $record->getEssenceCommonChange());
        $this->assertSame(-2, $record->getEssenceUncommonChange());
        $this->assertSame(0, $record->getEssenceRareChange());
        $this->assertSame(0, $record->getEssenceEpicChange());
        $this->assertSame(0, $record->getEssenceLegendaryChange());
        $this->assertSame(0, $record->getEssenceMythicChange());
        $this->assertSame($context, $record->getContext());
        $this->assertSame($createdAt, $record->getCreatedAt());
    }

    public function testDefaultValues(): void
    {
        $record = new FinancialRecord();
        $this->assertSame(0, $record->getGoldChange());
        $this->assertSame(0, $record->getCrystalsChange());
        $this->assertSame(0, $record->getEssenceCommonChange());
        $this->assertSame(0, $record->getEssenceUncommonChange());
        $this->assertSame(0, $record->getEssenceRareChange());
        $this->assertSame(0, $record->getEssenceEpicChange());
        $this->assertSame(0, $record->getEssenceLegendaryChange());
        $this->assertSame(0, $record->getEssenceMythicChange());
        $this->assertSame([], $record->getContext());
        $this->assertInstanceOf(\DateTimeImmutable::class, $record->getCreatedAt());
    }
}
