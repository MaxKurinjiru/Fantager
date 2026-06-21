<?php

declare(strict_types=1);

namespace App\Enum;

enum ChronicleCategory: string
{
    case All = 'all';
    case Ownership = 'ownership';
    case Competition = 'competition';
    case Roster = 'roster';
    case Economy = 'economy';

    /**
     * @return list<ChronicleEventType>|null null means no type restriction
     */
    public function types(): ?array
    {
        return match ($this) {
            self::All => null,
            self::Ownership => [
                ChronicleEventType::TeamEstablished,
                ChronicleEventType::PlayerJoined,
                ChronicleEventType::PlayerReleased,
                ChronicleEventType::TeamRenamed,
            ],
            self::Competition => [
                ChronicleEventType::BattleWin,
                ChronicleEventType::BattleLoss,
                ChronicleEventType::BattleDraw,
                ChronicleEventType::SeasonEnded,
            ],
            self::Roster => [
                ChronicleEventType::HeroLevelup,
                ChronicleEventType::HeroDied,
                ChronicleEventType::HeroRetired,
                ChronicleEventType::TrainingCompleted,
                ChronicleEventType::SummonCompleted,
                ChronicleEventType::DungeonCompleted,
                ChronicleEventType::HeroDismissed,
                ChronicleEventType::TrainerDismissed,
            ],
            self::Economy => [
                ChronicleEventType::ItemPurchased,
                ChronicleEventType::ItemSold,
                ChronicleEventType::HeroPurchased,
                ChronicleEventType::HeroSold,
                ChronicleEventType::TrainerPurchased,
                ChronicleEventType::TrainerSold,
            ],
        };
    }
}
