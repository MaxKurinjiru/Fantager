<?php

declare(strict_types=1);

namespace App\Service\TeamChronicle;

use App\Entity\Auth\User;
use App\Entity\Combat\Battle;
use App\Entity\Hero\Hero;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Entity\Team\TeamChronicle;
use App\Enum\ChronicleEventType;
use App\Enum\ChronicleReleaseReason;
use App\Enum\Race;
use App\Service\Calendar\TickClock;
use Doctrine\ORM\EntityManagerInterface;

class TeamChronicleService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TickClock $tickClock,
    ) {
    }

    public function recordTeamEstablished(Team $team, Kingdom $kingdom, int $seasonNumber): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::TeamEstablished,
            'activity.team_established',
            [
                'kingdom' => $kingdom->getName(),
                'season' => (string) $seasonNumber,
            ],
            [
                'kingdom_id' => $kingdom->getId(),
                'season' => $seasonNumber,
            ],
        );
    }

    public function recordPlayerJoined(Team $team, User $user): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::PlayerJoined,
            'activity.player_joined',
            [
                'player' => $user->getDisplayName(),
            ],
            [
                'user_id' => $user->getId(),
            ],
        );
    }

    public function recordPlayerReleased(Team $team, ?User $user, ChronicleReleaseReason $reason): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::PlayerReleased,
            'activity.player_released.'.$reason->value,
            [
                'player' => $user?->getDisplayName() ?? '—',
            ],
            [
                'user_id' => $user?->getId(),
                'reason' => $reason->value,
            ],
        );
    }

    public function recordSeasonEnded(
        Team $team,
        int $seasonNumber,
        string $tierName,
        int $position,
        string $status,
        int $goldGranted,
    ): TeamChronicle {
        return $this->create(
            $team,
            ChronicleEventType::SeasonEnded,
            'activity.season_ended',
            [
                'season' => (string) $seasonNumber,
                'tier' => $tierName,
                'position' => (string) $position,
                'status' => '' !== $status ? $status : 'maintained',
            ],
            [
                'gold' => $goldGranted,
                'status' => $status,
            ],
        );
    }

    public function recordSummonCompleted(Team $team, Hero $hero, Race $race, int $goldCost): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::SummonCompleted,
            'activity.summon_completed',
            [
                'hero' => $hero->getName(),
                'race' => $race->value,
            ],
            [
                'hero_id' => $hero->getId(),
                'race' => $race->value,
                'gold_cost' => $goldCost,
            ],
        );
    }

    public function recordHeroDismissed(Team $team, Hero $hero, int $compensation): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::HeroDismissed,
            'activity.hero_dismissed',
            [
                'hero' => $hero->getName(),
                'compensation' => (string) $compensation,
            ],
            [
                'hero_id' => $hero->getId(),
                'compensation' => $compensation,
            ],
        );
    }

    public function recordTrainerDismissed(Team $team, Hero $trainer, int $compensation): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::TrainerDismissed,
            'activity.trainer_dismissed',
            [
                'trainer' => $trainer->getName(),
                'compensation' => (string) $compensation,
            ],
            [
                'trainer_id' => $trainer->getId(),
                'compensation' => $compensation,
            ],
        );
    }

    public function recordHeroPurchased(Team $buyer, Hero $hero, Team $seller, int $price): TeamChronicle
    {
        return $this->create(
            $buyer,
            ChronicleEventType::HeroPurchased,
            'activity.hero_purchased',
            [
                'hero' => $hero->getName(),
                'race' => $hero->getRace()->value,
                'seller' => $seller->getName(),
                'price' => (string) $price,
            ],
            [
                'hero_id' => $hero->getId(),
                'seller_team_id' => $seller->getId(),
                'price' => $price,
            ],
        );
    }

    public function recordHeroSold(Team $seller, Hero $hero, Team $buyer, int $price): TeamChronicle
    {
        return $this->create(
            $seller,
            ChronicleEventType::HeroSold,
            'activity.hero_sold',
            [
                'hero' => $hero->getName(),
                'race' => $hero->getRace()->value,
                'buyer' => $buyer->getName(),
                'price' => (string) $price,
            ],
            [
                'hero_id' => $hero->getId(),
                'buyer_team_id' => $buyer->getId(),
                'price' => $price,
            ],
        );
    }

    public function recordTrainerPurchased(Team $buyer, Hero $trainer, Team $seller, int $price): TeamChronicle
    {
        return $this->create(
            $buyer,
            ChronicleEventType::TrainerPurchased,
            'activity.trainer_purchased',
            [
                'trainer' => $trainer->getName(),
                'race' => $trainer->getRace()->value,
                'seller' => $seller->getName(),
                'price' => (string) $price,
            ],
            [
                'trainer_id' => $trainer->getId(),
                'seller_team_id' => $seller->getId(),
                'price' => $price,
            ],
        );
    }

    public function recordTrainerSold(Team $seller, Hero $trainer, Team $buyer, int $price): TeamChronicle
    {
        return $this->create(
            $seller,
            ChronicleEventType::TrainerSold,
            'activity.trainer_sold',
            [
                'trainer' => $trainer->getName(),
                'race' => $trainer->getRace()->value,
                'buyer' => $buyer->getName(),
                'price' => (string) $price,
            ],
            [
                'trainer_id' => $trainer->getId(),
                'buyer_team_id' => $buyer->getId(),
                'price' => $price,
            ],
        );
    }

    public function recordTeamRenamed(Team $team, string $oldName, string $newName): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::TeamRenamed,
            'activity.team_renamed',
            [
                'old_name' => $oldName,
                'new_name' => $newName,
            ],
            [
                'old_name' => $oldName,
                'new_name' => $newName,
            ],
        );
    }

    public function recordBattleOutcome(Team $team, Team $opponent, int $ourScore, int $theirScore, Battle $battle): TeamChronicle
    {
        if ($ourScore > $theirScore) {
            $type = ChronicleEventType::BattleWin;
            $key = 'activity.battle_win';
        } elseif ($ourScore < $theirScore) {
            $type = ChronicleEventType::BattleLoss;
            $key = 'activity.battle_loss';
        } else {
            $type = ChronicleEventType::BattleDraw;
            $key = 'activity.battle_draw';
        }

        return $this->create(
            $team,
            $type,
            $key,
            [
                'opponent' => $opponent->getName(),
                'score' => $ourScore.':'.$theirScore,
            ],
            [
                'opponent_team_id' => $opponent->getId(),
                'our_score' => $ourScore,
                'their_score' => $theirScore,
                'battle_id' => $battle->getId(),
            ]
        );
    }

    public function recordTrainingCompleted(
        Team $team,
        Hero $hero,
        Hero $trainer,
        string $trainingTypeValue,
        ?string $attribute,
        int $gainRaw,
    ): TeamChronicle {
        $subjectKey = 'activity.training_completed.'.$trainingTypeValue;

        $gainFormatted = '';
        if ('attribute' === $trainingTypeValue) {
            $gainFormatted = '+'.number_format($gainRaw / 10, 1);
        } else {
            $gainFormatted = '+'.$gainRaw;
        }

        return $this->create(
            $team,
            ChronicleEventType::TrainingCompleted,
            $subjectKey,
            [
                'hero' => $hero->getName(),
                'trainer' => $trainer->getName(),
                'attribute' => $attribute ?? '',
                'gain' => $gainFormatted,
            ],
            [
                'hero_id' => $hero->getId(),
                'trainer_id' => $trainer->getId(),
                'type' => $trainingTypeValue,
                'attribute' => $attribute,
                'gain_raw' => $gainRaw,
            ]
        );
    }

    public function recordFacilityUpgraded(Team $team, string $facilityType, int $newLevel): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::FacilityUpgraded,
            'activity.facility_upgraded',
            [
                'facility' => $facilityType,
                'level' => (string) $newLevel,
            ],
            [
                'facility' => $facilityType,
                'level' => $newLevel,
            ]
        );
    }

    public function recordFacilityDowngraded(Team $team, string $facilityType, int $newLevel): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::FacilityDowngraded,
            'activity.facility_downgraded',
            [
                'facility' => $facilityType,
                'level' => (string) $newLevel,
            ],
            [
                'facility' => $facilityType,
                'level' => $newLevel,
            ]
        );
    }

    public function recordRaceOptimizationChanged(Team $team, ?string $race): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::RaceOptimizationChanged,
            'activity.race_optimization_changed',
            [
                'race' => $race ?? '',
            ],
            [
                'race' => $race,
            ]
        );
    }

    /**
     * @param array<string, string> $subjectParams
     * @param array<string, mixed>  $data
     */
    private function create(
        Team $team,
        ChronicleEventType $type,
        string $subjectKey,
        array $subjectParams,
        array $data = [],
    ): TeamChronicle {
        $entry = new TeamChronicle();
        $entry->setTeam($team);
        $entry->setType($type);
        $entry->setSubjectKey($subjectKey);
        $entry->setSubjectParams($subjectParams);
        $entry->setData($data);
        $entry->setCreatedAt($this->tickClock->getCurrentTime());
        $entry->setProcessedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $this->em->persist($entry);

        return $entry;
    }
}
