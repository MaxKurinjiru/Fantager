<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\Hero\HeroRepository;
use App\Repository\Summoning\SummonHistoryRepository;
use App\Repository\Training\TrainerRepository;
use App\Service\Arena\ArenaService;
use App\Service\Summoning\SummoningService;
use App\Service\Training\TrainingService;
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
        private readonly SummonHistoryRepository $historyRepository,
        private readonly HeroRepository $heroRepository,
        private readonly TrainerRepository $trainerRepository,
        private readonly TrainingService $trainingService,
    ) {
    }

    #[Route('/app/hq', name: 'app_hq', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        $facility = $request->query->get('facility');
        if (!in_array($facility, self::FACILITY_PANELS, true)) {
            $facility = null;
        }

        $panelData = [];

        if ('arena' === $facility) {
            $panelData['arena_status'] = $this->arenaService->getArenaStatus($team);
        }

        if ('summoning_chamber' === $facility) {
            $panelData['summon_status'] = $this->summoningService->getStatus($team);
            $panelData['arena_theme'] = $this->summoningService->getArenaRaceTheme($team);
            $panelData['compatible_races'] = $this->summoningService->getCompatibleRacesDetails($team);

            $race = $request->query->get('race');
            if ('' === $race) {
                $race = null;
            }
            $sort = $request->query->get('sort', 'date-desc');
            $panelData['summon_history'] = $this->historyRepository->findByTeamFiltered($team, $race, $sort);
            $panelData['summon_history_race'] = $race;
            $panelData['summon_history_sort'] = $sort;
            $panelData['summon_subtab'] = $request->query->get('subtab', 'summon');
        }

        if ('training' === $facility) {
            $heroes = $this->heroRepository->findBy(['team' => $team]);
            $trainers = $this->trainerRepository->findBy(['team' => $team]);
            $tz = new \DateTimeZone($team->getKingdom()->getTimezone());
            $nowLocal = new \DateTimeImmutable('now', $tz);

            $panelData['heroes'] = $heroes;
            $panelData['trainers'] = $trainers;
            $panelData['is_locked'] = $this->trainingService->isTrainingLockedForTeam($team, $nowLocal);
            $nextTick = $this->trainingService->getNextTrainingTime($nowLocal);
            $nextLock = $nextTick->modify('-46 hours');
            $panelData['next_tick'] = $nextTick;
            $panelData['next_lock'] = $nextLock;
            $panelData['next_tick_formatted'] = $nextTick->format('d. m. Y H:i');
            $panelData['next_lock_formatted'] = $nextLock->format('d. m. Y H:i');
            $panelData['trainer_limit'] = $this->trainingService->getTrainerLimit($team);
            $panelData['training_service'] = $this->trainingService;
            $panelData['training_subtab'] = $request->query->get('subtab', 'trainers');
        }

        if ('barracks' === $facility) {
            $panelData['race_optimization'] = $hq?->getRaceOptimization();
            $panelData['pending_race_optimization'] = $hq?->getPendingRaceOptimization();
            $panelData['is_optimization_locked'] = $hq ? ($hq->hasPendingRaceOptimizationChange() || $hq->isRaceOptimizationLockCycle()) : false;
        }

        return $this->render('hq/index.html.twig', array_merge([
            'team' => $team,
            'hq' => $hq,
            'active_facility' => $facility,
        ], $panelData));
    }
}
