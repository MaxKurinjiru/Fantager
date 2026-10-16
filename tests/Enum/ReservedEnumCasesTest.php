<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\ChronicleEventType;
use App\Enum\FinancialRecordType;
use App\Enum\HeroChronicleEventType;
use App\Enum\HeroStatus;
use App\Enum\MatchType;
use App\Enum\MemorialCause;
use App\Enum\NotificationType;
use PHPUnit\Framework\TestCase;

/**
 * A6.2: reserved cases stay on the enum but must not look live.
 */
class ReservedEnumCasesTest extends TestCase
{
    public function testMatchTypeArenaIsReservedAndOthersAreLive(): void
    {
        $this->assertTrue(MatchType::Arena->isReserved());
        $this->assertFalse(MatchType::League->isReserved());
        $this->assertFalse(MatchType::Friendly->isReserved());
    }

    public function testFinancialRecordLiveCasesOmitReservedTypes(): void
    {
        $this->assertTrue(FinancialRecordType::SpellSlotCost->isReserved());
        $this->assertTrue(FinancialRecordType::MoraleRestoration->isReserved());
        $this->assertFalse(FinancialRecordType::SummonFee->isReserved());

        $live = FinancialRecordType::liveCases();
        $this->assertNotContains(FinancialRecordType::SpellSlotCost, $live);
        $this->assertNotContains(FinancialRecordType::MoraleRestoration, $live);
        $this->assertContains(FinancialRecordType::SummonFee, $live);
        $this->assertCount(count(FinancialRecordType::cases()) - 2, $live);
    }

    public function testChronicleLiveCasesOmitHeroRetired(): void
    {
        $this->assertTrue(ChronicleEventType::HeroRetired->isReserved());
        $this->assertFalse(ChronicleEventType::HeroDied->isReserved());

        $live = ChronicleEventType::liveCases();
        $this->assertNotContains(ChronicleEventType::HeroRetired, $live);
        $this->assertContains(ChronicleEventType::HeroDied, $live);
        $this->assertCount(count(ChronicleEventType::cases()) - 1, $live);
    }

    public function testMemorialCauseLiveCasesAreDismissedAndCombatDeath(): void
    {
        $this->assertTrue(MemorialCause::Age->isReserved());
        $this->assertTrue(MemorialCause::Retired->isReserved());
        $this->assertTrue(MemorialCause::Death->isReserved());
        $this->assertFalse(MemorialCause::Dismissed->isReserved());
        $this->assertFalse(MemorialCause::CombatDeath->isReserved());

        $this->assertSame(
            [MemorialCause::Dismissed, MemorialCause::CombatDeath],
            MemorialCause::liveCases(),
        );
    }

    public function testNotificationReservedTypesHaveNoLiveAlias(): void
    {
        $this->assertTrue(NotificationType::LeagueUpdate->isReserved());
        $this->assertTrue(NotificationType::EventStarted->isReserved());
        $this->assertTrue(NotificationType::SpellLearned->isReserved());
        $this->assertFalse(NotificationType::BattleResult->isReserved());
        $this->assertFalse(NotificationType::System->isReserved());
    }

    public function testHeroStatusRetiredIsReserved(): void
    {
        $this->assertTrue(HeroStatus::Retired->isReserved());
        $this->assertFalse(HeroStatus::Dead->isReserved());
        $this->assertFalse(HeroStatus::Available->isReserved());
    }

    public function testHeroChronicleRetiredIsReserved(): void
    {
        $this->assertTrue(HeroChronicleEventType::Retired->isReserved());
        $this->assertFalse(HeroChronicleEventType::Died->isReserved());
        $this->assertFalse(HeroChronicleEventType::Summoned->isReserved());
    }
}
