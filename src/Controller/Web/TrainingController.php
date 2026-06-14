<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Repository\Hero\HeroRepository;
use App\Repository\Training\TrainerRepository;
use App\Service\Training\TrainingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class TrainingController extends AbstractController
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly TrainerRepository $trainerRepository,
        private readonly TrainingService $trainingService,
    ) {
    }

    #[Route('/app/training', name: 'app_training', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $heroes = $this->heroRepository->findBy(['team' => $team]);
        $trainers = $this->trainerRepository->findBy(['team' => $team]);
        $tz = new \DateTimeZone($team->getKingdom()->getTimezone());
        $nowLocal = new \DateTimeImmutable('now', $tz);

        $isLocked = $this->trainingService->isTrainingLockedForTeam($team, $nowLocal);
        $nextTick = $this->trainingService->getNextTrainingTime($nowLocal);
        $nextLock = $nextTick->modify('-46 hours'); // Wednesday 12:00:00 local time
        $trainerLimit = $this->trainingService->getTrainerLimit($team);

        return $this->render('training/index.html.twig', [
            'team' => $team,
            'heroes' => $heroes,
            'trainers' => $trainers,
            'isLocked' => $isLocked,
            'nextTick' => $nextTick,
            'nextLock' => $nextLock,
            'nextTickFormatted' => $nextTick->format('d. m. Y H:i'),
            'nextLockFormatted' => $nextLock->format('d. m. Y H:i'),
            'trainerLimit' => $trainerLimit,
            'trainingService' => $this->trainingService,
        ]);
    }
}
