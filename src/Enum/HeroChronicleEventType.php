<?php

declare(strict_types=1);

namespace App\Enum;

enum HeroChronicleEventType: string
{
    case Summoned = 'summoned';
    case Transferred = 'transferred';
    case MatchPlayed = 'match_played';
    case LevelUp = 'levelup';
    case MasteryGained = 'mastery_gained';
    case TrainingCompleted = 'training_completed';
    case SpellLearned = 'spell_learned';
    case Injured = 'injured';
    case Recovered = 'recovered';
    case Retired = 'retired';
    case Died = 'died';
}
