<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Repository\Hero\HeroRepository;
use App\Service\Training\TrainingService;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class TrainingController extends AbstractController
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly TrainingService $trainingService,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/training', name: 'app_training', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $heroId = $request->query->get('hero_id');
        if ($heroId) {
            return $this->redirectToRoute('app_heroes_detail', [
                'id' => $heroId,
                'tab' => 'training',
            ]);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        $team = $user?->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        $heroes = $this->heroRepository->findCombatantsByTeam($team);
        $trainers = $this->heroRepository->findTrainersByTeam($team);
        $tz = new \DateTimeZone($team->getKingdom()->getTimezone());
        $nowLocal = new \DateTimeImmutable('now', $tz);
        $nextTick = $this->trainingService->getNextTrainingTime($nowLocal);
        $nextLock = $nextTick->modify('-46 hours');
        $isLocked = $this->trainingService->isTrainingLockedForTeam($team, $nowLocal);

        return $this->render('training/index.html.twig', [
            'team' => $team,
            'heroes' => $heroes,
            'trainers' => $trainers,
            'isLocked' => $isLocked,
            'nextTick' => $nextTick,
            'nextLock' => $nextLock,
            'nextTickFormatted' => $nextTick->format('d. m. Y H:i'),
            'nextLockFormatted' => $nextLock->format('d. m. Y H:i'),
            'trainerLimit' => $this->trainingService->getTrainerLimit($team),
            'trainingService' => $this->trainingService,
        ]);
    }
}
