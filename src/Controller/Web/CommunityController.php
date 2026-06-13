<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Entity\Community\ForumThread;
use App\Entity\Team\Team;
use App\Service\Community\CommunityService;
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
        private readonly CommunityService $communityService,
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

        // Fetch other player teams in the same kingdom for recipient dropdown
        $otherTeams = $this->em->getRepository(Team::class)->createQueryBuilder('t')
            ->where('t.kingdom = :kingdom')
            ->andWhere('t.id != :teamId')
            ->andWhere('t.isNpc = false')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('teamId', $team->getId())
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Fetch threads in this kingdom
        $threads = $this->em->getRepository(ForumThread::class)->findBy(
            ['kingdom' => $kingdom],
            ['id' => 'DESC']
        );

        $inbox = $this->communityService->getInboxMessages($team);
        $sent = $this->communityService->getSentMessages($team);

        return $this->render('community/index.html.twig', [
            'team' => $team,
            'kingdom' => $kingdom,
            'other_teams' => $otherTeams,
            'threads' => $threads,
            'inbox' => $inbox,
            'sent' => $sent,
            'categories' => ['general', 'strategy', 'trading', 'bugs'],
        ]);
    }
}
