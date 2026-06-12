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
        $isLocked = $this->trainingService->isTrainingLockedForTeam($team, new \DateTimeImmutable());
        $nextTick = $this->trainingService->getNextTrainingTime(new \DateTimeImmutable());
        $trainerLimit = $this->trainingService->getTrainerLimit($team);

        return $this->render('training/index.html.twig', [
            'team' => $team,
            'heroes' => $heroes,
            'trainers' => $trainers,
            'isLocked' => $isLocked,
            'nextTick' => $nextTick,
            'trainerLimit' => $trainerLimit,
            'trainingService' => $this->trainingService,
        ]);
    }
}
