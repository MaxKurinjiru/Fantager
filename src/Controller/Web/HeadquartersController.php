<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Team\Team;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\Hero\HeroRepository;
use App\Repository\Team\TeamSummonHistoryRepository;
use App\Service\Headquarters\ArenaService;
use App\Service\Summoning\SummoningService;
use App\Service\Training\TrainingService;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class HeadquartersController extends AbstractController
{
    private const FACILITY_PANELS = [
        'arena',
        'summoning_chamber',
        'training',
        'barracks',
    ];

    public function __construct(
        private readonly HeadquartersRepository $hqRepository,
        private readonly ArenaService $arenaService,
        private readonly SummoningService $summoningService,
        private readonly TeamSummonHistoryRepository $historyRepository,
        private readonly HeroRepository $heroRepository,
        private readonly TrainingService $trainingService,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/hq', name: 'app_hq', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        /** @var Headquarters|null $hq */
        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        $activeFacility = $request->query->get('facility');
        if (!in_array($activeFacility, self::FACILITY_PANELS, true)) {
            $activeFacility = null;
        }

        return $this->render('hq/index.html.twig', array_merge(
            [
                'team' => $team,
                'hq' => $hq,
                'active_facility' => $activeFacility,
            ],
            $this->buildFacilityPanelData($team, $hq, $request),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFacilityPanelData(Team $team, ?Headquarters $hq, Request $request): array
    {
        $race = $request->query->get('race');
        if ('' === $race) {
            $race = null;
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 15;

        $historyRace = 'summoning_chamber' === $request->query->get('facility') ? $race : null;
        $totalItems = $this->historyRepository->countByTeamFiltered($team, $historyRace);
        $totalPages = max(1, (int) ceil($totalItems / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $heroes = $this->heroRepository->findCombatantsByTeam($team);
        $trainers = $this->heroRepository->findTrainersByTeam($team);
        $tz = new \DateTimeZone($team->getKingdom()->getTimezone());
        $nowLocal = new \DateTimeImmutable('now', $tz);
        $nextTick = $this->trainingService->getNextTrainingTime($nowLocal);
        $nextLock = $nextTick->modify('-46 hours');

        return [
            'arena_status' => $this->arenaService->getArenaStatus($team),
            'summon_status' => $this->summoningService->getStatus($team),
            'arena_theme' => $this->summoningService->getArenaRaceTheme($team),
            'compatible_races' => $this->summoningService->getCompatibleRacesDetails($team),
            'summon_history' => $this->historyRepository->findByTeamFiltered(
                $team,
                $historyRace,
                $request->query->get('sort', 'date-desc'),
                $page,
                $limit
            ),
            'summon_history_race' => $historyRace,
            'summon_history_sort' => $request->query->get('sort', 'date-desc'),
            'summon_history_page' => $page,
            'summon_history_total_pages' => $totalPages,
            'summon_subtab' => 'summoning_chamber' === $request->query->get('facility')
                ? $request->query->get('subtab', 'summon')
                : 'summon',
            'heroes' => $heroes,
            'trainers' => $trainers,
            'is_locked' => $this->trainingService->isTrainingLockedForTeam($team, $nowLocal),
            'next_tick' => $nextTick,
            'next_lock' => $nextLock,
            'next_tick_formatted' => $nextTick->format('d. m. Y H:i'),
            'next_lock_formatted' => $nextLock->format('d. m. Y H:i'),
            'trainer_limit' => $this->trainingService->getTrainerLimit($team),
            'training_service' => $this->trainingService,
            'training_subtab' => 'training' === $request->query->get('facility')
                ? $request->query->get('subtab', 'trainers')
                : 'trainers',
            'race_optimization' => $hq?->getRaceOptimization(),
            'pending_race_optimization' => $hq?->getPendingRaceOptimization(),
            'is_optimization_locked' => $hq ? ($hq->hasPendingRaceOptimizationChange() || $hq->isRaceOptimizationLockCycle()) : false,
        ];
    }
}
