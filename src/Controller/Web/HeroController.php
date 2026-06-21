<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Repository\Hero\HeroRepository;
use App\Repository\Hero\HeroTrainingHistoryRepository;
use App\Repository\Item\ItemRepository;
use App\Repository\Spell\SpellRepository;
use App\Repository\Team\TeamSummonHistoryRepository;
use App\Service\Config\RaceConfig;
use App\Service\Training\TrainingService;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class HeroController extends AbstractController
{
    private const VALID_TABS = ['overview', 'equipment', 'spells', 'training', 'history'];

    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly ItemRepository $itemRepository,
        private readonly HeroTrainingHistoryRepository $heroTrainingHistoryRepository,
        private readonly TeamSummonHistoryRepository $teamSummonHistoryRepository,
        private readonly SpellRepository $spellRepository,
        private readonly TrainingService $trainingService,
        private readonly RaceConfig $raceConfig,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/heroes', name: 'app_heroes', methods: ['GET'])]
    public function roster(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        $heroes = $this->heroRepository->findCombatantsByTeam($team);

        return $this->render('hero/roster.html.twig', [
            'team' => $team,
            'heroes' => $heroes,
        ]);
    }

    #[Route('/app/heroes/{id}', name: 'app_heroes_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        /** @var \App\Entity\Hero\Hero|null $hero */
        $hero = $this->heroRepository->find($id);

        if (!$hero || $hero->getTeam()->getId() !== $team->getId()) {
            throw $this->createNotFoundException('Hero not found.');
        }

        $tab = $request->query->get('tab', 'overview');
        if (!in_array($tab, self::VALID_TABS, true)) {
            $tab = 'overview';
        }

        $equipped = $this->itemRepository->findBy(['equippedHero' => $hero]);
        $equippedBySlot = [];
        foreach ($equipped as $item) {
            if ($item->getEquippedSlot()) {
                $equippedBySlot[$item->getEquippedSlot()->value] = $item;
            }
        }

        /** @var \App\Entity\Hero\HeroTrainingHistory[] $trainingHistory */
        $trainingHistory = $this->heroTrainingHistoryRepository->findBy(
            ['hero' => $hero],
            ['completedAt' => 'DESC', 'id' => 'DESC'],
            10
        );

        $statBonuses = $this->raceConfig->getStatBonuses($hero->getRace());
        $summonRecord = $this->teamSummonHistoryRepository->findOneByHero($hero);

        $totalStatGain = 0;
        $completedTrainings = 0;
        foreach ($trainingHistory as $log) {
            if (null !== $log->getStatGain()) {
                $totalStatGain += $log->getStatGain();
                ++$completedTrainings;
            }
        }

        $items = $this->itemRepository->findBy(['ownerTeam' => $team]);
        $spells = $this->spellRepository->findAll();
        $heroes = $this->heroRepository->findCombatantsByTeam($team);
        $trainers = $this->heroRepository->findTrainersByTeam($team);

        $tz = new \DateTimeZone($team->getKingdom()->getTimezone());
        $nowLocal = new \DateTimeImmutable('now', $tz);
        $isTrainingLocked = $this->trainingService->isTrainingLockedForTeam($team, $nowLocal);
        $nextTick = $this->trainingService->getNextTrainingTime($nowLocal);
        $nextLock = $nextTick->modify('-46 hours');

        return $this->render('hero/detail.html.twig', [
            'team' => $team,
            'hero' => $hero,
            'tab' => $tab,
            'equipped' => $equippedBySlot,
            'trainingHistory' => $trainingHistory,
            'statBonuses' => $statBonuses,
            'summonRecord' => $summonRecord,
            'totalStatGain' => $totalStatGain,
            'completedTrainings' => $completedTrainings,
            'items' => $items,
            'spells' => $spells,
            'heroes' => $heroes,
            'trainers' => $trainers,
            'is_training_locked' => $isTrainingLocked,
            'next_tick' => $nextTick,
            'next_lock' => $nextLock,
            'next_tick_formatted' => $nextTick->format('d. m. Y H:i'),
            'next_lock_formatted' => $nextLock->format('d. m. Y H:i'),
            'trainer_limit' => $this->trainingService->getTrainerLimit($team),
            'training_service' => $this->trainingService,
        ]);
    }
}
