<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Entity\Community\ForumThread;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class CommunityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/app/community', name: 'app_community', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $kingdom = $team->getKingdom();

        $threads = $this->em->getRepository(ForumThread::class)->findBy(
            ['kingdom' => $kingdom],
            ['id' => 'DESC']
        );

        return $this->render('community/index.html.twig', [
            'team' => $team,
            'kingdom' => $kingdom,
            'threads' => $threads,
            'categories' => ['general', 'strategy', 'trading', 'bugs'],
        ]);
    }
}
