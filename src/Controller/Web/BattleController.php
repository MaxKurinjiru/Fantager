<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Entity\Combat\Battle;
use App\Entity\League\LeagueFixture;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class BattleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/app/battles/{id}', name: 'app_battle_report', methods: ['GET'])]
    public function report(int $id): Response
    {
        $battle = $this->em->getRepository(Battle::class)->find($id);
        if (!$battle) {
            throw $this->createNotFoundException('Battle not found');
        }

        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            throw $this->createAccessDeniedException('No team assigned');
        }

        // Security check: Only allow viewing if the player's team participated in this battle
        if ($battle->getTeamA()->getId() !== $team->getId() && $battle->getTeamB()->getId() !== $team->getId()) {
            throw $this->createAccessDeniedException('You are not authorized to view this battle');
        }

        // Find the fixture associated with this battle to display round/kickoff details
        $fixture = $this->em->getRepository(LeagueFixture::class)->findOneBy(['battle' => $battle]);

        return $this->render('battle/index.html.twig', [
            'battle' => $battle,
            'fixture' => $fixture,
            'player_team' => $team,
        ]);
    }
}
