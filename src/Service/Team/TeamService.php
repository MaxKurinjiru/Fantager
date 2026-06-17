<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\HeroStatus;
use App\Exception\UserFacingException;
use App\Repository\Formation\FormationRepository;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\Hero\HeroRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Formation\FixtureFormationService;
use Doctrine\ORM\EntityManagerInterface;

class TeamService
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly FormationRepository $formationRepository,
        private readonly HeadquartersRepository $hqRepository,
        private readonly LeagueFixtureRepository $leagueFixtureRepository,
        private readonly FinancialCrisisService $financialCrisisService,
        private readonly FixtureFormationService $fixtureFormationService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Aggregated dashboard data for the team's main screen.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(Team $team): array
    {
        $combatants = $this->heroRepository->findCombatantsByTeam($team);

        $heroCount = count($combatants);
        $activeCount = 0;
        $trainingCount = 0;
        foreach ($combatants as $h) {
            if (null !== $h->getTrainer()) {
                ++$trainingCount;
            } elseif (HeroStatus::Available === $h->getStatus()) {
                ++$activeCount;
            }
        }

        /** @var \App\Entity\Headquarters\Headquarters|null $hq */
        $hq = $this->hqRepository->findOneBy(['team' => $team]);

        $formationCount = $this->formationRepository->countSavedByTeam($team);

        $nextFixture = $this->leagueFixtureRepository->findNextFixtureForTeam($team);

        return [
            'team' => [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'emblem' => $team->getEmblem(),
                'colors' => $team->getColors(),
                'morale' => $team->getMorale(),
                'reputation' => $team->getReputation(),
                'chemistry' => $team->getChemistry(),
            ],
            'resources' => [
                'gold' => $team->getGold(),
                'essence_common' => $team->getEssenceCommon(),
                'essence_uncommon' => $team->getEssenceUncommon(),
                'essence_rare' => $team->getEssenceRare(),
                'essence_epic' => $team->getEssenceEpic(),
                'essence_legendary' => $team->getEssenceLegendary(),
                'essence_mythic' => $team->getEssenceMythic(),
            ],
            'heroes' => [
                'total' => $heroCount,
                'available' => $activeCount,
                'in_training' => $trainingCount,
            ],
            'headquarters' => [
                'total_level' => $hq?->getComputedTotalLevel() ?? 0,
                'initialized' => null !== $hq,
                'race_optimization' => $hq?->getRaceOptimization(),
                'pending_race_optimization' => $hq?->getPendingRaceOptimization(),
                'is_optimization_locked' => $hq ? ($hq->hasPendingRaceOptimizationChange() || $hq->isRaceOptimizationLockCycle()) : false,
            ],
            'formations' => [
                'count' => $formationCount,
            ],
            'summoning' => [
                'summons_this_cycle' => $team->getSummonsThisCycle(),
                'last_summon_at' => $team->getLastSummonAt()?->format(\DateTimeInterface::ATOM),
            ],
            'next_match' => $nextFixture ? [
                'id' => $nextFixture->getId(),
                'home_team' => [
                    'id' => $nextFixture->getHomeTeam()->getId(),
                    'name' => $nextFixture->getHomeTeam()->getName(),
                    'emblem' => $nextFixture->getHomeTeam()->getEmblem(),
                    'is_npc' => $nextFixture->getHomeTeam()->isNpc(),
                    'owner_name' => $nextFixture->getHomeTeam()->getUser()?->getDisplayName(),
                ],
                'away_team' => [
                    'id' => $nextFixture->getAwayTeam()->getId(),
                    'name' => $nextFixture->getAwayTeam()->getName(),
                    'emblem' => $nextFixture->getAwayTeam()->getEmblem(),
                    'is_npc' => $nextFixture->getAwayTeam()->isNpc(),
                    'owner_name' => $nextFixture->getAwayTeam()->getUser()?->getDisplayName(),
                ],
                'scheduled_at' => $nextFixture->getScheduledAt()->format(\DateTimeInterface::ATOM),
                'group_name' => $nextFixture->getGroup()->getGroupName(),
                'formation' => $this->serializeFixtureFormation($nextFixture, $team),
            ] : null,
            'financial_crisis' => $this->financialCrisisService->getStatus($team),
        ];
    }

    /**
     * Update team display settings (name, emblem, colors).
     *
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException
     */
    public function updateSettings(Team $team, array $data): void
    {
        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            if ('' === $name) {
                throw new UserFacingException('error.team_name_empty');
            }
            if (mb_strlen($name) > 100) {
                throw new UserFacingException('error.team_name_too_long');
            }
            $team->setName($name);
        }

        if (array_key_exists('emblem', $data)) {
            $emblem = null !== $data['emblem'] ? (string) $data['emblem'] : null;
            if (null !== $emblem && mb_strlen($emblem) > 255) {
                throw new UserFacingException('error.emblem_too_long');
            }
            $team->setEmblem($emblem);
        }

        if (array_key_exists('colors', $data)) {
            $colors = is_array($data['colors']) ? $data['colors'] : null;
            $team->setColors($colors);
        }

        $this->em->flush();
    }

    /** @return array<string, mixed> */
    private function serializeFixtureFormation(LeagueFixture $fixture, Team $team): array
    {
        return $this->fixtureFormationService->getFormationSummary($fixture, $team);
    }
}
